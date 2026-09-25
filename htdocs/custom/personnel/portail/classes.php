<?php
/**
 * Mes classes (enseignant) : ses classes d'après l'emploi du temps ; pour une classe, la liste des élèves
 * (numéro d'appel, photo, nom — sans coordonnées ni situation financière), les matières qu'il y enseigne et les
 * absences, retards et renvois des 30 derniers jours dans ses cours.
 *
 *   classes[/<code de la classe>]
 *
 * Fichier : custom/personnel/portail/classes.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/eleves/core/lib/eleves.lib.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'classes');
$classes = pe_classes($db, $emp);
$aff = pe_affectations($db, $emp);
$fk_classe = pe_arg(0) !== '' ? espace_id_par_jeton('classe', pe_arg(0), array_keys($classes)) : 0;

pe_header($langs->trans('EspMesClasses'), $acces, $emp, $rubriques, 'classes', $fk_classe ? espace_page_url('classes') : '');

if (!$fk_classe) {
	if (empty($classes)) {
		print espace_msg($langs->trans('AucunCoursEdt'), 'info');
	}
	print '<div class="es-cards">';
	foreach ($classes as $cid => $c) {
		$nb = count(eleves_liste_classe($db, $cid));
		$mats = array();
		foreach (array_keys($aff[$cid]) as $mid) {
			$mats[] = ecole_row_label($db, 'ecole_matiere', $mid);
		}
		print '<a class="es-card" href="'.dol_escape_htmltag(espace_page_url('classes/'.espace_jeton('classe', $cid))).'">';
		print '<div class="es-item-top"><b>'.dol_escape_htmltag($c->ref.' · '.ecole_label($c)).'</b><i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').' es-chev"></i></div>';
		print '<div class="es-chips"><span class="es-chip"><i class="fas fa-users"></i> '.$langs->trans('NbEleves').' : '.$nb.'</span></div>';
		print '<div class="es-muted es-small">'.dol_escape_htmltag(implode(', ', $mats)).'</div></a>';
	}
	print '</div>';
	espace_footer();
	$db->close();
	exit;
}

$c = $classes[$fk_classe];
print '<section class="es-card"><h2>'.dol_escape_htmltag($c->ref.' · '.ecole_label($c)).'</h2>';
$mats = array();
foreach (array_keys($aff[$fk_classe]) as $mid) {
	$mats[] = '<span class="es-chip es-chip-blue">'.dol_escape_htmltag(ecole_row_label($db, 'ecole_matiere', $mid)).'</span>';
}
print '<div class="es-chips">'.implode('', $mats).'</div></section>';

// Élèves (inscrits et suspendus) par numéro d'appel
$eleves = array();
foreach (eleves_liste_classe($db, $fk_classe) as $o) {
	if (in_array((int) $o->status, EcoleEleve::statusOccupantPlace(), true)) {
		$eleves[] = $o;
	}
}
print '<h2 class="es-h2"><i class="fas fa-users"></i> '.$langs->trans('Eleves').' <span class="es-muted es-small">('.count($eleves).')</span></h2>';
print '<div class="es-list">';
foreach ($eleves as $o) {
	$e = new EcoleEleve($db);
	$e->fetch((int) $o->rowid);
	print '<div class="es-card es-item"><div class="es-child-head">'.espace_avatar($e).'<div><div><b dir="ltr">'.($o->numero_appel ? (int) $o->numero_appel.'.' : '').'</b> '.dol_escape_htmltag(ecole_label($o)).'</div>';
	$autre = espace_rtl() ? $o->nom_fr : $o->nom_ar;
	if (!empty($autre)) {
		print '<div class="es-muted es-small" dir="auto">'.dol_escape_htmltag($autre).'</div>';
	}
	print '</div></div>';
	if ((int) $o->status === EcoleEleve::STATUS_SUSPENDU) {
		print '<span class="es-chip es-chip-orange">'.$langs->trans('StatutSuspendu').'</span>';
	}
	print '</div>';
}
if (empty($eleves)) {
	print espace_msg($langs->trans('AucunEleveClasseDate'), 'info');
}
print '</div>';

// Absences, retards et renvois des 30 derniers jours dans ses cours (ses matières dans cette classe)
$du = date('Y-m-d', strtotime(personnel_aujourdhui().' -30 days'));
$mids = array_keys($aff[$fk_classe]);
$sql = "SELECT a.date_appel, a.type, a.heure_arrivee, a.justifiee, e.nom_fr, e.nom_ar, e.numero_appel, cr.ref as crref, cr.heure_debut, m.label_fr as m_fr, m.label_ar as m_ar";
$sql .= " FROM ".$db->prefix()."ecole_absence a INNER JOIN ".$db->prefix()."ecole_appel ap ON ap.rowid = a.fk_appel INNER JOIN ".$db->prefix()."ecole_eleve e ON e.rowid = a.fk_eleve";
$sql .= " LEFT JOIN ".$db->prefix()."ecole_creneau cr ON cr.rowid = a.fk_creneau LEFT JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = ap.fk_matiere";
$sql .= " WHERE a.fk_classe = ".((int) $fk_classe)." AND a.date_appel >= '".$db->escape($du)."' AND ap.fk_matiere IN (".implode(',', array_map('intval', $mids)).")";
$sql .= " ORDER BY a.date_appel DESC, cr.heure_debut DESC";
$resql = $db->query($sql);
print '<h2 class="es-h2"><i class="fas fa-user-clock"></i> '.$langs->trans('EspAbsencesDansMesCours').'</h2>';
print '<div class="es-list">';
$n = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	$n++;
	$css = array(ELEVES_ABSENT => 'es-chip-red', ELEVES_RETARD => 'es-chip-orange', ELEVES_RENVOYE => 'es-chip-purple');
	print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag(ecole_label($o)).'</b>';
	print '<span class="es-muted es-small">'.dol_escape_htmltag(personnel_date_label(substr($o->date_appel, 0, 10)).' · '.$o->crref.' · '.pe_label($o, 'm_')).'</span></div>';
	print '<div class="es-item-side"><span class="es-chip '.(isset($css[(int) $o->type]) ? $css[(int) $o->type] : '').'">'.dol_escape_htmltag(eleves_presence_label(eleves_presence_code($o->type, (string) $o->heure_arrivee))).'</span>';
	if ((int) $o->justifiee) {
		print '<span class="es-small es-ok">'.$langs->trans('Justifiee').'</span>';
	}
	print '</div></div>';
}
if (!$n) {
	print espace_msg($langs->trans('EspAucuneAbsenceMesCours'), 'ok');
}
print '</div>';

espace_footer();
$db->close();
