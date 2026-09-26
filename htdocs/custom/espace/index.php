<?php
/**
 * Tableau de bord de l'espace parents, élèves et personnel : comptes par type, actifs, désactivés,
 * jamais connectés, connectés ces 7 derniers jours, dernières connexions, raccourcis.
 *
 * Fichier : custom/espace/index.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/dashboard.lib.php');

$langs->loadLangs(array('espace@espace', 'classes@classes', 'other'));

if (!$user->hasRight('espace', 'acces', 'lire')) {
	accessforbidden();
}
$p = $db->prefix();
$base = dol_buildpath('/espace/', 1);
$st = ecole_dash_espace();
$types = array(ESPACE_PARENT => 'EdComptesParents', ESPACE_ELEVE => 'EdComptesEleves', ESPACE_EMPLOYE => 'EdComptesPersonnel');

llxHeader('', $langs->trans('EdTableauEspace'), '', '', 0, 0, '', '', '', 'mod-espace page-index');
print ecole_dash_hero($langs->trans('EdTableauEspace'));

$kpis = array();
foreach ($types as $t => $l) {
	if (in_array($t, espace_types(), true)) {
		$kpis[] = array($langs->trans($l), isset($st['types'][$t]) ? $st['types'][$t] : 0, $t === ESPACE_PARENT ? 'fa-house-user' : ($t === ESPACE_ELEVE ? 'fa-user-graduate' : 'fa-id-badge'), $t === ESPACE_PARENT ? 'blue' : ($t === ESPACE_ELEVE ? 'purple' : 'teal'), $base.'list.php');
	}
}
$kpis[] = array($langs->trans('EdConnectes7j'), $st['connectes7'], 'fa-sign-in-alt', 'green', $base.'list.php');
$kpis[] = array($langs->trans('MenuJamaisConnectes'), $st['jamais'], 'fa-user-slash', 'orange', $base.'list.php?search_etat=jamais');
$kpis[] = array($langs->trans('EdComptesDesactives'), $st['desactives'], 'fa-ban', 'grey', $base.'list.php');
print ecole_dash_kpis($kpis);

print '<div class="ed-cols">';
$data = array();
foreach ($types as $t => $l) {
	if (!empty($st['types'][$t])) {
		$data[] = array($langs->trans($l), $st['types'][$t]);
	}
}
print ecole_dash_box($langs->trans('EdComptesParType'), 'fa-chart-pie', ecole_dash_graph('espace_types', 'pie', $data));
$total = array_sum($st['types']);
print ecole_dash_box($langs->trans('EdUtilisation'), 'fa-tasks', ecole_dash_rows(array(
	array($langs->trans('EdConnectes7j'), $st['connectes7'].' / '.$total, $total ? round(100 * $st['connectes7'] / $total) : 0),
	array($langs->trans('MenuJamaisConnectes'), $st['jamais'].' / '.$total, $total ? round(100 * $st['jamais'] / $total) : 0),
	array($langs->trans('EdComptesDesactives'), $st['desactives'].' / '.$total, $total ? round(100 * $st['desactives'] / $total) : 0),
)));

// Dernières connexions
$html = '';
if (ecole_dash_table('ecole_acces')) {
	$resql = $db->query("SELECT type, fk_cible, date_derniere_connexion FROM ".$p."ecole_acces WHERE entity IN (".getEntity('ecole_acces').") AND date_derniere_connexion IS NOT NULL ORDER BY date_derniere_connexion DESC LIMIT 8");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$c = espace_cible($db, $o->type, (int) $o->fk_cible);
		if (!$c) {
			continue;
		}
		$html .= '<div class="ed-row"><a href="'.$base.'acces.php?type='.urlencode($o->type).'&id='.((int) $o->fk_cible).'">'.dol_escape_htmltag($c->ref.' · '.ecole_label($c)).'</a><span class="opacitymedium">'.dol_print_date($db->jdate($o->date_derniere_connexion), 'dayhourshort', 'tzuserrel').'</span></div>';
	}
}
print ecole_dash_box($langs->trans('EdDernieresConnexions'), 'fa-history', $html !== '' ? $html : '<div class="ed-empty">'.$langs->trans('EdAucuneDonnee').'</div>', $base.'list.php');
print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array(true, $base.'list.php', $langs->trans('MenuEspaceParents'), 'fa-list'),
	array(true, $base.'list.php?search_etat=jamais', $langs->trans('MenuJamaisConnectes'), 'fa-user-slash'),
	array($user->hasRight('espace', 'config', 'gerer'), $base.'admin/setup.php', $langs->trans('MenuConfigEspace'), 'fa-cog'),
)));
print '</div>';

llxFooter();
$db->close();
