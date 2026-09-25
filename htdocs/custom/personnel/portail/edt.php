<?php
/**
 * Emploi du temps de l'enseignant dans son espace : jour par jour sur téléphone (aujourd'hui ouvert),
 * grille de la semaine sur un grand écran. Même présentation que l'emploi du temps d'un élève.
 *
 *   emploi-du-temps
 *
 * Fichier : custom/personnel/portail/edt.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'edt');
$data = personnel_edt_enseignant_data($db, (int) $emp->fk_user);

pe_header($langs->trans('EspEmploiDuTemps'), $acces, $emp, $rubriques, 'edt');

if (empty($data['events'])) {
	print espace_msg($langs->trans('AucunCoursEdt'), 'info');
} else {
	print '<p class="es-muted">'.dol_escape_htmltag($langs->transnoentities('HeuresParSemaine', ecole_duree($data['minutes']))).'</p>';
	$jd = dol_getdate(dol_now(), true);
	$aujourdhui = ((int) $jd['wday'] === 0) ? 7 : (int) $jd['wday'];
	$parJour = array();
	foreach ($data['events'] as $ev) {
		$parJour[$ev['col']][] = $ev;
	}
	print '<div class="es-days">';
	foreach ($data['columns'] as $j => $col) {
		$liste = isset($parJour[$j]) ? $parJour[$j] : array();
		usort($liste, function ($a, $b) {
			return strcmp($a['start'], $b['start']);
		});
		print '<details class="es-card es-day"'.($j === $aujourdhui ? ' open' : '').'>';
		print '<summary>'.dol_escape_htmltag($col['label']).($j === $aujourdhui ? ' <span class="es-chip es-chip-blue">'.$langs->trans('Aujourdhui').'</span>' : '').' <span class="es-muted es-small">('.count($liste).')</span></summary>';
		if (empty($liste)) {
			print '<div class="es-muted es-small">'.$langs->trans('PasDeCours').'</div>';
		}
		foreach ($liste as $ev) {
			print '<div class="es-course es-c'.(((int) $ev['color']) % 10).'"><span class="es-course-time" dir="ltr">'.dol_escape_htmltag($ev['start'].' - '.$ev['end']).'</span>';
			print '<span class="es-course-title">'.dol_escape_htmltag($ev['title']).'</span>';
			$details = array_filter($ev['lines']);
			if (!empty($details)) {
				print '<span class="es-muted es-small">'.dol_escape_htmltag(implode(' · ', $details)).'</span>';
			}
			print '</div>';
		}
		print '</details>';
	}
	print '</div>';
	print '<div class="es-card es-week">';
	ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands']));
	print '</div>';
}

espace_footer();
$db->close();
