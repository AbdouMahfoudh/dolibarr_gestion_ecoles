<?php
/**
 * Examens dans l'espace d'un employé : ses surveillances d'épreuves (toutes catégories) et, pour un enseignant,
 * les épreuves de ses classes et matières. Épreuves à venir (et des 7 derniers jours), jour par jour.
 *
 *   examens
 *
 * Fichier : custom/personnel/portail/examens.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'examens');
$p = $db->prefix();
$depuis = date('Y-m-d', strtotime(personnel_aujourdhui().' -7 days'));
$aff = pe_affectations($db, $emp);

$w = array();
if ((int) $emp->fk_user > 0) {
	$w[] = "x.fk_user = ".((int) $emp->fk_user);
}
foreach ($aff as $cid => $mats) {
	$w[] = "(x.fk_classe = ".((int) $cid)." AND x.fk_matiere IN (".implode(',', array_map('intval', array_keys($mats)))."))";
}
$lignes = array();
if ($w) {
	$sql = "SELECT x.date_examen, x.heure_debut, x.heure_fin, x.fk_user, x.fk_salle, c.ref as cref, m.label_fr as m_fr, m.label_ar as m_ar, s.label_fr as s_fr, s.label_ar as s_ar";
	$sql .= " FROM ".$p."ecole_edt_examen x INNER JOIN ".$p."ecole_classe c ON c.rowid = x.fk_classe INNER JOIN ".$p."ecole_matiere m ON m.rowid = x.fk_matiere";
	$sql .= " LEFT JOIN ".$p."ecole_session s ON s.rowid = x.fk_session";
	$sql .= " WHERE x.date_examen >= '".$db->escape($depuis)."' AND (".implode(' OR ', $w).") ORDER BY x.date_examen, x.heure_debut, c.ref";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$lignes[] = $o;
	}
}
$salles = ecole_salles($db);

pe_header($langs->trans('EspExamens'), $acces, $emp, $rubriques, 'examens');
print '<p class="es-muted">'.$langs->trans('EspAideExamens').'</p>';
if (empty($lignes)) {
	print espace_msg($langs->trans('EspAucunExamen'), 'info');
}
$jour = '';
foreach ($lignes as $o) {
	$d = substr($o->date_examen, 0, 10);
	if ($d !== $jour) {
		if ($jour !== '') {
			print '</div>';
		}
		$jour = $d;
		print '<h2 class="es-h2">'.dol_escape_htmltag(personnel_date_label($d)).'</h2><div class="es-list">';
	}
	$surv = (int) $o->fk_user > 0 && (int) $o->fk_user === (int) $emp->fk_user;
	print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag($o->cref.' · '.pe_label($o, 'm_')).'</b>';
	print '<span class="es-muted es-small"><span dir="ltr">'.dol_escape_htmltag($o->heure_debut.' - '.$o->heure_fin).'</span>'.(isset($salles[(int) $o->fk_salle]) ? ' · '.dol_escape_htmltag($salles[(int) $o->fk_salle]) : '').' · '.dol_escape_htmltag(pe_label($o, 's_')).'</span></div>';
	print '<div class="es-item-side">'.($surv ? '<span class="es-chip es-chip-purple"><i class="fas fa-eye"></i> '.$langs->trans('EspVousSurveillez').'</span>' : '');
	if (!$surv && (int) $o->fk_user > 0) {
		print '<span class="es-small es-muted">'.$langs->trans('Surveillant').' : '.dol_escape_htmltag(ecole_user_label($db, $o->fk_user)).'</span>';
	}
	print '</div></div>';
}
if ($jour !== '') {
	print '</div>';
}

espace_footer();
$db->close();
