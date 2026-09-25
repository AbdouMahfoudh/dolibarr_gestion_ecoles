<?php
/**
 * Onglet « Emploi du temps et matières » d'un employé : matières qu'il peut enseigner (fiche), classes et
 * matières qu'il enseigne selon l'emploi du temps (heures par semaine) et son emploi du temps de la semaine
 * (bouton PDF). Rien ne se modifie ici : l'emploi du temps se gère dans la fiche de chaque classe.
 *
 * Fichier : custom/personnel/employe/emploi.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('personnel', 'employe', 'lire')) {
	accessforbidden();
}
$object = new EcoleEmploye($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$canclasse = $user->hasRight('classes', 'lire');

llxHeader('', $object->ref.' - '.$langs->trans('EmploiMatieresEmploye'), '', '', 0, 0, '', array('/classes/css/timetable.css'), '', 'mod-personnel page-emploi');

employe_print_banner($object, 'emploi');
print '<div class="underbanner clearboth"></div>';

// Matières qu'il peut enseigner (fiche)
$enseignables = $object->getMatieres();
print load_fiche_titre($langs->trans('MatieresEnseignables'), '', 'fa-book');
if ($enseignables) {
	foreach ($enseignables as $m) {
		print '<span class="badge badge-status1 marginrightonly" style="font-weight:normal;font-size:0.95em;padding:5px 9px">'.dol_escape_htmltag($m->ref.' - '.ecole_label($m)).'</span>';
	}
} else {
	print '<span class="opacitymedium">'.$langs->trans('AucuneMatiereEnseignable').'</span>';
}
print '<br><br>';

// Classes déclarées sur la fiche (information)
$declarees = $object->getClassesDeclarees();
print load_fiche_titre($langs->trans('ClassesDeclarees'), '', 'fa-chalkboard');
if ($declarees) {
	foreach ($declarees as $c) {
		print '<span class="badge badge-status0 marginrightonly" style="font-weight:normal;font-size:0.95em;padding:5px 9px">'.dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</span>';
	}
} else {
	print '<span class="opacitymedium">—</span>';
}
print '<br><br>';

// Classes et matières enseignées selon l'emploi du temps
$cours = personnel_cours_enseignes($db, (int) $object->fk_user);
print load_fiche_titre($langs->trans('CoursEnseignes'), '', 'fa-chalkboard-teacher');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td><td class="right">'.$langs->trans('SeancesSemaine').'</td><td class="right">'.$langs->trans('HeuresSemaineCol').'</td><td></td></tr>';
$total = 0;
$totnb = 0;
foreach ($cours as $c) {
	$total += (int) $c->minutes;
	$totnb += (int) $c->nb;
	$hors = !isset($enseignables[(int) $c->fk_matiere]);
	$horsClasse = $declarees && !isset($declarees[(int) $c->fk_classe]);
	print '<tr class="oddeven"><td>'.($canclasse ? '<a href="'.dol_buildpath('/classes/classe/edt.php', 1).'?id='.((int) $c->fk_classe).'">'.dol_escape_htmltag($c->cref).'</a>' : dol_escape_htmltag($c->cref));
	print ' <span class="opacitymedium">'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $c->cl_fr, 'label_ar' => $c->cl_ar))).'</span></td>';
	print '<td>'.dol_escape_htmltag($c->mref.' - '.ecole_label($c)).'</td>';
	print '<td class="right">'.((int) $c->nb).'</td><td class="right">'.personnel_duree((int) $c->minutes).'</td>';
	print '<td>'.($hors ? '<span class="badge badge-warning" title="'.dol_escape_htmltag($langs->trans('MatiereHorsFicheAide')).'">'.$langs->trans('MatiereHorsFiche').'</span> ' : '');
	print ($horsClasse ? '<span class="badge badge-status0" title="'.dol_escape_htmltag($langs->trans('ClasseNonDeclareeAide')).'">'.$langs->trans('ClasseNonDeclaree').'</span>' : '').'</td></tr>';
}
if (empty($cours)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans((int) $object->fk_user > 0 ? 'AucunCoursEdt' : 'AucunCompte').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="2">'.$langs->trans('Total').'</td><td class="right">'.$totnb.'</td><td class="right">'.personnel_duree($total).'</td><td>';
	if ($object->mode_paie === 'fixe' && (float) $object->heures_semaine > 0 && $user->hasRight('personnel', 'paie', 'lire')) {
		print '<span class="opacitymedium">'.$langs->trans('HeuresPrevuesContrat', price2num((float) $object->heures_semaine)).'</span>';
	}
	print '</td></tr>';
}
print '</table></div><br>';

// Emploi du temps de la semaine
print load_fiche_titre($langs->trans('EmploiDuTempsSemaine'), '', 'fa-calendar-alt');
$data = personnel_edt_enseignant_data($db, (int) $object->fk_user);
if (empty($data['events'])) {
	print '<span class="opacitymedium">'.$langs->trans('AucunCoursEdt').'</span>';
} else {
	print ecole_pdf_button(dol_buildpath('/personnel/edt_pdf.php', 1).'?id='.((int) $object->id));
	ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands']));
}

print dol_get_fiche_end();

llxFooter();
$db->close();
