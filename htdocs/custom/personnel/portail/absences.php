<?php
/**
 * Mes absences dans l'espace d'un employé : absences enregistrées par l'école (journées, demi-journées,
 * périodes, justifiées ou non) et, pour un enseignant, ses cours marqués absent ou en retard (90 derniers jours).
 *
 *   absences
 *
 * Fichier : custom/personnel/portail/absences.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'absences');
$depuis = date('Y-m-d', strtotime(personnel_aujourdhui().' -90 days'));

pe_header($langs->trans('EspMesAbsences'), $acces, $emp, $rubriques, 'absences');

// Absences du personnel
$total = 0;
$abs = personnel_absences_liste($db, array('du' => '', 'au' => '', 'fk_employe' => (int) $emp->id, 'employe' => '', 'categorie' => '', 'duree' => '', 'motif' => 0, 'etat' => '', 'statut' => ''), 50, 0, $total);
print '<h2 class="es-h2"><i class="fas fa-user-clock"></i> '.$langs->trans('AbsencesPersonnel').'</h2><div class="es-list">';
foreach ($abs as $a) {
	print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag(personnel_absence_periode_label($a)).'</b>';
	$motif = $a->motif_fr ? ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar)) : '';
	print '<span class="es-muted es-small">'.dol_escape_htmltag(trim($motif.($a->note ? ' · '.$a->note : ''), ' ·')).'</span></div>';
	print '<div class="es-item-side"><span class="es-chip '.((int) $a->justifiee ? 'es-chip-green' : 'es-chip-red').'">'.$langs->trans((int) $a->justifiee ? 'Justifiee' : 'NonJustifiee').'</span>';
	print '<span class="es-small es-muted">'.$langs->trans('NombreJours').' : <span dir="ltr">'.price2num(personnel_absence_nb_jours($a)).'</span></span></div></div>';
}
if (empty($abs)) {
	print espace_msg($langs->trans('EspAucuneAbsence'), 'ok');
}
print '</div>';

// Cours marqués absent ou en retard (enseignant)
if ($emp->aCategorie('enseignant')) {
	$total = 0;
	$f = array('du' => $depuis, 'au' => '', 'classe' => 0, 'creneau' => 0, 'matiere' => 0, 'fk_employe' => 0, 'employe' => '', 'type' => '', 'etat' => '', 'source' => '');
	$lignes = array();
	foreach (personnel_cours_liste($db, $f, 0, 0, $total) as $r) {
		if ((int) $r->fk_employe === (int) $emp->id && in_array((int) $r->etat, array(PERSONNEL_ABSENT, PERSONNEL_RETARD), true)) {
			$lignes[] = $r;
		}
	}
	print '<h2 class="es-h2"><i class="fas fa-chalkboard-teacher"></i> '.$langs->trans('EspCoursAbsentsRetards').'</h2><div class="es-list">';
	foreach ($lignes as $r) {
		$etat = ((int) $r->etat === PERSONNEL_ABSENT) ? 'absent' : 'retard';
		$info = ($etat === 'retard') ? '('.$langs->trans('NbMinutes', (int) $r->minutes_retard).')' : '';
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag($r->cref.' · '.pe_label($r, 'm_')).'</b>';
		print '<span class="es-muted es-small">'.dol_escape_htmltag(personnel_date_label(substr($r->date_cours, 0, 10))).' · <span dir="ltr">'.dol_escape_htmltag($r->heure_debut).'</span></span></div>';
		print '<div class="es-item-side">'.pe_etat_chip($etat, $info);
		if ((int) $r->justifiee) {
			print '<span class="es-small es-ok">'.$langs->trans('Justifiee').'</span>';
		}
		if ((int) $r->fk_remplacant > 0) {
			print '<span class="es-small es-muted">'.$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $r->fk_remplacant))).'</span>';
		}
		print '</div></div>';
	}
	if (empty($lignes)) {
		print espace_msg($langs->trans('EspAucunCoursAbsent'), 'ok');
	}
	print '</div>';
}

espace_footer();
$db->close();
