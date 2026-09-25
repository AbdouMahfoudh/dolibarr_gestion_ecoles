<?php
/**
 * Onglet « Absences et discipline » d'une classe : pour chaque élève de la classe, sur une période choisie,
 * absences, retards (dont non justifiés) et sanctions ; élèves signalés (seuils de l'année). Exports PDF / Excel.
 *
 * Fichier : custom/eleves/classe/discipline.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('classes', 'lire') || !$user->hasRight('eleves', 'absence', 'lire')) {
	accessforbidden();
}
$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
list($du, $au) = eleves_periode_defaut();
$du = eleves_date_ok(GETPOST('du', 'alpha')) ?: $du;
$au = eleves_date_ok(GETPOST('au', 'alpha')) ?: $au;
if ($au < $du) {
	list($du, $au) = array($au, $du);
}

llxHeader('', $object->ref.' - '.$langs->trans('AbsencesDiscipline'), '', '', 0, 0, '', '', '', 'mod-eleves page-classe-discipline');

classe_print_header($object, 'discipline');
print '<div class="fichecenter"><br>';

$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons = ecole_export_buttons('classe_discipline', '&id='.((int) $object->id).'&du='.$du.'&au='.$au, 'eleves');
}
if ($user->hasRight('eleves', 'absence', 'appel')) {
	$buttons .= dolGetButtonTitle($langs->trans('FaireAppel'), '', 'fa fa-clipboard-check', dol_buildpath('/eleves/appel.php', 1).'?fk_classe='.((int) $object->id));
}
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<div class="inline-block valignmiddle marginrightonly"><span class="opacitymedium">'.img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').$langs->trans('Periode').'</span> ';
print '<input type="date" name="du" class="flat" value="'.dol_escape_htmltag($du).'"> → <input type="date" name="au" class="flat" value="'.dol_escape_htmltag($au).'"> ';
print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></div>';
print '<div class="inline-block valignmiddle floatright">'.$buttons.'</div>';
print '</form><br>';

$lignes = eleves_classe_discipline($db, $object, $du, $au);
$tot = array('absences' => 0, 'absences_nj' => 0, 'retards' => 0, 'retards_nj' => 0, 'sanctions' => 0);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td>';
print '<td class="center">'.$langs->trans('Absences').'</td><td class="center">'.$langs->trans('AbsencesNonJustifiees').'</td><td class="center">'.$langs->trans('Retards').'</td><td class="center">'.$langs->trans('RetardsNonJustifies').'</td>';
print '<td class="center">'.$langs->trans('Sanctions').'</td><td class="center">'.$langs->trans('Signale').'</td></tr>';
$urlAbs = dol_buildpath('/eleves/absence/list.php', 1).'?f_du='.$du.'&f_au='.$au;
foreach ($lignes as $eid => $l) {
	$c = $l['compteurs'];
	foreach ($tot as $k => $v) {
		$tot[$k] += $c[$k];
	}
	print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $eid).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($l['eleve']->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($l['eleve'])).'</td>';
	foreach (array('absences', 'absences_nj', 'retards', 'retards_nj') as $k) {
		print '<td class="center">'.($c[$k] ? '<a href="'.dol_escape_htmltag($urlAbs.'&f_fk_eleve='.((int) $eid).'&f_type='.(strpos($k, 'absences') === 0 ? ELEVES_ABSENT : ELEVES_RETARD).(substr($k, -3) === '_nj' ? '&f_etat=non' : '')).'">'.$c[$k].'</a>' : '<span class="opacitymedium">0</span>').'</td>';
	}
	print '<td class="center">'.($c['sanctions'] ?: '<span class="opacitymedium">0</span>').'</td>';
	print '<td class="center">'.(empty($l['raisons']) ? '' : '<span class="badge badge-danger" title="'.dol_escape_htmltag(implode(' ; ', $l['raisons'])).'">!</span>').'</td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunEleve').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="2">'.$langs->trans('Total').' ('.count($lignes).')</td>';
	foreach ($tot as $v) {
		print '<td class="center">'.$v.'</td>';
	}
	print '<td></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideDisciplineClasse').'</span>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
