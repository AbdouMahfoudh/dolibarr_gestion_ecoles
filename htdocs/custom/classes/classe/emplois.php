<?php
/**
 * Emplois du temps : toutes les classes, regroupées par niveau, avec l'état de leur emploi
 * du temps et un accès direct à celui-ci.
 *
 * Fichier : custom/classes/classe/emplois.php
 */

require '../init.php';

$langs->loadLangs(array('classes@classes', 'other'));

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$p = MAIN_DB_PREFIX;

// Nombre de matières liées par classe
$nbmat = array();
$resql = $db->query("SELECT fk_classe, COUNT(*) as nb FROM ".$p."ecole_classe_matiere GROUP BY fk_classe");
while ($resql && ($o = $db->fetch_object($resql))) {
	$nbmat[(int) $o->fk_classe] = (int) $o->nb;
}
$salles = ecole_salles($db);

$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, c.fk_salle, n.rowid as nid, n.label_fr as nlabel_fr, n.label_ar as nlabel_ar";
$sql .= " FROM ".$p."ecole_classe c INNER JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
$passee = ecole_annee_passee();
$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').")";
if ($passee) {
	// Année passée : les classes de l'emploi du temps gardé cette année-là, avec leurs heures
	$minPassee = array();
	$r = $db->query("SELECT fk_classe, heure_debut, heure_fin FROM ".$p."ecole_edt_annee WHERE annee = ".ecole_annee_vue());
	while ($r && ($o = $db->fetch_object($r))) {
		$minPassee[(int) $o->fk_classe] = (isset($minPassee[(int) $o->fk_classe]) ? $minPassee[(int) $o->fk_classe] : 0) + max(0, ecole_hhmm_to_min($o->heure_fin) - ecole_hhmm_to_min($o->heure_debut));
	}
	$sql .= " AND c.rowid IN (".(empty($minPassee) ? '0' : implode(',', array_keys($minPassee))).")";
} else {
	$sql .= " AND c.status = 1";
}
$sql .= " ORDER BY n.position, c.rowid";
$resql = $db->query($sql);

llxHeader('', $langs->trans('MenuEmploisDuTemps'), '', '', 0, 0, '', '', '', 'mod-classes page-emplois');
print load_fiche_titre($langs->trans('MenuEmploisDuTemps'), '', 'fa-calendar-alt');
print ecole_annee_selecteur();
print '<div class="opacitymedium">'.$langs->trans('AideEmploisDuTemps').'</div><br>';

$nbcreneaux = 0;
$r = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_creneau WHERE status = 1");
if ($r && ($o = $db->fetch_object($r))) {
	$nbcreneaux = (int) $o->nb;
}
if (!$nbcreneaux) {
	print '<div class="warning">'.$langs->trans('AucunCreneau').' <a href="'.dol_buildpath('/classes/creneau/card.php', 1).'?action=create">'.$langs->trans('NouveauCreneau').'</a></div>';
}

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('EcoleLibelle').'</td><td>'.$langs->trans('SalleAttitree').'</td>';
print '<td class="right">'.$langs->trans('EcoleMatieres').'</td><td class="right">'.$langs->trans('HeuresSemaine').'</td><td></td></tr>';

$curniv = 0;
$n = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	if ((int) $o->nid !== $curniv) {
		$curniv = (int) $o->nid;
		print '<tr class="liste_titre_sub"><td colspan="6"><b>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $o->nlabel_fr, 'label_ar' => $o->nlabel_ar))).'</b></td></tr>';
	}
	$id = (int) $o->rowid;
	$min = $passee ? (isset($minPassee[$id]) ? $minPassee[$id] : 0) : ecole_minutes_semaine($db, 'e.fk_classe = '.$id);
	$nm = isset($nbmat[$id]) ? $nbmat[$id] : 0;
	$edturl = dol_buildpath('/classes/classe/edt.php', 1).'?id='.$id;
	print '<tr class="oddeven">';
	print '<td class="nowraponall"><a href="'.$edturl.'">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($o)).'</td>';
	print '<td>'.dol_escape_htmltag(isset($salles[(int) $o->fk_salle]) ? $salles[(int) $o->fk_salle] : '').'</td>';
	print '<td class="right">';
	if ($nm) {
		print $nm;
	} else {
		print '<a href="'.dol_buildpath('/classes/classe/matieres.php', 1).'?id='.$id.'" class="error">'.$langs->trans('AucuneMatiere').'</a>';
	}
	print '</td>';
	print '<td class="right">'.($min > 0 ? ecole_duree($min) : '<span class="opacitymedium">'.$langs->trans('EdtVide').'</span>').'</td>';
	print '<td class="right nowraponall"><a class="butAction smallpaddingimp" href="'.$edturl.'">'.$langs->trans('VoirEmploiDuTemps').'</a></td>';
	print '</tr>';
	$n++;
}
if (!$n) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
