<?php
/**
 * Fiche d'un bulletin de paie.
 *  - création : employé + période (début proposé = lendemain de son dernier bulletin) ;
 *  - brouillon : recalcul depuis la présence, montants modifiables, lignes ajoutées à la main, notes ;
 *  - validation, réouverture (motif), annulation (motif), paiement (mode, date, référence), annulation du paiement ;
 *  - résumé de la présence, heures supplémentaires semaine par semaine, séances, fiche de paie PDF, historique.
 *
 * Fichier : custom/salaires/bulletin/card.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'banks', 'bills', 'other'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$lineid = GETPOSTINT('lineid');

if (!$user->hasRight('salaires', 'bulletin', 'lire')) {
	accessforbidden();
}
$cancreer = $user->hasRight('salaires', 'bulletin', 'creer');
$canvalider = $user->hasRight('salaires', 'bulletin', 'valider');
$canrouvrir = $user->hasRight('salaires', 'bulletin', 'rouvrir');
$canannuler = $user->hasRight('salaires', 'bulletin', 'annuler');
$canexport = $user->hasRight('salaires', 'bulletin', 'exporter');
$canpayer = $user->hasRight('salaires', 'paiement', 'payer');
$canannulerpaie = $user->hasRight('salaires', 'paiement', 'annuler');

$form = new Form($db);
$object = new EcoleSalaire($db);
if ($id > 0 || $ref !== '') {
	if ($object->fetch($id, $ref !== '' ? $ref : null) <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
$brouillon = ($object->id > 0 && (int) $object->status === EcoleSalaire::STATUS_BROUILLON);

/*
 * Actions
 */
$redirect = function () use ($self) {
	header('Location: '.$self);
	exit;
};

if ($action == 'add' && GETPOST('cancel', 'alpha')) {
	header('Location: '.dol_buildpath('/salaires/bulletin/list.php', 1));
	exit;
}
if ($action == 'add' && $cancreer) {
	$d1 = personnel_date_ok(GETPOST('date_debut', 'alphanohtml'));
	$d2 = personnel_date_ok(GETPOST('date_fin', 'alphanohtml'));
	$res = $object->creer($user, GETPOSTINT('fk_employe'), $d1, $d2, 0, personnel_mois_ok(GETPOST('mois', 'alphanohtml')));
	if ($res > 0) {
		setEventMessages($langs->trans('BulletinCree', $object->ref), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$res);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'create';
}
if ($object->id > 0) {
	if ($action == 'recalculer' && $cancreer && $brouillon) {
		if ($object->calculer($user) > 0) {
			setEventMessages($langs->trans('BulletinRecalcule'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'addline' && $cancreer && $brouillon) {
		if ($object->ajouterLigne($user, GETPOST('type', 'aZ09'), GETPOST('libelle', 'alphanohtml'), GETPOST('montant', 'alphanohtml')) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'updateline' && $cancreer && $brouillon && !GETPOST('cancel', 'alpha')) {
		if ($object->modifierLigne($user, $lineid, GETPOST('montant', 'alphanohtml'), GETPOST('libelle', 'alphanohtml')) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'delline' && $cancreer && $brouillon) {
		if ($object->supprimerLigne($user, $lineid) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'setnotes' && $cancreer && $brouillon) {
		$object->note_public = GETPOST('note_public', 'restricthtml');
		$object->note_private = GETPOST('note_private', 'restricthtml');
		if ($object->updateCommon($user) > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'confirm_valider' && $confirm === 'yes' && $canvalider) {
		if ($object->valider($user) > 0) {
			setEventMessages($langs->trans('BulletinValideOk', $object->ref), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'confirm_rouvrir' && $confirm === 'yes' && $canrouvrir) {
		if ($object->rouvrir($user, GETPOST('motif', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('BulletinRouvertOk', $object->ref), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'confirm_annuler' && $confirm === 'yes' && $canannuler) {
		if ($object->annuler($user, GETPOST('motif', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('BulletinAnnuleOk', $object->ref), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'confirm_payer' && $confirm === 'yes' && $canpayer) {
		$datep = dol_mktime(12, 0, 0, GETPOSTINT('datepmonth'), GETPOSTINT('datepday'), GETPOSTINT('datepyear'));
		if ($object->payer($user, $datep, GETPOSTINT('fk_mode'), GETPOST('reference', 'alphanohtml'), GETPOST('numero_compte', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('BulletinPayeOk', $object->ref), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
	if ($action == 'confirm_annulerpaiement' && $confirm === 'yes' && $canannulerpaie) {
		if ($object->annulerPaiement($user, GETPOST('motif', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('PaiementAnnuleOk', $object->ref), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$redirect();
	}
}

/*
 * Affichage
 */
$title = $object->id > 0 ? $object->ref.' - '.$langs->trans('BulletinDePaie') : $langs->trans('NouveauBulletin');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-salaires page-bulletin');

// Création
if ($action == 'create' || $object->id <= 0) {
	if (!$cancreer) {
		accessforbidden();
	}
	$fk_employe = GETPOSTINT('fk_employe');
	$d1 = personnel_date_ok(GETPOST('date_debut', 'alphanohtml'));
	$d2 = personnel_date_ok(GETPOST('date_fin', 'alphanohtml'));
	if ($d1 === '') {
		list($d1, $d2def) = salaires_periode_defaut();
		$prec = $fk_employe > 0 ? EcoleSalaire::precedent($db, $fk_employe) : null;
		if ($prec) {
			// Lendemain du dernier bulletin, jusqu'à la fin de ce mois-là (jamais après aujourd'hui)
			$d1 = date('Y-m-d', strtotime(substr($prec->date_fin, 0, 10).' 12:00:00 +1 day'));
			$d2def = min(date('Y-m-t', strtotime($d1.' 12:00:00')), personnel_aujourdhui());
		}
		if ($d2 === '') {
			$d2 = $d2def;
		}
	} elseif ($d2 === '') {
		$d2 = min(date('Y-m-t', strtotime($d1.' 12:00:00')), personnel_aujourdhui());
	}
	$mois = personnel_mois_ok(GETPOST('mois', 'alphanohtml'));
	$mois = $mois !== '' ? $mois : EcoleSalaire::moisPropose($d2);
	$auj = personnel_aujourdhui();
	print load_fiche_titre($langs->trans('NouveauBulletin'), '', $object->picto);
	if ($d1 > $auj) {
		// Le salaire suivant de cet employé ne peut pas encore être fait (sa période commencerait après aujourd'hui)
		print info_admin($langs->trans('AlerteProchainePeriode', dol_print_date(personnel_date_ts($d1), 'day')), 0, 0, 'warning');
	}
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Employe').'</td><td>';
	print $form->selectarray('fk_employe', personnel_employes_choix($db, '', $fk_employe), $fk_employe, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1);
	print '</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('PeriodeDu').'</td><td><input type="date" name="date_debut" class="flat" max="'.$auj.'" value="'.dol_escape_htmltag($d1).'" required></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('PeriodeAu').'</td><td><input type="date" name="date_fin" id="date_fin" class="flat" max="'.$auj.'" value="'.dol_escape_htmltag($d2).'" required>';
	print ' <span class="opacitymedium">'.$langs->trans('PeriodeLibreAide').'</span></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('MoisSalaire').'</td><td><input type="month" name="mois" id="mois" class="flat" max="'.substr($auj, 0, 7).'" value="'.dol_escape_htmltag($mois).'" required>';
	print ' <span class="opacitymedium">'.$langs->trans('MoisSalaireAide').'</span></td></tr>';
	// Le mois suit la fin de la période tant qu'il n'a pas été choisi à la main
	print '<script>$(function(){ var libre = false; $("#mois").on("change", function(){ libre = true; }); $("#date_fin").on("change", function(){ if (!libre && this.value) { $("#mois").val(this.value.substr(0, 7)); } }); });</script>';
	print '</table>';
	print dol_get_fiche_end();
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('CreationBulletinAide').'</div>';
	print $form->buttonsSaveCancel('CreerEtCalculer');
	print '</form>';
	llxFooter();
	$db->close();
	exit;
}

// Confirmations
if ($action == 'valider' && $canvalider) {
	print $form->formconfirm($self, $langs->trans('ValiderBulletin'), $langs->trans('ConfirmValiderBulletin', $object->ref, salaires_montant_texte($object->net)), 'confirm_valider', '', 'yes', 1);
}
if ($action == 'rouvrir' && $canrouvrir) {
	print $form->formconfirm($self, $langs->trans('RouvrirBulletin'), $langs->trans('ConfirmRouvrirBulletin', $object->ref), 'confirm_rouvrir',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Motif'), 'morecss' => 'minwidth300')), 'no', 1, 0, 550);
}
if ($action == 'annuler' && $canannuler) {
	print $form->formconfirm($self, $langs->trans('AnnulerBulletin'), $langs->trans('ConfirmAnnulerBulletin', $object->ref), 'confirm_annuler',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Motif'), 'morecss' => 'minwidth300')), 'no', 1, 0, 550);
}
if ($action == 'payer' && $canpayer) {
	$modes = salaires_modes_paiement($db);
	$defmode = getDolGlobalInt('SALAIRES_MODE_DEFAUT');
	if (!isset($modes[$defmode])) {
		$defmode = 0;
		foreach (array_keys($modes) as $mid) {
			if (salaires_compte_mode($mid) > 0) {
				$defmode = $mid;
				break;
			}
		}
	}
	$empPaie = new EcoleEmploye($db);
	$empPaie->fetch((int) $object->fk_employe);
	print $form->formconfirm($self, $langs->trans('PayerSalaire'), $langs->trans('ConfirmPayerSalaire', $object->ref, salaires_montant_texte($object->net)), 'confirm_payer',
		array(
			array('type' => 'date', 'name' => 'datep', 'label' => $langs->trans('DatePaiementSalaire'), 'value' => dol_now()),
			array('type' => 'select', 'name' => 'fk_mode', 'label' => $langs->trans('ModePaiement'), 'values' => $modes, 'default' => $defmode),
			array('type' => 'text', 'name' => 'numero_compte', 'label' => $langs->trans('NumeroCompte').' <span class="opacitymedium small">('.$langs->trans('NumeroCompteAide').')</span>', 'value' => (string) $empPaie->mobile_money, 'morecss' => 'minwidth200'),
			array('type' => 'text', 'name' => 'reference', 'label' => $langs->trans('ReferencePaiement'), 'morecss' => 'minwidth200'),
		), 'yes', 1, 0, 650);
	print salaires_js_numero($db, '#fk_mode', '#numero_compte');
}
if ($action == 'annulerpaiement' && $canannulerpaie) {
	print $form->formconfirm($self, $langs->trans('AnnulerPaiementSalaire'), $langs->trans('ConfirmAnnulerPaiementSalaire', $object->ref), 'confirm_annulerpaiement',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Motif'), 'morecss' => 'minwidth300')), 'no', 1, 0, 550);
}

$emp = new EcoleEmploye($db);
$emp->fetch((int) $object->fk_employe);
$calc = $object->getCalcul();

$head = array(array($self, $langs->trans('BulletinDePaie'), 'card'));
print dol_get_fiche_head($head, 'card', $langs->trans('BulletinDePaie'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/salaires/bulletin/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">'.salaires_employe_lien($db, (int) $object->fk_employe);
$morehtmlref .= '<br><b>'.$langs->trans('SalaireDuMois', dol_escape_htmltag(personnel_mois_label((string) $object->mois))).'</b> — '.$langs->trans('PeriodeDuAu', dol_print_date($object->date_debut, 'day'), dol_print_date($object->date_fin, 'day'));
$morehtmlref .= ' — <b>'.$langs->trans('NetAPayer').' : '.salaires_montant($object->net).'</b></div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', '');

print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
$modes = array('fixe' => $langs->trans('ModeFixe'), 'heure' => $langs->trans('ModeHeure'));
print '<tr><td class="titlefield">'.$langs->trans('ModeDePaie').'</td><td>'.(isset($modes[$object->mode_paie]) ? $modes[$object->mode_paie] : '<span class="opacitymedium">'.$langs->trans('PaieNonRenseignee').'</span>').'</td></tr>';
if ($object->mode_paie === 'fixe') {
	print '<tr><td>'.$langs->trans('SalaireMensuel').'</td><td>'.salaires_montant($object->salaire_base).'</td></tr>';
	if ((float) $object->heures_semaine > 0) {
		print '<tr><td>'.$langs->trans('HeuresPrevuesSemaine').'</td><td>'.price2num((float) $object->heures_semaine).' h';
		print ' — '.$langs->trans('PrixHeureSup').' : '.((float) $object->taux_heure_sup > 0 ? salaires_montant($object->taux_heure_sup) : ((float) $object->taux_horaire > 0 ? salaires_montant($object->taux_horaire) : '<span class="opacitymedium">—</span>')).'</td></tr>';
	}
} elseif ($object->mode_paie === 'heure') {
	print '<tr><td>'.$langs->trans('PrixHeure').'</td><td>'.salaires_montant($object->taux_horaire).'</td></tr>';
}
if (!empty($calc['jours_periode'])) {
	print '<tr><td>'.$langs->trans('JoursPeriode').'</td><td>'.((int) $calc['jours_periode']).($object->mode_paie === 'fixe' ? ' <span class="opacitymedium">('.$langs->trans('FractionMois', price2num($calc['fraction_mois'], 2)).')</span>' : '').'</td></tr>';
}
if ((int) $object->fk_lot > 0) {
	print '<tr><td>'.$langs->trans('LotSalaires').'</td><td>'.salaires_lot_lien($db, (int) $object->fk_lot).'</td></tr>';
}
print '</table></div>';

print '<div class="fichehalfright"><div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('CreePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_creat)).' <span class="opacitymedium">'.dol_print_date($object->date_creation, 'dayhour').'</span></td></tr>';
if ($object->date_calcul) {
	print '<tr><td>'.$langs->trans('DateCalcul').'</td><td>'.dol_print_date($object->date_calcul, 'dayhour').'</td></tr>';
}
if ($object->date_validation) {
	print '<tr><td>'.$langs->trans('ValidePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_valid)).' <span class="opacitymedium">'.dol_print_date($object->date_validation, 'dayhour').'</span></td></tr>';
}
if ((int) $object->status === EcoleSalaire::STATUS_PAYE) {
	print '<tr><td>'.$langs->trans('Paiement').'</td><td>'.dol_print_date($object->date_paiement, 'day').' — '.dol_escape_htmltag(salaires_mode_numero_texte($db, (int) $object->fk_mode, (string) $object->numero_compte));
	print ($object->reference_paiement ? ' ('.dol_escape_htmltag($object->reference_paiement).')' : '').' <span class="opacitymedium">'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_paie)).'</span></td></tr>';
	if ((int) $object->fk_bank_account > 0 && $user->hasRight('banque', 'lire')) {
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$acc = new Account($db);
		if ($acc->fetch((int) $object->fk_bank_account) > 0) {
			print '<tr><td>'.$langs->trans('CompteTresorerie').'</td><td>'.$acc->getNomUrl(1).'</td></tr>';
		}
	}
	if ((int) $object->fk_salary > 0 && $user->hasRight('salaries', 'read')) {
		print '<tr><td>'.$langs->trans('SalaireDolibarr').'</td><td><a href="'.DOL_URL_ROOT.'/salaries/card.php?id='.((int) $object->fk_salary).'">'.img_picto('', 'salary', 'class="pictofixedwidth"').$langs->trans('VoirComptabilite').'</a></td></tr>';
	}
}
if ((int) $object->status === EcoleSalaire::STATUS_ANNULE) {
	print '<tr><td class="error">'.$langs->trans('BulletinAnnule').'</td><td>'.dol_escape_htmltag((string) $object->motif_annulation).' <span class="opacitymedium">— '.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_annulation)).' '.dol_print_date($object->date_annulation, 'dayhour').'</span></td></tr>';
}
print '</table></div></div><div class="clearboth"></div>';
print dol_get_fiche_end();

// Avertissements
$alertes = array();
if (!empty($calc['alertes'])) {
	foreach ($calc['alertes'] as $a) {
		$alertes[] = personnel_paie_texte($langs, $a[0], $a[1]);
	}
}
if ($brouillon || (int) $object->status === EcoleSalaire::STATUS_VALIDE) {
	if (!empty($calc['avenir']) || $object->au() > personnel_aujourdhui()) {
		$alertes[] = $langs->trans('AlertePeriodeNonTerminee');
	}
	$prec = EcoleSalaire::precedent($db, (int) $object->fk_employe, $object->du(), (int) $object->id);
	if ($prec && date('Y-m-d', strtotime(substr($prec->date_fin, 0, 10).' 12:00:00 +1 day')) < $object->du()) {
		$alertes[] = $langs->trans('AlerteTrouPeriode', $prec->ref, dol_print_date($db->jdate($prec->date_fin), 'day'), dol_print_date($object->date_debut, 'day'));
	}
	if ($brouillon && $emp->id > 0) {
		foreach (EcoleEmploye::CHAMPS_PAIE as $c) {
			if ((string) price2num((float) $emp->$c) !== (string) price2num((float) $object->$c) || ($c === 'mode_paie' && (string) $emp->mode_paie !== (string) $object->mode_paie)) {
				$alertes[] = $langs->trans('AlerteFicheModifiee');
				break;
			}
		}
	}
}
if ($alertes) {
	print info_admin(implode('<br>', array_map('dol_escape_htmltag', array_unique($alertes))), 0, 0, 'warning');
}

// Lignes du bulletin
print load_fiche_titre($langs->trans('CalculDuSalaire'), '', 'fa-calculator');
print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Libelle').'</td><td>'.$langs->trans('DetailCalcul').'</td><td class="right">'.$langs->trans('Gain').'</td><td class="right">'.$langs->trans('Retenue').'</td>';
print $brouillon && $cancreer ? '<td class="center width75"></td>' : '';
print '</tr>';
$lignes = $object->getLignes();
foreach ($lignes as $l) {
	$edit = ($action == 'editline' && $lineid === (int) $l->rowid && $brouillon && $cancreer);
	print '<tr class="oddeven">';
	if ($edit && !(int) $l->auto) {
		print '<td><input type="text" name="libelle" class="flat minwidth300" value="'.dol_escape_htmltag($l->libelle).'"></td>';
	} else {
		print '<td>'.dol_escape_htmltag(salaires_ligne_libelle($l, $langs));
		if (!(int) $l->auto) {
			print ' <span class="badge badge-secondary small">'.$langs->trans('AjouteeMain').'</span>';
		} elseif ((int) $l->modifie) {
			print ' <span class="badge badge-warning small" title="'.dol_escape_htmltag($langs->trans('MontantCalcule').' : '.salaires_montant_texte($l->montant_calcule)).'">'.$langs->trans('ModifieMain').'</span>';
		}
		print '</td>';
	}
	print '<td class="opacitymedium">'.dol_escape_htmltag(salaires_ligne_detail($l, $langs));
	if (in_array($l->source_type, array('avance', 'pret'), true) && (int) $l->fk_source > 0) {
		print ' <a href="'.dol_buildpath('/salaires/'.$l->source_type.'/card.php', 1).'?id='.((int) $l->fk_source).'" title="'.dol_escape_htmltag($langs->trans('VoirDetail')).'">'.img_picto('', $l->source_type === 'pret' ? 'fa-piggy-bank' : 'fa-hand-holding-usd').'</a>';
	}
	print '</td>';
	foreach (array('gain', 'retenue') as $type) {
		print '<td class="right nowraponall">';
		if ($l->type === $type) {
			if ($edit) {
				print '<input type="hidden" name="action" value="updateline"><input type="hidden" name="lineid" value="'.((int) $l->rowid).'">';
				print '<input type="text" name="montant" class="flat maxwidth100 right" value="'.dol_escape_htmltag(price2num($l->montant)).'" autofocus>';
			} else {
				print ($type === 'retenue' ? '- ' : '').salaires_montant($l->montant);
			}
		}
		print '</td>';
	}
	if ($brouillon && $cancreer) {
		print '<td class="center nowraponall">';
		if ($edit) {
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'"> ';
			print '<input type="submit" class="button button-cancel smallpaddingimp" name="cancel" value="'.dol_escape_htmltag($langs->trans('Cancel')).'">';
		} else {
			print '<a class="editfielda marginrightonly" href="'.$self.'&action=editline&lineid='.((int) $l->rowid).'&token='.newToken().'#ligne" title="'.dol_escape_htmltag($langs->trans('Modify')).'">'.img_edit().'</a>';
			if (!(int) $l->auto) {
				print '<a href="'.$self.'&action=delline&lineid='.((int) $l->rowid).'&token='.newToken().'" title="'.dol_escape_htmltag($langs->trans('Delete')).'">'.img_delete().'</a>';
			} elseif ((int) $l->modifie) {
				print '<a href="'.$self.'&action=delline&lineid='.((int) $l->rowid).'&token='.newToken().'" title="'.dol_escape_htmltag($langs->trans('RetablirCalcul')).'">'.img_picto($langs->trans('RetablirCalcul'), 'fa-undo').'</a>';
			}
		}
		print '</td>';
	}
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('AucuneLigne').'</span></td></tr>';
}
print '<tr class="liste_total"><td colspan="2" class="right">'.$langs->trans('Total').'</td><td class="right nowraponall">'.salaires_montant($object->total_gains).'</td><td class="right nowraponall">- '.salaires_montant($object->total_retenues).'</td>'.($brouillon && $cancreer ? '<td></td>' : '').'</tr>';
print '<tr class="liste_total"><td colspan="3" class="right"><b>'.$langs->trans('NetAPayer').'</b></td><td class="right nowraponall"><b>'.salaires_montant($object->net).'</b></td>'.($brouillon && $cancreer ? '<td></td>' : '').'</tr>';
print '</table></div>';
print '</form>';

// Ajout d'une ligne à la main
if ($brouillon && $cancreer && $action != 'editline') {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="margintoponly">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addline">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="4">'.img_picto('', 'fa-plus-circle', 'class="pictofixedwidth"').$langs->trans('AjouterLigne').' <span class="opacitymedium small">— '.$langs->trans('AjouterLigneAide').'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$form->selectarray('type', array('gain' => $langs->trans('Gain'), 'retenue' => $langs->trans('Retenue')), GETPOST('type', 'aZ09') ? GETPOST('type', 'aZ09') : 'gain', 0).'</td>';
	print '<td><input type="text" name="libelle" class="flat minwidth300" placeholder="'.dol_escape_htmltag($langs->trans('LibelleLigneExemple')).'"></td>';
	print '<td><input type="text" name="montant" class="flat maxwidth100 right" placeholder="0"></td>';
	print '<td class="right"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td></tr>';
	print '</table></form>';
}

// Actions
print '<div class="tabsAction">';
if ($brouillon) {
	print dolGetButtonAction('', $langs->trans('Recalculer'), 'default', $self.'&action=recalculer&token='.newToken(), '', $cancreer);
	print dolGetButtonAction('', $langs->trans('Valider'), 'default', $self.'&action=valider&token='.newToken(), '', $canvalider);
}
if ((int) $object->status === EcoleSalaire::STATUS_VALIDE) {
	print dolGetButtonAction('', $langs->trans('PayerSalaire'), 'default', $self.'&action=payer&token='.newToken(), '', $canpayer);
	print dolGetButtonAction('', $langs->trans('RouvrirBulletin'), 'default', $self.'&action=rouvrir&token='.newToken(), '', $canrouvrir);
}
if ((int) $object->status === EcoleSalaire::STATUS_PAYE) {
	print ecole_bouton_supprimer($langs->trans('AnnulerPaiementSalaire'), $self.'&action=annulerpaiement&token='.newToken(), $canannulerpaie, $object->getCancelPaymentBlockers());
}
print dolGetButtonAction('', $langs->trans('FicheDePaiePdf'), 'default', dol_buildpath('/salaires/bulletin/pdf.php', 1).'?id='.((int) $object->id), '', $canexport, array('attr' => array('target' => '_blank')));
if ($canexport) {
	print ecole_pdf_modele_choix_html('paie', dol_buildpath('/salaires/bulletin/pdf.php', 1).'?id='.((int) $object->id));
}
if (in_array((int) $object->status, array(EcoleSalaire::STATUS_BROUILLON, EcoleSalaire::STATUS_VALIDE), true)) {
	print ecole_bouton_supprimer($langs->trans('AnnulerBulletin'), $self.'&action=annuler&token='.newToken(), $canannuler);
} elseif ((int) $object->status === EcoleSalaire::STATUS_PAYE) {
	print ecole_bouton_supprimer($langs->trans('AnnulerBulletin'), $self.'&action=annuler&token='.newToken(), $canannuler, array($langs->trans('ErrorAnnulerPaiementDabord')));
}
print '</div>';

// Présence de la période
$enseignant = ((int) $object->minutes_prevues > 0 || (int) $object->nb_remplacements > 0);
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-chart-bar', 'class="pictofixedwidth"').$langs->trans('PresencePeriode').'</td></tr>';
if ($enseignant) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('CoursPrevus').'</td><td>'.personnel_duree($object->minutes_prevues).(!empty($calc['nb_prevues']) ? ' <span class="opacitymedium">('.$langs->trans('NbSeances', $calc['nb_prevues']).')</span>' : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('CoursFaits').'</td><td><b>'.personnel_duree($object->minutes_faites).'</b>'.($object->minutes_payees != $object->minutes_faites ? ' <span class="opacitymedium">('.$langs->trans('HeuresPayees').' : '.personnel_duree($object->minutes_payees).')</span>' : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Absences').'</td><td>'.$langs->trans('AbsJustNonJust', personnel_duree($object->minutes_absent_j), personnel_duree($object->minutes_absent_nj)).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Retards').'</td><td>'.(!empty($calc['nb_retards']) ? $calc['nb_retards'].' <span class="opacitymedium">'.$langs->trans('NbMinutes', $object->minutes_retard).'</span>' : '0').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RemplacementsFaits').'</td><td>'.($object->nb_remplacements ? $object->nb_remplacements.' <span class="opacitymedium">('.personnel_duree($object->minutes_rempl).')</span>' : '0').'</td></tr>';
	if ($object->mode_paie === 'fixe' && (float) $object->heures_semaine > 0) {
		print '<tr class="oddeven"><td>'.$langs->trans('HeuresSup').'</td><td>'.personnel_duree($object->minutes_hsup).'</td></tr>';
	}
}
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('JoursOuvrables').'</td><td>'.((int) $object->jours_ouvrables).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('JoursAbsence').'</td><td>'.$langs->trans('AbsJustNonJust', price2num((float) $object->jours_absent_j), price2num((float) $object->jours_absent_nj)).'</td></tr>';
print '</table>';
print '</div><div class="fichehalfright">';
// Règles appliquées et heures supplémentaires semaine par semaine
if (!empty($calc['regles'])) {
	$r = $calc['regles'];
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-balance-scale', 'class="pictofixedwidth"').$langs->trans('ReglesAppliquees').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('RegleRetenue').'</td><td>'.$langs->trans('PERSONNEL_RETENUE_ABSENCE_'.$r['retenue']).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RegleRetard').'</td><td>'.$langs->trans('PERSONNEL_RETARD_EFFET_'.$r['retard']).($r['retard'] === 'seuil' ? ' '.$r['retard_seuil'].' '.$langs->trans('Minutes') : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RegleHeuresSup').'</td><td>'.$langs->trans('PERSONNEL_HEURES_SUP_'.(!empty($r['hsup']) ? 'oui' : 'non')).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RegleRemplacement').'</td><td>'.$langs->trans('PERSONNEL_REMPLACEMENT_PAIE_'.$r['remplacement']).'</td></tr>';
	print '</table>';
}
if (!empty($calc['hsup_semaines'])) {
	print '<br><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Semaine').'</td><td class="right">'.$langs->trans('HeuresFaites').'</td><td class="right">'.$langs->trans('VolumeSemaine').'</td><td class="right">'.$langs->trans('HeuresSup').'</td></tr>';
	foreach ($calc['hsup_semaines'] as $w => $s) {
		$p = explode('-', $w);
		$lundi = (new DateTime())->setISODate((int) $p[0], (int) $p[1])->format('Y-m-d');
		print '<tr class="oddeven"><td>'.$langs->trans('SemaineDu', dol_print_date(personnel_date_ts($lundi), 'day')).'</td><td class="right">'.personnel_duree($s['faites']).'</td><td class="right">'.personnel_duree($s['volume']).'</td><td class="right"><b>'.personnel_duree($s['sup']).'</b></td></tr>';
	}
	print '<tr><td colspan="4"><span class="opacitymedium small">'.$langs->trans('VolumeSemaineAide').'</span></td></tr>';
	print '</table>';
}
print '</div></div><div class="clearboth"></div><br>';

// Séances
$seances = $object->getSeances();
if ($seances) {
	print load_fiche_titre($langs->trans('SeancesPeriode').' ('.count($seances).')', '', 'fa-chalkboard-teacher');
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Creneau').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td><td>'.$langs->trans('Presence').'</td><td class="right">'.$langs->trans('Duree').'</td><td class="right">'.$langs->trans('HeuresPayees').'</td></tr>';
	$jourPrec = '';
	foreach ($seances as $s) {
		$d = substr($s->date_seance, 0, 10);
		print '<tr class="oddeven'.($d !== $jourPrec && $jourPrec !== '' ? ' trforbreak' : '').'">';
		print '<td class="nowraponall">'.($d !== $jourPrec ? dol_escape_htmltag(personnel_date_label($d)) : '').'</td>';
		$jourPrec = $d;
		print '<td class="nowraponall">'.dol_escape_htmltag($s->creneau).' <span class="opacitymedium small">'.$s->heure_debut.'-'.$s->heure_fin.'</span></td>';
		print '<td>'.dol_escape_htmltag($s->classe).'</td><td>'.dol_escape_htmltag($s->matiere).'</td>';
		print '<td class="nowraponall">'.personnel_etat_badge($s->etat, $s->etat === 'retard' ? '('.$langs->trans('NbMinutes', $s->retard).')' : '');
		if ($s->etat === 'absent') {
			print ' '.((int) $s->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="opacitymedium small">'.$langs->trans('NonJustifiee').'</span>');
		}
		print '</td><td class="right">'.personnel_duree($s->minutes).'</td><td class="right">'.($s->etat === 'absent' ? '' : personnel_duree($s->minutes_payees)).'</td></tr>';
	}
	print '</table></div><br>';
}

// Notes
print load_fiche_titre($langs->trans('Notes'), '', 'fa-sticky-note');
if ($brouillon && $cancreer) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setnotes">';
}
print '<table class="border centpercent tableforfield">';
foreach (array('note_public' => 'NotePublicBulletin', 'note_private' => 'NotePrivateBulletin') as $k => $lab) {
	print '<tr><td class="titlefield">'.$langs->trans($lab).'<br><span class="opacitymedium small">'.$langs->trans($lab.'Aide').'</span></td><td>';
	if ($brouillon && $cancreer) {
		print '<textarea name="'.$k.'" class="flat centpercent" rows="2">'.dol_escape_htmltag((string) $object->$k).'</textarea>';
	} else {
		print dol_nl2br(dol_escape_htmltag((string) $object->$k));
	}
	print '</td></tr>';
}
print '</table>';
if ($brouillon && $cancreer) {
	print '<div class="center margintoponly"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div></form>';
}
print '<br>';

// Historique
print load_fiche_titre($langs->trans('Historique'), '', 'fa-history');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Action').'</td><td>'.$langs->trans('Detail').'</td><td>'.$langs->trans('Motif').'</td><td>'.$langs->trans('Par').'</td></tr>';
foreach ($object->getLogs() as $lg) {
	print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($lg->date_creation), 'dayhour').'</td><td>'.dol_escape_htmltag($langs->trans('LogSalaire_'.$lg->action)).'</td>';
	print '<td>'.dol_escape_htmltag((string) $lg->detail).'</td><td>'.dol_escape_htmltag((string) $lg->motif).'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $lg->fk_user)).'</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
