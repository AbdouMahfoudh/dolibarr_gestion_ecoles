<?php
/**
 * Appels faits (ou corrigés) par le surveillant ces 90 derniers jours : date, créneau, classe, matière,
 * nombre d'absents et de retards ; chaque ligne ouvre l'appel.
 *
 *   mes-appels
 *
 * Fichier : custom/personnel/portail/mesappels.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'mesappels');
$p = $db->prefix();
$depuis = date('Y-m-d', strtotime(personnel_aujourdhui().' -90 days'));

$sql = "SELECT a.fk_classe, a.date_appel, a.fk_creneau, a.nb_absents, a.nb_retards, a.fk_user_creat, a.fk_user_modif, c.ref as cref, cr.ref as crref, cr.heure_debut,";
$sql .= " m.label_fr as m_fr, m.label_ar as m_ar FROM ".$p."ecole_appel a LEFT JOIN ".$p."ecole_classe c ON c.rowid = a.fk_classe";
$sql .= " LEFT JOIN ".$p."ecole_creneau cr ON cr.rowid = a.fk_creneau LEFT JOIN ".$p."ecole_matiere m ON m.rowid = a.fk_matiere";
$sql .= " WHERE a.entity IN (".getEntity('ecole_appel').") AND a.date_appel >= '".$db->escape($depuis)."' AND (a.fk_user_creat = ".((int) $moi->id)." OR a.fk_user_modif = ".((int) $moi->id).")";
$sql .= " ORDER BY a.date_appel DESC, cr.heure_debut DESC LIMIT 300";
$resql = $db->query($sql);
$lignes = array();
while ($resql && ($o = $db->fetch_object($resql))) {
	$lignes[] = $o;
}

pe_header($langs->trans('EspMesAppels'), $acces, $emp, $rubriques, 'mesappels');
$tot = array('a' => 0, 'r' => 0);
foreach ($lignes as $o) {
	$tot['a'] += (int) $o->nb_absents;
	$tot['r'] += (int) $o->nb_retards;
}
print '<div class="es-kpis"><div class="es-card es-kpi"><span>'.$langs->trans('EspAppels').'</span><b>'.count($lignes).'</b></div>';
print '<div class="es-card es-kpi"><span>'.$langs->trans('Absents').'</span><b>'.$tot['a'].'</b></div><div class="es-card es-kpi"><span>'.$langs->trans('Retards').'</span><b>'.$tot['r'].'</b></div></div>';

$jour = '';
foreach ($lignes as $o) {
	$d = substr($o->date_appel, 0, 10);
	if ($d !== $jour) {
		if ($jour !== '') {
			print '</div>';
		}
		$jour = $d;
		print '<h2 class="es-h2">'.dol_escape_htmltag(personnel_date_label($d)).'</h2><div class="es-list">';
	}
	$url = espace_page_url('appel/'.$d.'/'.espace_jeton('classe', (int) $o->fk_classe).'/'.espace_jeton('creneau', (int) $o->fk_creneau));
	print '<a class="es-card es-item" href="'.dol_escape_htmltag($url).'"><div class="es-item-main"><b>'.dol_escape_htmltag($o->cref.' · '.pe_label($o, 'm_')).'</b>';
	print '<span class="es-muted es-small" dir="ltr">'.dol_escape_htmltag($o->crref.' '.$o->heure_debut).'</span>';
	if ((int) $o->fk_user_creat !== (int) $moi->id) {
		print '<span class="es-muted es-small">'.$langs->trans('EspCorrigeParVous').'</span>';
	}
	print '</div><div class="es-item-side"><span class="es-chip '.($o->nb_absents ? 'es-chip-red' : 'es-chip-green').'" dir="ltr">'.dol_escape_htmltag($langs->transnoentities('NbAbsNbRet', (int) $o->nb_absents, (int) $o->nb_retards)).'</span></div></a>';
}
if ($jour !== '') {
	print '</div>';
}
if (empty($lignes)) {
	print espace_msg($langs->trans('EspAucunAppel'), 'info');
}

espace_footer();
$db->close();
