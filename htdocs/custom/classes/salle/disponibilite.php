<?php
/**
 * Disponibilité des salles pour un jour : une ligne par salle active, une colonne par créneau.
 * Chaque case indique « Libre » ou la classe qui occupe la salle.
 *
 * Fichier : custom/classes/salle/disponibilite.php
 */

require '../init.php';

$langs->loadLangs(array('classes@classes', 'other'));

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$p = MAIN_DB_PREFIX;
$jours = ecole_jours();
$ouvrables = ecole_jours_ouvrables();

// Jour affiché : celui demandé, sinon aujourd'hui s'il est ouvrable, sinon le premier jour ouvrable
$jour = GETPOSTINT('jour');
if (!in_array($jour, $ouvrables)) {
	$today = (int) date('N', strtotime(dol_print_date(dol_now(), '%Y-%m-%d', 'tzuser'))); // 1 = lundi ... 7 = dimanche
	$jour = in_array($today, $ouvrables) ? $today : (int) reset($ouvrables);
}

$creneaux = array();
$resql = $db->query("SELECT rowid, label_fr, label_ar, heure_debut, heure_fin FROM ".$p."ecole_creneau WHERE entity IN (".getEntity('ecole_creneau').") AND status = 1 ORDER BY heure_debut");
while ($resql && ($o = $db->fetch_object($resql))) {
	$creneaux[(int) $o->rowid] = $o;
}

$salles = array();
$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, capacite FROM ".$p."ecole_salle WHERE entity IN (".getEntity('ecole_salle').") AND status = 1 ORDER BY ref");
while ($resql && ($o = $db->fetch_object($resql))) {
	$salles[(int) $o->rowid] = $o;
}

// Classe attitrée de chaque salle
$attitree = array();
$resql = $db->query("SELECT fk_salle, ref FROM ".$p."ecole_classe WHERE fk_salle IS NOT NULL ORDER BY fk_niveau, rowid");
while ($resql && ($o = $db->fetch_object($resql))) {
	$attitree[(int) $o->fk_salle][] = $o->ref;
}

// Occupation du jour
$occ = array();
$sql = "SELECT e.fk_salle, e.fk_creneau, e.fk_classe, c.ref as cref, m.label_fr, m.label_ar";
$sql .= " FROM ".$p."ecole_edt_cours e";
$sql .= " INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
$sql .= " WHERE e.jour = ".((int) $jour)." AND e.fk_salle IS NOT NULL";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$occ[(int) $o->fk_salle][(int) $o->fk_creneau] = $o;
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('DisponibiliteSalles'), '', '', 0, 0, '', '', '', 'mod-classes page-salle-dispo');
print load_fiche_titre($langs->trans('DisponibiliteSalles'), '', 'fa-door-open');

// Un onglet par jour ouvrable
$head = array();
foreach ($ouvrables as $i => $j) {
	$head[$i] = array($_SERVER['PHP_SELF'].'?jour='.$j, $langs->trans($jours[$j]), 'j'.$j);
}
print dol_get_fiche_head($head, 'j'.$jour, '', -1);

if (empty($salles)) {
	print '<div class="warning">'.$langs->trans('AucuneSalle').' <a href="'.dol_buildpath('/classes/salle/card.php', 1).'?action=create">'.$langs->trans('NouvelleSalle').'</a></div>';
} elseif (empty($creneaux)) {
	print '<div class="warning">'.$langs->trans('AucunCreneau').'</div>';
} else {
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Salle').'</td><td>'.$langs->trans('ClasseAttitree').'</td>';
	foreach ($creneaux as $c) {
		print '<td class="center">'.dol_escape_htmltag(ecole_label($c)).'<br><span class="opacitymedium small">'.$c->heure_debut.' - '.$c->heure_fin.'</span></td>';
	}
	print '</tr>';
	$libres = array_fill_keys(array_keys($creneaux), 0);
	foreach ($salles as $sid => $s) {
		print '<tr class="oddeven">';
		print '<td class="nowraponall"><a href="'.dol_buildpath('/classes/salle/occupation.php', 1).'?id='.$sid.'" title="'.dol_escape_htmltag(ecole_label($s)).'">'.img_picto('', 'fa-door-open', 'class="pictofixedwidth"').dol_escape_htmltag($s->ref).'</a>';
		if ($s->capacite) {
			print ' <span class="opacitymedium small">('.((int) $s->capacite).')</span>';
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag(isset($attitree[$sid]) ? implode(', ', $attitree[$sid]) : '').'</td>';
		foreach ($creneaux as $cid => $c) {
			print '<td class="center">';
			if (isset($occ[$sid][$cid])) {
				$o = $occ[$sid][$cid];
				print '<a href="'.dol_buildpath('/classes/classe/edt.php', 1).'?id='.((int) $o->fk_classe).'"><b>'.dol_escape_htmltag($o->cref).'</b></a>';
				print '<br><span class="opacitymedium small">'.dol_escape_htmltag(ecole_label($o)).'</span>';
			} else {
				print dolGetStatus($langs->trans('Libre'), $langs->trans('Libre'), '', 'status4', 1);
				$libres[$cid]++;
			}
			print '</td>';
		}
		print '</tr>';
	}
	print '<tr class="liste_total"><td colspan="2">'.$langs->trans('SallesLibres').'</td>';
	foreach ($libres as $n) {
		print '<td class="center">'.$n.' / '.count($salles).'</td>';
	}
	print '</tr>';
	print '</table>';
	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
