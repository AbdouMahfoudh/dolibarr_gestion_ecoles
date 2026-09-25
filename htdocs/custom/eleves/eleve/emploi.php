<?php
/**
 * Onglet « Emploi du temps et matières » d'un élève : tout est hérité de sa classe
 * (matières avec coefficients, enseignants, heures par semaine, emploi du temps de la semaine).
 * Rien ne se modifie ici : ces informations se gèrent dans la fiche de la classe.
 *
 * Fichier : custom/eleves/eleve/emploi.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/classes/core/lib/edt.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('eleves', 'eleve', 'lire')) {
	accessforbidden();
}
$object = new EcoleEleve($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$classe = new EcoleClasse($db);
$classe->fetch((int) $object->fk_classe);
$canclasse = $user->hasRight('classes', 'lire');
$p = $db->prefix();

llxHeader('', $object->ref.' - '.$langs->trans('EmploiMatieres'), '', '', 0, 0, '', array('/classes/css/timetable.css'), '', 'mod-eleves page-emploi');

eleve_print_banner($object, 'emploi');
print '<div class="underbanner clearboth"></div>';

// Origine des informations : la classe de l'élève
$lienclasse = $canclasse ? '<a href="'.dol_buildpath('/classes/classe/card.php', 1).'?id='.((int) $classe->id).'">'.dol_escape_htmltag($classe->ref.' - '.ecole_label($classe)).'</a>' : dol_escape_htmltag($classe->ref.' - '.ecole_label($classe));
print '<div class="opacitymedium">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').$langs->transnoentities('HeriteDeLaClasse', $lienclasse).'</div><br>';

// Heures par semaine et enseignants de chaque matière, d'après l'emploi du temps
$minutes = array();
$profs = array();
$sql = "SELECT e.fk_matiere, e.fk_user, c.heure_debut, c.heure_fin FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_creneau c ON c.rowid = e.fk_creneau";
$sql .= " WHERE e.fk_classe = ".((int) $object->fk_classe)." AND c.status = 1";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$m = (int) $o->fk_matiere;
	$minutes[$m] = (isset($minutes[$m]) ? $minutes[$m] : 0) + max(0, ecole_hhmm_to_min($o->heure_fin) - ecole_hhmm_to_min($o->heure_debut));
	if ($o->fk_user > 0) {
		$profs[$m][(int) $o->fk_user] = ecole_user_label($db, $o->fk_user);
	}
}

// Matières de la classe
$baremes = array('20' => $langs->trans('BaremeSur20'), 'coef20' => $langs->trans('BaremeSurCoef20'));
print load_fiche_titre($langs->trans('MatieresDeLaClasse'), '', 'fa-book');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Matiere').'</td><td class="right">'.$langs->trans('Coefficient').'</td><td>'.$langs->trans('Bareme').'</td><td class="right">'.$langs->trans('NoteMax').'</td>';
print '<td>'.$langs->trans('Enseignant').'</td><td class="right">'.$langs->trans('HeuresSemaineCol').'</td></tr>';
$sql = "SELECT cm.fk_matiere, cm.coefficient, cm.bareme, cm.note_max, m.ref, m.label_fr, m.label_ar FROM ".$p."ecole_classe_matiere cm";
$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = cm.fk_matiere WHERE cm.fk_classe = ".((int) $object->fk_classe)." ORDER BY m.label_fr";
$resql = $db->query($sql);
$nb = 0;
$totcoef = 0;
$totmin = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	$nb++;
	$m = (int) $o->fk_matiere;
	$totcoef += (float) $o->coefficient;
	$totmin += isset($minutes[$m]) ? $minutes[$m] : 0;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($o->ref).' - '.dol_escape_htmltag(ecole_label($o)).'</td>';
	print '<td class="right">'.price2num($o->coefficient).'</td>';
	print '<td>'.dol_escape_htmltag(isset($baremes[$o->bareme]) ? $baremes[$o->bareme] : $o->bareme).'</td>';
	print '<td class="right">'.price2num($o->note_max).'</td>';
	print '<td>'.(isset($profs[$m]) ? dol_escape_htmltag(implode(', ', $profs[$m])) : '<span class="opacitymedium">—</span>').'</td>';
	print '<td class="right">'.(!empty($minutes[$m]) ? ecole_duree($minutes[$m]) : '<span class="opacitymedium">—</span>').'</td></tr>';
}
if (!$nb) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('AucuneMatiereClasse').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td>'.$langs->trans('Total').' ('.$nb.')</td><td class="right">'.price2num($totcoef).'</td><td></td><td></td><td></td><td class="right">'.($totmin > 0 ? ecole_duree($totmin) : '').'</td></tr>';
}
print '</table></div><br>';

// Emploi du temps de la semaine
print load_fiche_titre($langs->trans('EmploiDuTempsSemaine'), '', 'fa-calendar-alt');
$data = ecole_edt_classe_data($db, (int) $object->fk_classe);
if (empty($data['events'])) {
	print '<span class="opacitymedium">'.$langs->trans('AucunCoursClasse').'</span>';
} else {
	if ($canclasse) {
		print ecole_pdf_button(dol_buildpath('/classes/edt_pdf.php', 1).'?type=cours&id='.((int) $object->fk_classe));
	}
	ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands']));
}

print dol_get_fiche_end();

llxFooter();
$db->close();
