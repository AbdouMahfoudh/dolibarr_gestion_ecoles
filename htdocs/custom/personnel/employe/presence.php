<?php
/**
 * Onglet « Présence et heures » d'un employé, mois par mois :
 *  - enseignant : chaque cours de son emploi du temps (fait par défaut, déclaré, absent, en retard, remplacé),
 *    remplacements qu'il a faits, totaux (prévu, fait, absences, retards, heures supplémentaires) ;
 *  - tous : absences du personnel du mois ;
 *  - estimation de la paie du mois (droit « Paie : voir ») selon les règles de la configuration.
 * Exports PDF / Excel du mois.
 *
 * Fichier : custom/personnel/employe/presence.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
$canpres = $user->hasRight('personnel', 'presence', 'lire');
$canabs = $user->hasRight('personnel', 'absence', 'lire');
if (!$user->hasRight('personnel', 'employe', 'lire') || (!$canpres && !$canabs)) {
	accessforbidden();
}
$object = new EcoleEmploye($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$mois = personnel_mois_ok(GETPOST('mois', 'alpha'));
if ($mois === '') {
	$mois = dol_print_date(dol_now(), '%Y-%m', 'tzuserrel');
}
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
$h = personnel_heures_mois($db, $object, $mois);
$t = $h['totaux'];
$enseignant = !empty($h['seances']) || $object->aCategorie('enseignant');

llxHeader('', $object->ref.' - '.$langs->trans('PresenceHeures'), '', '', 0, 0, '', '', '', 'mod-personnel page-presence');

employe_print_banner($object, 'presence');
print '<div class="underbanner clearboth"></div>';

// Choix du mois
$prec = date('Y-m', strtotime($mois.'-01 -1 month'));
$suiv = date('Y-m', strtotime($mois.'-01 +1 month'));
$boutons = '';
if ($user->hasRight('personnel', 'employe', 'exporter')) {
	$boutons = ecole_export_buttons('presence_employe', '&id='.((int) $object->id).'&mois='.$mois, 'personnel');
}
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="marginbottomonly">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'&mois='.$prec).'">&lsaquo;</a> ';
print '<input type="month" name="mois" class="flat" value="'.dol_escape_htmltag($mois).'" onchange="this.form.submit()"> ';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'&mois='.$suiv).'">&rsaquo;</a> ';
print '<b class="marginleftonly">'.dol_escape_htmltag(personnel_mois_label($mois)).'</b>';
print '<span class="floatright">'.$boutons.'</span>';
print '</form>';

// Résumé du mois
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-chart-bar', 'class="pictofixedwidth"').$langs->trans('ResumeMois').'</td></tr>';
if ($enseignant && $canpres) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('CoursPrevus').'</td><td>'.personnel_duree($t['prevues']).' <span class="opacitymedium">('.$langs->trans('NbSeances', $t['nb_prevues']).')</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('CoursFaits').'</td><td><b>'.personnel_duree($t['faites']).'</b> <span class="opacitymedium">('.$langs->trans('NbSeances', $t['nb_faites']).', '.$langs->trans('NbDeclares', $t['nb_declares']).')</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Absences').'</td><td>'.($t['nb_absences'] ? '<span class="badge badge-danger">'.$t['nb_absences'].'</span> ' : '0 ')
		.'<span class="opacitymedium">'.$langs->trans('AbsJustNonJust', personnel_duree($t['absent_j']), personnel_duree($t['absent_nj'])).'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Retards').'</td><td>'.($t['nb_retards'] ? '<span class="badge badge-warning">'.$t['nb_retards'].'</span> <span class="opacitymedium">'.$langs->trans('NbMinutes', $t['retard_min']).'</span>' : '0').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RemplacementsFaits').'</td><td>'.($t['nb_remplacements'] ? $t['nb_remplacements'].' <span class="opacitymedium">('.personnel_duree($t['remplacement']).')</span>' : '0').'</td></tr>';
	if ($object->mode_paie === 'fixe' && (float) $object->heures_semaine > 0) {
		print '<tr class="oddeven"><td>'.$langs->trans('HeuresSup').'</td><td>'.personnel_duree($t['heures_sup']).' <span class="opacitymedium">('.$langs->trans('AuDelaDe', price2num((float) $object->heures_semaine)).')</span></td></tr>';
	}
	if ($t['avenir'] > 0) {
		print '<tr class="oddeven"><td>'.$langs->trans('AVenir').'</td><td>'.personnel_duree($t['avenir']).'</td></tr>';
	}
}
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('JoursOuvrables').'</td><td>'.$t['jours_ouvrables'].'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('JoursAbsence').'</td><td>'.$langs->trans('AbsJustNonJust', price2num($t['jours_absent_j']), price2num($t['jours_absent_nj'])).'</td></tr>';
print '</table>';
print '</div><div class="fichehalfright">';

// Estimation de la paie
if ($user->hasRight('personnel', 'paie', 'lire')) {
	$est = $h['estimation'];
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-coins', 'class="pictofixedwidth"').$langs->trans('EstimationMois').'</td></tr>';
	if (!$est['possible']) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('EstimationImpossible').'</span></td></tr>';
	} else {
		foreach ($est['lignes'] as $l) {
			print '<tr class="oddeven"><td>'.dol_escape_htmltag($l[0]).'</td><td class="right nowraponall">'.personnel_montant($l[1]).'</td></tr>';
		}
		print '<tr class="liste_total"><td>'.$langs->trans('TotalEstime').'</td><td class="right nowraponall">'.personnel_montant($est['total']).'</td></tr>';
	}
	print '<tr><td colspan="2"><span class="opacitymedium small">'.$langs->trans('EstimationAide').'</span></td></tr>';
	print '</table>';
}
print '</div></div><div class="clearboth"></div><br>';

// Cours du mois (enseignant)
if ($enseignant && $canpres) {
	print load_fiche_titre($langs->trans('CoursDuMois'), '', 'fa-chalkboard-teacher');
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Creneau').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td>';
	print '<td>'.$langs->trans('Presence').'</td><td>'.$langs->trans('SujetOuRemarque').'</td><td class="right">'.$langs->trans('Duree').'</td><td></td></tr>';
	$jourPrec = '';
	foreach ($h['seances'] as $s) {
		$info = '';
		if ($s->etat === 'retard') {
			$info = '('.$langs->trans('NbMinutes', $s->retard).')';
		}
		print '<tr class="oddeven'.($s->date !== $jourPrec && $jourPrec !== '' ? ' trforbreak' : '').'">';
		print '<td class="nowraponall">'.($s->date !== $jourPrec ? dol_escape_htmltag(personnel_date_label($s->date)) : '').'</td>';
		$jourPrec = $s->date;
		print '<td class="nowraponall">'.dol_escape_htmltag($s->crref).' <span class="opacitymedium small">'.$s->heure_debut.'-'.$s->heure_fin.'</span></td>';
		print '<td>'.dol_escape_htmltag($s->cref).'</td><td>'.dol_escape_htmltag($s->matiere).'</td>';
		print '<td class="nowraponall">'.personnel_etat_badge($s->etat, $info);
		if ($s->etat === 'absent') {
			print ' '.($s->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="opacitymedium small">'.$langs->trans('NonJustifiee').'</span>');
		}
		if ($s->etat === 'remplacement' && !empty($s->titulaire)) {
			print ' <span class="opacitymedium small">'.$langs->trans('AuLieuDe', dol_escape_htmltag(personnel_employe_nom($db, (int) $s->titulaire))).'</span>';
		} elseif ($s->rec && (int) $s->rec->fk_remplacant > 0 && $s->etat === 'absent') {
			print ' <span class="opacitymedium small">'.$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $s->rec->fk_remplacant))).'</span>';
		}
		print '</td>';
		$txt = $s->rec ? trim((string) $s->rec->sujet.((string) $s->rec->note !== '' ? ' — '.$s->rec->note : '')) : '';
		print '<td>'.dol_escape_htmltag($txt).'</td>';
		print '<td class="right">'.personnel_duree($s->minutes).'</td>';
		print '<td class="center"><a href="'.dol_buildpath('/personnel/presence.php', 1).'?date='.$s->date.'&fk_classe='.$s->fk_classe.'&fk_creneau='.$s->fk_creneau.'#cours" title="'.dol_escape_htmltag($langs->trans('VoirCours')).'">'.img_picto($langs->trans('VoirCours'), 'fa-external-link-alt').'</a></td>';
		print '</tr>';
	}
	if (empty($h['seances'])) {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunCoursMois').'</span></td></tr>';
	}
	print '</table></div><br>';
}

// Absences du mois
if ($canabs) {
	list($d1, $d2) = personnel_mois_bornes($mois);
	$total = 0;
	$abs = personnel_absences_liste($db, array('du' => $d1, 'au' => $d2, 'fk_employe' => (int) $object->id, 'employe' => '', 'categorie' => '', 'duree' => '', 'motif' => 0, 'etat' => '', 'statut' => ''), 0, 0, $total);
	$bouton = '';
	if ($user->hasRight('personnel', 'absence', 'gerer') && $object->estPresent()) {
		$bouton = dolGetButtonTitle($langs->trans('NouvelleAbsence'), '', 'fa fa-plus-circle', dol_buildpath('/personnel/absence/list.php', 1).'?action=create&fk_employe='.((int) $object->id));
	}
	print load_fiche_titre($langs->trans('AbsencesDuMois').' ('.count($abs).')', $bouton, 'fa-user-clock');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Periode').'</td><td class="right">'.$langs->trans('NombreJours').'</td><td>'.$langs->trans('Justification').'</td><td>'.$langs->trans('Remarque').'</td></tr>';
	foreach ($abs as $a) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(personnel_absence_periode_label($a)).'</td><td class="right">'.price2num(personnel_absence_nb_jours($a)).'</td>';
		print '<td>'.((int) $a->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="badge badge-danger">'.$langs->trans('NonJustifiee').'</span>');
		print ($a->motif_fr ? ' '.dol_escape_htmltag(ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar))) : '').'</td>';
		print '<td>'.dol_escape_htmltag((string) $a->note).'</td></tr>';
	}
	if (empty($abs)) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('AucuneAbsenceMois').'</span></td></tr>';
	}
	print '</table></div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
