<?php
/**
 * Tableau de bord du module Élèves : chiffres clés (inscrits, pré-inscrits, attente, dossiers incomplets,
 * impayés), graphiques (statuts, filles / garçons, élèves par niveau), la journée (appel, absents, retards),
 * paiements du mois, effectifs et places par classe, raccourcis.
 *
 * Fichier : custom/eleves/index.php
 */

require 'init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/classes/core/lib/dashboard.lib.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('eleves', 'eleve', 'lire')) {
	accessforbidden();
}

$p = MAIN_DB_PREFIX;
$listurl = dol_buildpath('/eleves/eleve/list.php', 1);
$base = dol_buildpath('/eleves/', 1);

llxHeader('', $langs->trans('EdTableauEleves'), '', '', 0, 0, '', '', '', 'mod-eleves page-index');
print ecole_dash_hero($langs->trans('EdTableauEleves'), dol_escape_htmltag(eleves_annee_label(eleves_annee_scolaire())));

$st = ecole_dash_eleves();
$nb = function ($s) use ($st) {
	return isset($st['statut'][$s]) ? $st['statut'][$s] : 0;
};
$canpaie = $user->hasRight('eleves', 'paiement', 'lire');
$canabs = $user->hasRight('eleves', 'absence', 'lire');
$kpis = array(
	array($langs->trans('EdElevesInscrits'), $nb(1) + $nb(3), 'fa-user-graduate', 'blue', $listurl.'?search_status=1,3', $langs->trans('EdFillesGarcons', $st['filles'], $st['garcons'])),
	array($langs->trans('StatutPreinscrit'), $nb(0), 'fa-user-plus', 'orange', $listurl.'?search_status=0', $langs->trans('EdNouveaux30j', $st['nouveaux'])),
	array($langs->trans('StatutAttente'), $nb(2), 'fa-hourglass-half', 'purple', $listurl.'?search_status=2'),
	array($langs->trans('EdDossiersIncomplets'), $st['incomplets'], 'fa-folder-open', 'grey', $user->hasRight('eleves', 'document', 'lire') ? $listurl.'?manquantes=1&search_status=0,1,2,3' : ''),
);
if ($canpaie) {
	$pay = ecole_dash_paiements();
	$kpis[] = array($langs->trans('EdEncaisseMois'), ecole_dash_montant($pay['mois']), 'fa-coins', 'green', $base.'recu/list.php', $langs->trans('EdAujourdhuiMontant', ecole_dash_montant($pay['jour'])));
	if ($user->hasRight('eleves', 'paiement', 'impayes')) {
		$kpis[] = array($langs->trans('EdImpayes'), ecole_dash_montant($pay['impaye']), 'fa-exclamation-triangle', 'red', $base.'impayes.php', $langs->trans('EdNbEleves', $pay['nb_impayes']));
	}
}
print ecole_dash_kpis($kpis);

print '<div class="ed-cols">';
// Répartition par statut
$labels = EcoleEleve::statusLabels();
$data = array();
foreach ($st['statut'] as $s => $n) {
	if ($n > 0 && isset($labels[$s])) {
		$data[] = array($langs->trans($labels[$s][0]), $n);
	}
}
print ecole_dash_box($langs->trans('EdElevesParStatut'), 'fa-chart-pie', ecole_dash_graph('eleves_statut', 'pie', $data));
// Élèves par niveau
$data = array();
foreach ($st['niveaux'] as $n) {
	$data[] = array($n[0], $n[1]);
}
print ecole_dash_box($langs->trans('EdElevesParNiveau'), 'fa-chart-bar', ecole_dash_graph('eleves_niveaux', 'bars', $data, array($langs->trans('EdElevesInscrits'))));
// La journée
if ($canabs) {
	$auj = ecole_dash_aujourdhui();
	print ecole_dash_box($langs->trans('EdAujourdhui'), 'fa-calendar-day', ecole_dash_rows(array(
		array($langs->trans('EdAppelsFaits'), $auj['appels'].' / '.$auj['cours'], $auj['cours'] > 0 ? round(100 * $auj['appels'] / $auj['cours']) : null),
		array('<a href="'.$base.'absence/list.php">'.$langs->trans('EdAbsents').'</a>', $auj['absents']),
		array($langs->trans('EdRetards'), $auj['retards']),
		array($langs->trans('EdRenvoyes'), $auj['renvoyes']),
	)), $base.'appel.php');
}
// Encaissements par mois
if ($canpaie) {
	print ecole_dash_box($langs->trans('EdEncaissementsParMois'), 'fa-chart-line', ecole_dash_graph('eleves_encaisse', 'bars', $pay['par_mois'], array($langs->trans('EdEncaisse'))), $base.'recu/list.php');
}
print '</div>';

// Effectifs et places par classe
$occ = implode(',', EcoleEleve::statusOccupantPlace());
$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, c.effectif_max,";
$sql .= " SUM(CASE WHEN e.status IN (".$occ.") THEN 1 ELSE 0 END) as effectif,";
$sql .= " SUM(CASE WHEN e.status = ".EcoleEleve::STATUS_PREINSCRIT." THEN 1 ELSE 0 END) as preinscrits,";
$sql .= " SUM(CASE WHEN e.status = ".EcoleEleve::STATUS_ATTENTE." THEN 1 ELSE 0 END) as attente,";
$sql .= " SUM(CASE WHEN e.status IN (".$occ.") AND e.sexe = 'F' THEN 1 ELSE 0 END) as filles,";
$sql .= " SUM(CASE WHEN e.status IN (".$occ.") AND e.sexe = 'M' THEN 1 ELSE 0 END) as garcons";
$sql .= " FROM ".$p."ecole_classe c";
$sql .= " LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
$sql .= " LEFT JOIN ".$p."ecole_eleve e ON e.fk_classe = c.rowid";
$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1";
$sql .= " GROUP BY c.rowid, c.ref, c.label_fr, c.label_ar, c.effectif_max, n.position ORDER BY n.position, c.rowid";
$resql = $db->query($sql);

print '<div class="ed-box"><div class="ed-box-h"><i class="fas fa-chalkboard"></i> '.$langs->trans('EdEffectifsParClasse').'</div><div class="ed-box-b"><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td>';
print '<td class="right">'.$langs->trans('Inscrits').'</td><td class="right">'.$langs->trans('Filles').'</td><td class="right">'.$langs->trans('Garcons').'</td>';
print '<td class="right">'.$langs->trans('EffectifMax').'</td><td class="right">'.$langs->trans('PlacesLibres').'</td>';
print '<td class="right">'.$langs->trans('StatutPreinscrit').'</td><td class="right">'.$langs->trans('StatutAttente').'</td></tr>';
$tot = array('effectif' => 0, 'filles' => 0, 'garcons' => 0, 'preinscrits' => 0, 'attente' => 0);
while ($resql && ($o = $db->fetch_object($resql))) {
	foreach ($tot as $k => $v) {
		$tot[$k] += (int) $o->$k;
	}
	$max = ((int) $o->effectif_max > 0) ? (int) $o->effectif_max : EcoleClasse::effectifMaxDefaut();
	$libres = ($max === null) ? '<span class="opacitymedium">'.$langs->trans('Illimite').'</span>' : max(0, $max - (int) $o->effectif);
	if ($max !== null && (int) $o->effectif >= $max) {
		$libres = '<span class="badge badge-danger">'.$langs->trans('Complet').'</span>';
	}
	$u = $listurl.'?search_fk_classe='.((int) $o->rowid);
	print '<tr class="oddeven"><td class="nowraponall"><a href="'.$u.'&search_status=1,3" title="'.dol_escape_htmltag(ecole_label($o)).'">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a></td>';
	print '<td class="right">'.((int) $o->effectif).'</td><td class="right">'.((int) $o->filles).'</td><td class="right">'.((int) $o->garcons).'</td>';
	print '<td class="right">'.($max === null ? '' : $max).'</td><td class="right">'.$libres.'</td>';
	print '<td class="right">'.((int) $o->preinscrits ? '<a href="'.$u.'&search_status=0">'.((int) $o->preinscrits).'</a>' : '0').'</td>';
	print '<td class="right">'.((int) $o->attente ? '<a href="'.$u.'&search_status=2">'.((int) $o->attente).'</a>' : '0').'</td></tr>';
}
print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.$tot['effectif'].'</td><td class="right">'.$tot['filles'].'</td><td class="right">'.$tot['garcons'].'</td>';
print '<td></td><td></td><td class="right">'.$tot['preinscrits'].'</td><td class="right">'.$tot['attente'].'</td></tr>';
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('EffectifAide').'</span></div></div>';


print '<br>'.ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($user->hasRight('eleves', 'eleve', 'creer'), $base.'eleve/card.php?action=create', $langs->trans('MenuNouvelEleve'), 'fa-user-plus'),
	array($user->hasRight('eleves', 'paiement', 'encaisser'), $base.'caisse.php', $langs->trans('MenuCaisse'), 'fa-cash-register'),
	array($user->hasRight('eleves', 'absence', 'appel'), $base.'appel.php', $langs->trans('MenuAppel'), 'fa-clipboard-check'),
	array($user->hasRight('eleves', 'paiement', 'impayes'), $base.'impayes.php', $langs->trans('MenuImpayes'), 'fa-exclamation-triangle'),
	array($user->hasRight('eleves', 'absence', 'lire'), $base.'signales.php', $langs->trans('MenuElevesSignales'), 'fa-flag'),
	array($user->hasRight('eleves', 'responsable', 'lire'), $base.'responsable/list.php', $langs->trans('MenuResponsables'), 'fa-users'),
	array($user->hasRight('eleves', 'config', 'gerer'), $base.'admin/setup.php', $langs->trans('MenuConfiguration'), 'fa-cog'),
)));

llxFooter();
$db->close();
