<?php
/**
 * Tableau de bord du module Salaires : bulletins du mois (brouillon, validés, payés), masse salariale,
 * évolution sur six mois, avances et prêts en cours, derniers bulletins, raccourcis.
 *
 * Fichier : custom/salaires/index.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/dashboard.lib.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'other'));

if (!$user->hasRight('salaires', 'bulletin', 'lire') && !$user->hasRight('salaires', 'avance', 'lire')) {
	accessforbidden();
}
$p = $db->prefix();
$base = dol_buildpath('/salaires/', 1);
$st = ecole_dash_salaires();
$moisLabel = dol_print_date(dol_now(), '%B %Y', 'tzuser');

llxHeader('', $langs->trans('EdTableauSalaires'), '', '', 0, 0, '', '', '', 'mod-salaires page-index');
print ecole_dash_hero($langs->trans('EdTableauSalaires'), dol_escape_htmltag(ucfirst($moisLabel)));

// Avances et prêts en cours (reste à retenir)
$avances = array('nb' => 0, 'reste' => 0);
if ($user->hasRight('salaires', 'avance', 'lire')) {
	foreach (array('ecole_salaire_avance', 'ecole_salaire_pret') as $t) {
		if (ecole_dash_table($t)) {
			$resql = $db->query("SELECT COUNT(*) as nb, SUM(montant) as m FROM ".$p.$t." WHERE status = 1");
			$o = $resql ? $db->fetch_object($resql) : null;
			$avances['nb'] += $o ? (int) $o->nb : 0;
			$avances['reste'] += $o ? (float) $o->m : 0;
		}
	}
}
$list = $base.'bulletin/list.php';
print ecole_dash_kpis(array(
	array($langs->trans('EdMasseSalarialeMois'), ecole_dash_montant($st['net']), 'fa-money-check-alt', 'blue', $list.'?sortfield=t.rowid&sortorder=DESC'),
	array($langs->trans('EdBulletinsBrouillon'), $st['brouillon'], 'fa-pencil-alt', 'grey', $list.'?search_status=0'),
	array($langs->trans('EdBulletinsAPayer'), $st['valide'], 'fa-hourglass-half', 'orange', $list.'?search_status=1'),
	array($langs->trans('EdBulletinsPayesMois'), $st['paye'], 'fa-check-circle', 'green', $list.'?search_status=2', ecole_dash_montant($st['net_paye'])),
	array($langs->trans('EdAvancesPretsCours'), $avances['nb'], 'fa-hand-holding-usd', 'purple', $base.'avance/list.php?search_extra_etat=encours'),
));

print '<div class="ed-cols">';
print ecole_dash_box($langs->trans('EdMasseSalariale6Mois'), 'fa-chart-bar', ecole_dash_graph('sal_mois', 'bars', $st['par_mois'], array($langs->trans('NetAPayer'))));
$total = $st['brouillon'] + $st['valide'] + $st['paye'];
print ecole_dash_box($langs->trans('EdAvancementMois'), 'fa-tasks', ecole_dash_rows(array(
	array($langs->trans('EdBulletinsBrouillon'), $st['brouillon'], $total ? round(100 * $st['brouillon'] / $total) : 0),
	array($langs->trans('EdBulletinsAPayer'), $st['valide'], $total ? round(100 * $st['valide'] / $total) : 0),
	array($langs->trans('EdBulletinsPayesMois'), $st['paye'], $total ? round(100 * $st['paye'] / $total) : 0),
)));

// Derniers bulletins
$html = '';
if (ecole_dash_table('ecole_salaire')) {
	dol_include_once('/salaires/class/ecole_salaire.class.php');
	$b = new EcoleSalaire($db);
	$resql = $db->query("SELECT s.rowid, s.ref, s.mois, s.net, s.status, e.nom_fr, e.nom_ar FROM ".$p."ecole_salaire s LEFT JOIN ".$p."ecole_employe e ON e.rowid = s.fk_employe WHERE s.entity IN (".getEntity('ecole_salaire').") ORDER BY s.tms DESC LIMIT 8");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$html .= '<div class="ed-row"><a href="'.$base.'bulletin/card.php?id='.((int) $o->rowid).'"><b>'.dol_escape_htmltag($o->ref).'</b> · '.dol_escape_htmltag(ecole_label($o)).'</a><span>'.ecole_dash_montant($o->net).' '.$b->LibStatut((int) $o->status, 5).'</span></div>';
	}
}
print ecole_dash_box($langs->trans('EdDerniersBulletins'), 'fa-history', $html !== '' ? $html : '<div class="ed-empty">'.$langs->trans('EdAucuneDonnee').'</div>', $list.'?sortfield=t.rowid&sortorder=DESC');

print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($user->hasRight('salaires', 'bulletin', 'creer'), $base.'lot/card.php?action=create', $langs->trans('MenuPreparerSalaires'), 'fa-layer-group'),
	array($user->hasRight('salaires', 'bulletin', 'creer'), $base.'bulletin/card.php?action=create', $langs->trans('MenuNouveauBulletin'), 'fa-plus'),
	array($user->hasRight('salaires', 'avance', 'donner'), $base.'avance/card.php?action=create', $langs->trans('NouvelleAvance'), 'fa-hand-holding-usd'),
	array($user->hasRight('salaires', 'avance', 'donner'), $base.'pret/card.php?action=create', $langs->trans('NouveauPret'), 'fa-piggy-bank'),
	array($user->hasRight('salaires', 'config', 'gerer'), $base.'admin/setup.php', $langs->trans('MenuConfigSalaires'), 'fa-cog'),
)));
print '</div>';

llxFooter();
$db->close();
