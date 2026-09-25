<?php
/**
 * Onglet « Salaires » de la fiche d'un employé :
 *  - règles de paie propres à l'employé (retenue pour absence, retards, heures supplémentaires, remplacements) :
 *    par défaut « règle générale » ; chaque changement est gardé dans l'historique de l'employé ;
 *  - ses avances sur salaire et ses prêts (déjà retenu, reste à retenir), avec les boutons pour en donner ;
 *  - ses bulletins de paie, avec un bouton « Nouveau bulletin » (période proposée : suite du dernier).
 *
 * Fichier : custom/salaires/employe.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
if (!$user->hasRight('personnel', 'employe', 'lire') || !$user->hasRight('salaires', 'bulletin', 'lire')) {
	accessforbidden();
}
$canregle = $user->hasRight('salaires', 'regle', 'modifier');
$object = new EcoleEmploye($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);

// Règles : champ => [valeurs possibles, préfixe des libellés, constante générale]
$regles = array(
	'regle_retenue' => array(array('aucune', 'prorata'), 'PERSONNEL_RETENUE_ABSENCE_', 'RegleRetenue'),
	'regle_retard' => array(array('aucun', 'minutes', 'seuil'), 'PERSONNEL_RETARD_EFFET_', 'RegleRetard'),
	'regle_hsup' => array(array('1', '0'), '', 'RegleHeuresSup'),
	'regle_remplacement' => array(array('taux', 'forfait', 'aucun'), 'PERSONNEL_REMPLACEMENT_PAIE_', 'RegleRemplacement'),
);
$gen = personnel_regles_generales();
$generale = array(
	'regle_retenue' => $langs->transnoentities('PERSONNEL_RETENUE_ABSENCE_'.$gen['retenue']),
	'regle_retard' => $langs->transnoentities('PERSONNEL_RETARD_EFFET_'.$gen['retard']),
	'regle_hsup' => $langs->transnoentities('PERSONNEL_HEURES_SUP_'.($gen['hsup'] ? 'oui' : 'non')),
	'regle_remplacement' => $langs->transnoentities('PERSONNEL_REMPLACEMENT_PAIE_'.$gen['remplacement']),
);
$libelle = function ($champ, $v) use ($regles, $langs) {
	if ($champ === 'regle_hsup') {
		return $langs->transnoentities('PERSONNEL_HEURES_SUP_'.((string) $v === '1' ? 'oui' : 'non'));
	}
	return $langs->transnoentities($regles[$champ][1].$v);
};

/*
 * Actions
 */
if ($action == 'setregles' && $canregle) {
	$db->begin();
	$error = 0;
	foreach ($regles as $champ => $r) {
		$v = GETPOST($champ, 'aZ09');
		$v = in_array($v, $r[0], true) ? $v : null;
		$avant = ($object->$champ === null || $object->$champ === '') ? null : (string) $object->$champ;
		if ($avant === $v) {
			continue;
		}
		$sql = "UPDATE ".$db->prefix()."ecole_employe SET ".$champ." = ".($v === null ? "NULL" : ($champ === 'regle_hsup' ? (int) $v : "'".$db->escape($v)."'"));
		$sql .= ", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $object->id);
		if (!$db->query($sql) || $object->addLog($user, $champ, $avant === null ? $langs->transnoentities('RegleGenerale') : $libelle($champ, $avant),
			$v === null ? $langs->transnoentities('RegleGenerale') : $libelle($champ, $v), $langs->transnoentities('ReglesPaieEmploye')) < 0) {
			$error++;
		}
	}
	if ($error) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	}
	header('Location: '.$self);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $object->ref.' - '.$langs->trans('OngletSalaires'), '', '', 0, 0, '', '', '', 'mod-salaires page-employe');
employe_print_banner($object, 'salaires');
print '<div class="underbanner clearboth"></div>';

// Règles de paie de l'employé
print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setregles">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-balance-scale', 'class="pictofixedwidth"').$langs->trans('ReglesPaieEmploye').' <span class="opacitymedium small">— '.$langs->trans('ReglesPaieEmployeAide').'</span></td></tr>';
foreach ($regles as $champ => $r) {
	$options = array('' => $langs->transnoentities('RegleGeneraleValeur', $generale[$champ]));
	foreach ($r[0] as $v) {
		$options[$v] = $libelle($champ, $v);
	}
	$cur = ($object->$champ === null || $object->$champ === '') ? '' : (string) $object->$champ;
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans($r[2]).'</td><td>';
	if ($canregle) {
		print $form->selectarray($champ, $options, $cur, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
	} else {
		print dol_escape_htmltag($options[$cur]);
	}
	if ($cur !== '') {
		print ' <span class="badge badge-warning">'.$langs->trans('RegleParticuliere').'</span>';
	}
	print '</td></tr>';
}
print '</table>';
if ($canregle) {
	print '<div class="center margintoponly"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	print ' <a class="marginleftonly" href="'.dol_buildpath('/personnel/admin/regles.php', 1).'">'.$langs->trans('VoirReglesGenerales').'</a></div>';
}
print '</form><br>';

// Avances et prêts de l'employé
if ($user->hasRight('salaires', 'avance', 'lire')) {
	$candonner = $user->hasRight('salaires', 'avance', 'donner') && $object->estPresent();
	foreach (array('avance', 'pret') as $type) {
		$liste = salaires_avances_prets_employe($db, (int) $object->id, $type);
		$reste = 0;
		foreach ($liste as $a) {
			$reste += $a->reste;
		}
		$bouton = dolGetButtonTitle($langs->trans($type === 'pret' ? 'NouveauPret' : 'NouvelleAvance'), '', 'fa fa-plus-circle',
			dol_buildpath('/salaires/'.$type.'/card.php', 1).'?action=create&fk_employe='.((int) $object->id), '', $candonner);
		print load_fiche_titre($langs->trans($type === 'pret' ? 'PretsPersonnel' : 'AvancesSurSalaire').' ('.count($liste).')'.($reste > 0 ? ' — '.$langs->trans('ResteARetenir').' : '.salaires_montant($reste) : ''),
			$bouton, $type === 'pret' ? 'fa-piggy-bank' : 'fa-hand-holding-usd');
		if ($reste > 0 && !$object->estPresent()) {
			print info_admin($langs->trans('AlerteEmployePartiReste'), 0, 0, 'warning');
		}
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('Date').'</td><td class="right">'.$langs->trans('Montant').'</td>';
		print ($type === 'pret' ? '<td>'.$langs->trans('Mensualites').'</td>' : '').'<td class="right">'.$langs->trans('DejaRetenu').'</td><td class="right">'.$langs->trans('ResteARetenir').'</td><td class="right">'.$langs->trans('Etat').'</td></tr>';
		$o = ($type === 'pret') ? new EcoleSalairePret($db) : new EcoleSalaireAvance($db);
		foreach ($liste as $a) {
			$o->fetch((int) $a->rowid);
			print '<tr class="oddeven"><td class="nowraponall">'.$o->getNomUrl(1).'</td><td>'.dol_print_date($db->jdate($type === 'pret' ? $a->date_pret : $a->date_avance), 'day').'</td>';
			print '<td class="right nowraponall">'.salaires_montant($a->montant).'</td>';
			if ($type === 'pret') {
				print '<td class="nowraponall">'.((int) $a->nb_echeances).' × '.salaires_montant($a->montant_echeance).'</td>';
			}
			print '<td class="right nowraponall">'.salaires_montant($a->retenu).($a->brouillon > 0 ? '<br><span class="opacitymedium small" title="'.dol_escape_htmltag($langs->trans('PrevuBrouillon')).'">+ '.salaires_montant($a->brouillon).' '.$langs->trans('RetenuePrevue').'</span>' : '').'</td><td class="right nowraponall"><b>'.salaires_montant($a->reste).'</b></td><td class="right">'.$o->getLibStatut(5).'</td></tr>';
		}
		if (empty($liste)) {
			print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans($type === 'pret' ? 'AucunPret' : 'AucuneAvance').'</span></td></tr>';
		}
		print '</table></div><br>';
	}
}

// Bulletins de l'employé
$b = new EcoleSalaire($db);
$list = $b->fetchAllObjects('t.date_debut', 'DESC', 0, 0, array(), 't.fk_employe = '.((int) $object->id));
$bouton = dolGetButtonTitle($langs->trans('NouveauBulletin'), '', 'fa fa-plus-circle', dol_buildpath('/salaires/bulletin/card.php', 1).'?action=create&fk_employe='.((int) $object->id), '',
	$user->hasRight('salaires', 'bulletin', 'creer') && $object->estPresent());
print load_fiche_titre($langs->trans('BulletinsDePaie').' ('.(is_array($list) ? count($list) : 0).')', $bouton, 'fa-money-check-alt');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('NumeroBulletin').'</td><td>'.$langs->trans('MoisSalaire').'</td><td>'.$langs->trans('Periode').'</td><td class="right">'.$langs->trans('TotalGains').'</td><td class="right">'.$langs->trans('TotalRetenues').'</td>';
print '<td class="right">'.$langs->trans('NetAPayer').'</td><td>'.$langs->trans('Paiement').'</td><td class="right">'.$langs->trans('StatutBulletin').'</td></tr>';
$total = 0;
foreach (is_array($list) ? $list : array() as $rec) {
	$annule = ((int) $rec->status === EcoleSalaire::STATUS_ANNULE);
	if ((int) $rec->status === EcoleSalaire::STATUS_PAYE) {
		$total += $rec->net;
	}
	print '<tr class="oddeven"'.($annule ? ' style="opacity:.6"' : '').'><td class="nowraponall">'.$rec->getNomUrl(1).'</td>';
	print '<td><b>'.dol_escape_htmltag(personnel_mois_label((string) $rec->mois)).'</b></td><td>'.$langs->trans('PeriodeDuAu', dol_print_date($rec->date_debut, 'day'), dol_print_date($rec->date_fin, 'day')).'</td>';
	print '<td class="right nowraponall">'.salaires_montant($rec->total_gains).'</td><td class="right nowraponall">'.salaires_montant($rec->total_retenues).'</td>';
	print '<td class="right nowraponall"><b>'.salaires_montant($rec->net).'</b></td>';
	print '<td>'.((int) $rec->status === EcoleSalaire::STATUS_PAYE ? dol_print_date($rec->date_paiement, 'day').' — '.dol_escape_htmltag(salaires_mode_numero_texte($db, (int) $rec->fk_mode, (string) $rec->numero_compte)) : '').'</td>';
	print '<td class="right">'.$rec->getLibStatut(5).'</td></tr>';
}
if (empty($list)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunBulletin').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="5" class="right">'.$langs->trans('TotalPaye').'</td><td class="right nowraponall">'.salaires_montant($total).'</td><td colspan="2"></td></tr>';
}
print '</table></div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
