<?php
/**
 * Données des emplois du temps (cours d'une classe, occupation d'une salle, examens d'une classe).
 * Les mêmes données servent à l'écran (ecole_timetable_render) et au PDF (ecole_pdf_timetable) :
 * ce qui est imprimé est donc toujours identique à ce qui est affiché.
 *
 * Fichier : custom/classes/core/lib/edt.lib.php
 */

/**
 * Créneaux actifs, du plus tôt au plus tard.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,object> rowid => ligne
 */
function ecole_creneaux_actifs($db)
{
	$out = array();
	$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, heure_debut, heure_fin FROM ".$db->prefix()."ecole_creneau WHERE entity IN (".getEntity('ecole_creneau').") AND status = 1 ORDER BY heure_debut");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/**
 * Colonnes (jours ouvrables) et créneaux communs aux emplois du temps de la semaine.
 *
 * @param  array $creneaux Créneaux actifs
 * @return array{0:array,1:array,2:int[]} colonnes, bandes, id du créneau de chaque bande
 */
function ecole_edt_semaine($creneaux)
{
	global $langs;
	$jours = ecole_jours();
	$columns = array();
	foreach (ecole_jours_ouvrables() as $j) {
		$columns[$j] = array('label' => $langs->trans($jours[$j]));
	}
	$bands = array();
	$bandids = array();
	foreach ($creneaux as $cid => $c) {
		$bands[] = array('start' => $c->heure_debut, 'end' => $c->heure_fin, 'label' => ecole_label($c));
		$bandids[] = $cid;
	}
	return array($columns, $bands, $bandids);
}

/**
 * Emploi du temps des cours d'une classe.
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $classeId Classe
 * @return array{columns:array,bands:array,bandids:int[],events:array}  chaque événement porte aussi 'rowid'
 */
function ecole_edt_classe_data($db, $classeId)
{
	$creneaux = ecole_creneaux_actifs($db);
	list($columns, $bands, $bandids) = ecole_edt_semaine($creneaux);
	$salles = ecole_salles($db);

	$events = array();
	$sql = "SELECT e.rowid, e.jour, e.fk_creneau, e.fk_matiere, e.fk_user, e.fk_salle, m.label_fr, m.label_ar";
	$sql .= " FROM ".$db->prefix()."ecole_edt_cours e INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere";
	$sql .= " WHERE e.fk_classe = ".((int) $classeId);
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		if (!isset($columns[(int) $o->jour]) || !isset($creneaux[(int) $o->fk_creneau])) {
			continue; // jour non ouvrable ou créneau désactivé
		}
		$c = $creneaux[(int) $o->fk_creneau];
		$events[] = array(
			'rowid' => (int) $o->rowid,
			'col' => (int) $o->jour,
			'start' => $c->heure_debut,
			'end' => $c->heure_fin,
			'title' => ecole_label($o),
			'lines' => array(
				$o->fk_user > 0 ? ecole_user_label($db, $o->fk_user) : '',
				isset($salles[(int) $o->fk_salle]) ? $salles[(int) $o->fk_salle] : '',
			),
			'color' => (int) $o->fk_matiere,
		);
	}
	return array('columns' => $columns, 'bands' => $bands, 'bandids' => $bandids, 'events' => $events);
}

/**
 * Occupation d'une salle sur la semaine (cours de toutes les classes).
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $salleId Salle
 * @return array{columns:array,bands:array,events:array}  chaque événement porte aussi 'fk_classe'
 */
function ecole_edt_salle_data($db, $salleId)
{
	$creneaux = ecole_creneaux_actifs($db);
	list($columns, $bands) = ecole_edt_semaine($creneaux);

	$events = array();
	$sql = "SELECT e.jour, e.fk_creneau, e.fk_classe, e.fk_user, c.ref as cref, m.label_fr, m.label_ar";
	$sql .= " FROM ".$db->prefix()."ecole_edt_cours e";
	$sql .= " INNER JOIN ".$db->prefix()."ecole_classe c ON c.rowid = e.fk_classe";
	$sql .= " INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere";
	$sql .= " WHERE e.fk_salle = ".((int) $salleId);
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		if (!isset($columns[(int) $o->jour]) || !isset($creneaux[(int) $o->fk_creneau])) {
			continue;
		}
		$c = $creneaux[(int) $o->fk_creneau];
		$events[] = array(
			'fk_classe' => (int) $o->fk_classe,
			'col' => (int) $o->jour,
			'start' => $c->heure_debut,
			'end' => $c->heure_fin,
			'title' => $o->cref,
			'lines' => array(ecole_label($o), $o->fk_user > 0 ? ecole_user_label($db, $o->fk_user) : ''),
			'color' => (int) $o->fk_classe,
		);
	}
	return array('columns' => $columns, 'bands' => $bands, 'events' => $events);
}

/**
 * Emploi du temps des examens d'une classe pour une session : une colonne par jour de la période
 * (et par date d'épreuve éventuellement hors période).
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $classeId Classe
 * @param  object $session  Ligne de session (rowid, date_debut, date_fin)
 * @return array{columns:array,events:array,periode:array}  chaque événement porte aussi 'rowid'
 */
function ecole_edt_examens_data($db, $classeId, $session)
{
	global $langs;
	$jours = ecole_jours();
	$salles = ecole_salles($db);
	$periode = ecole_jours_periode($session->date_debut, $session->date_fin);

	$columns = array();
	foreach ($periode as $d => $t) {
		$columns[$d] = array('label' => $langs->trans($jours[(int) date('N', $t)]), 'sublabel' => dol_print_date($t, 'day'));
	}
	$events = array();
	$sql = "SELECT e.rowid, e.date_examen, e.heure_debut, e.heure_fin, e.fk_matiere, e.fk_salle, e.fk_user, m.label_fr, m.label_ar";
	$sql .= " FROM ".$db->prefix()."ecole_edt_examen e INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere";
	$sql .= " WHERE e.fk_classe = ".((int) $classeId)." AND e.fk_session = ".((int) $session->rowid)." ORDER BY e.date_examen, e.heure_debut";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$d = substr($o->date_examen, 0, 10);
		if (!isset($columns[$d])) {
			$t = strtotime($d.' 12:00:00');
			$columns[$d] = array('label' => $langs->trans($jours[(int) date('N', $t)]), 'sublabel' => dol_print_date($t, 'day'));
		}
		$events[] = array(
			'rowid' => (int) $o->rowid,
			'col' => $d,
			'start' => $o->heure_debut,
			'end' => $o->heure_fin,
			'title' => ecole_label($o),
			'lines' => array(
				isset($salles[(int) $o->fk_salle]) ? $salles[(int) $o->fk_salle] : '',
				$o->fk_user > 0 ? ecole_user_label($db, $o->fk_user) : '',
			),
			'color' => (int) $o->fk_matiere,
		);
	}
	ksort($columns);
	return array('columns' => $columns, 'events' => $events, 'periode' => $periode);
}
