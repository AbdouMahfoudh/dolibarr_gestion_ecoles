<?php
/**
 * Caisse : rechercher un élève ou un responsable (nom, matricule, téléphone), puis encaisser pour
 * l'élève ou pour toute la fratrie en une fois (un seul reçu pour la famille).
 *
 * Fichier : custom/eleves/caisse.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/class/ecole_responsable.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'bills', 'other'));

if (!$user->hasRight('eleves', 'paiement', 'encaisser')) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$q = trim(GETPOST('q', 'alphanohtml'));
$fk_eleve = GETPOSTINT('fk_eleve');
$fk_responsable = GETPOSTINT('fk_responsable');
$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$p = $db->prefix();

// Élèves à encaisser : l'élève choisi, ou les enfants du responsable (sauf dossiers clos sans reste)
$eleves = array();
$resp = null;
if ($fk_eleve > 0 || $fk_responsable > 0) {
	$eleves = eleves_a_encaisser($db, $fk_eleve, $fk_responsable);
	if ($fk_responsable > 0) {
		$sits = eleves_situations($db, $eleves);
		foreach ($eleves as $k => $e) {
			if (in_array((int) $e->status, EcoleEleve::statusSortis(), true) && $sits[(int) $e->id]['reste'] <= 0) {
				unset($eleves[$k]);
			}
		}
	}
	$rid = $fk_responsable > 0 ? $fk_responsable : (!empty($eleves) ? (int) reset($eleves)->fk_responsable : 0);
	if ($rid > 0) {
		$resp = new EcoleResponsable($db);
		if ($resp->fetch($rid) <= 0) {
			$resp = null;
		}
	}
}

/*
 * Actions
 */
if ($action == 'encaisser' && !empty($eleves)) {
	$recuid = eleves_encaissement_process($db, $user, $eleves, $fk_responsable > 0 ? $fk_responsable : ($resp ? (int) $resp->id : 0));
	if ($recuid > 0) {
		setEventMessages($langs->trans('PaiementEnregistre'), null, 'mesgs');
		header('Location: '.dol_buildpath('/eleves/recu/card.php', 1).'?id='.$recuid);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('Caisse'), '', '', 0, 0, '', '', '', 'mod-eleves page-caisse');
print load_fiche_titre($langs->trans('Caisse'), '', 'fa-cash-register');

// Recherche
print '<form method="GET" action="'.dol_escape_htmltag($self).'">';
print '<div class="marginbottomonly">'.img_picto('', 'search', 'class="pictofixedwidth"');
print '<input type="text" name="q" class="flat minwidth400" value="'.dol_escape_htmltag($q).'" placeholder="'.dol_escape_htmltag($langs->trans('CaisseRecherche')).'" autofocus> ';
print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'"></div>';
print '</form>';

if ($q !== '' && empty($eleves)) {
	$like = $db->escape($db->escapeforlike($q));
	print '<div class="fichecenter"><div class="fichehalfleft">';
	// Responsables
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-user-friends', 'class="pictofixedwidth"').$langs->trans('Responsables').'</td></tr>';
	$sql = "SELECT r.rowid, r.ref, r.nom_fr, r.nom_ar, r.telephone, (SELECT COUNT(*) FROM ".$p."ecole_eleve e WHERE e.fk_responsable = r.rowid) as nb";
	$sql .= " FROM ".$p."ecole_responsable r WHERE r.entity IN (".getEntity('ecole_responsable').")";
	$sql .= " AND (r.nom_fr LIKE '%".$like."%' OR r.nom_ar LIKE '%".$like."%' OR r.telephone LIKE '%".$like."%' OR r.whatsapp LIKE '%".$like."%' OR r.ref LIKE '%".$like."%')";
	$sql .= " ORDER BY r.nom_fr LIMIT 30";
	$resql = $db->query($sql);
	$nb = 0;
	while ($resql && ($o = $db->fetch_object($resql))) {
		$nb++;
		print '<tr class="oddeven"><td><a href="'.$self.'?fk_responsable='.((int) $o->rowid).'">'.img_picto('', 'fa-user-friends', 'class="pictofixedwidth"').dol_escape_htmltag(ecole_label($o)).'</a></td>';
		print '<td>'.dol_escape_htmltag($o->telephone).'</td><td class="right">'.$langs->trans('NbEnfants', (int) $o->nb).'</td></tr>';
	}
	if (!$nb) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '</div><div class="fichehalfright">';
	// Élèves
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').$langs->trans('Eleves').'</td></tr>';
	$sql = "SELECT e.rowid, e.ref, e.nom_fr, e.nom_ar, e.fk_classe, e.fk_responsable FROM ".$p."ecole_eleve e WHERE e.entity IN (".getEntity('ecole_eleve').")";
	$sql .= " AND (e.nom_fr LIKE '%".$like."%' OR e.nom_ar LIKE '%".$like."%' OR e.ref LIKE '%".$like."%' OR e.rip LIKE '%".$like."%')";
	$sql .= " ORDER BY e.nom_fr LIMIT 30";
	$resql = $db->query($sql);
	$nb = 0;
	while ($resql && ($o = $db->fetch_object($resql))) {
		$nb++;
		print '<tr class="oddeven"><td><a href="'.$self.'?fk_eleve='.((int) $o->rowid).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref.' - '.ecole_label($o)).'</a></td>';
		print '<td>'.ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $o->fk_classe).'</td>';
		print '<td class="right"><a href="'.$self.'?fk_responsable='.((int) $o->fk_responsable).'">'.$langs->trans('TouteLaFamille').'</a></td></tr>';
	}
	if (!$nb) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '</div></div><div class="clearboth"></div>';
}

if (!empty($eleves)) {
	// Payeur
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-user-friends', 'class="pictofixedwidth"').$langs->trans('Payeur').'</td></tr>';
	if ($resp) {
		print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('Responsable').'</td><td>'.$resp->getNomUrl(1, 'label').' — '.dol_print_phone($resp->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone');
		if ($fk_eleve > 0) {
			print ' &nbsp; <a href="'.$self.'?fk_responsable='.((int) $resp->id).'">'.img_picto('', 'fa-users', 'class="pictofixedwidth"').$langs->trans('EncaisserToutLaFamille').'</a>';
		}
		print '</td></tr>';
	}
	print '</table><br>';
	$hidden = $fk_responsable > 0 ? array('fk_responsable' => $fk_responsable) : array('fk_eleve' => $fk_eleve);
	eleves_encaissement_form($eleves, $self, $hidden);
} elseif ($fk_eleve > 0 || $fk_responsable > 0) {
	print info_admin($langs->trans('AucunEleveAEncaisser'), 0, 0, 'warning');
} elseif ($q === '') {
	print '<span class="opacitymedium">'.$langs->trans('CaisseAide').'</span>';
}

llxFooter();
$db->close();
