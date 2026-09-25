<?php
/**
 * Onglet « Occupation » d'une salle : emploi du temps de la semaine (quelle classe l'utilise,
 * à quel moment ; les cases vertes sont libres) et examens à venir dans cette salle.
 *
 * Fichier : custom/classes/salle/occupation.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_salle.class.php');

$langs->loadLangs(array('classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$object = new EcoleSalle($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$p = MAIN_DB_PREFIX;

// Cours donnés dans cette salle (un clic ouvre l'emploi du temps de la classe)
$data = ecole_edt_salle_data($db, $id);
foreach ($data['events'] as $k => $ev) {
	$data['events'][$k]['editurl'] = dol_buildpath('/classes/classe/edt.php', 1).'?id='.$ev['fk_classe'];
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('OccupationSalle'), '', '', 0, 0, '', array('/classes/css/timetable.css'), '', 'mod-classes page-salle-occupation');

print dol_get_fiche_head(salle_prepare_head($object), 'occupation', $langs->trans('Salle'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/classes/salle/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 0, 'rowid', 'ref', '<div class="refidno">'.dol_escape_htmltag(ecole_label($object)).'</div>', '', 0, '', ''); // le statut est ajouté par Dolibarr

print '<div class="fichecenter"><br>';

$attitrees = array();
foreach ($object->getClassesAttitrees() as $c) {
	$attitrees[] = $c->ref;
}
print '<div class="opacitymedium">'.$langs->trans('ClasseAttitree').' : <b>'.dol_escape_htmltag($attitrees ? implode(', ', $attitrees) : $langs->trans('AucuneClasseAttitree')).'</b>';
print ' &nbsp; — &nbsp; '.$langs->trans('HeuresParSemaine', ecole_duree(ecole_minutes_semaine($db, 'e.fk_salle = '.((int) $id)))).'</div>';

if (empty($data['bands'])) {
	print '<div class="warning">'.$langs->trans('AucunCreneau').'</div>';
} else {
	print ecole_pdf_button(dol_buildpath('/classes/edt_pdf.php', 1).'?type=salle&id='.$id);
	ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands'], 'freelabel' => $langs->trans('Libre')));
}

// Examens à venir dans cette salle
print load_fiche_titre($langs->trans('ExamensAVenir'), '', '');
$sql = "SELECT x.date_examen, x.heure_debut, x.heure_fin, x.fk_classe, c.ref as cref, m.label_fr, m.label_ar";
$sql .= " FROM ".$p."ecole_edt_examen x";
$sql .= " INNER JOIN ".$p."ecole_classe c ON c.rowid = x.fk_classe";
$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = x.fk_matiere";
$sql .= " WHERE x.fk_salle = ".((int) $id)." AND x.date_examen >= '".$db->escape(dol_print_date(dol_now(), '%Y-%m-%d'))."'";
$sql .= " ORDER BY x.date_examen, x.heure_debut";
$resql = $db->query($sql);
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('DateExamen').'</td><td>'.$langs->trans('Horaire').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td></tr>';
$n = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->date_examen), 'day').'</td><td>'.$o->heure_debut.' - '.$o->heure_fin.'</td>';
	print '<td><a href="'.dol_buildpath('/classes/classe/examens.php', 1).'?id='.((int) $o->fk_classe).'">'.dol_escape_htmltag($o->cref).'</a></td><td>'.dol_escape_htmltag(ecole_label($o)).'</td></tr>';
	$n++;
}
if (!$n) {
	print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('AucuneEpreuve').'</span></td></tr>';
}
print '</table>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
