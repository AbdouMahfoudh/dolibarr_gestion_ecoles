<?php
/**
 * Accueil du module Élèves : élèves par statut, effectifs et places par classe, accès rapides.
 *
 * Fichier : custom/eleves/index.php
 */

require 'init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('eleves', 'eleve', 'lire')) {
	accessforbidden();
}

$p = MAIN_DB_PREFIX;
$listurl = dol_buildpath('/eleves/eleve/list.php', 1);

llxHeader('', $langs->trans('Eleves'), '', '', 0, 0, '', '', '', 'mod-eleves page-index');
print load_fiche_titre($langs->trans('Eleves'), '', 'fa-user-graduate');

print '<div class="fichecenter"><div class="fichethirdleft">';

// Élèves par statut
$nb = array();
$resql = $db->query("SELECT status, COUNT(*) as nb FROM ".$p."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") GROUP BY status");
while ($resql && ($o = $db->fetch_object($resql))) {
	$nb[(int) $o->status] = (int) $o->nb;
}
$eleve = new EcoleEleve($db);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('StatutEleve').'</td><td class="right">'.$langs->trans('Nombre').'</td></tr>';
$total = 0;
foreach (EcoleEleve::statusLabels() as $s => $def) {
	$n = isset($nb[$s]) ? $nb[$s] : 0;
	$total += $n;
	print '<tr class="oddeven"><td><a href="'.$listurl.'?search_status='.$s.'">'.$eleve->LibStatut($s, 5).'</a></td><td class="right">'.$n.'</td></tr>';
}
print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.$total.'</td></tr>';
print '</table><br>';

// Pièces manquantes
if ($user->hasRight('eleves', 'document', 'lire')) {
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_eleve t WHERE t.entity IN (".getEntity('ecole_eleve').") AND t.status NOT IN (".implode(',', EcoleEleve::statusSortis()).") AND ".EcoleEleve::sqlPiecesManquantes($db));
	$o = $resql ? $db->fetch_object($resql) : null;
	print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('PiecesDossier').'</td><td class="right">'.$langs->trans('Nombre').'</td></tr>';
	print '<tr class="oddeven"><td><a href="'.$listurl.'?manquantes=1&search_status=0,1,2,3">'.$langs->trans('ElevesDossierIncomplet').'</a></td><td class="right">'.($o ? (int) $o->nb : 0).'</td></tr>';
	print '</table><br>';
}

// Accès rapides
$links = array(
	array('eleve', 'lire', '/eleves/eleve/list.php', 'MenuEleves'),
	array('eleve', 'creer', '/eleves/eleve/card.php?action=create', 'MenuNouvelEleve'),
	array('eleve', 'lire', '/eleves/eleve/list.php?search_status=0', 'MenuPreinscriptions'),
	array('eleve', 'lire', '/eleves/eleve/list.php?search_status=2', 'MenuListeAttente'),
	array('responsable', 'lire', '/eleves/responsable/list.php', 'MenuResponsables'),
	array('responsable', 'creer', '/eleves/responsable/card.php?action=create', 'MenuNouveauResponsable'),
	array('config', 'gerer', '/eleves/admin/setup.php', 'MenuConfiguration'),
);
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('AccesRapides').'</td></tr>';
foreach ($links as $l) {
	if ($user->hasRight('eleves', $l[0], $l[1])) {
		print '<tr class="oddeven"><td><a href="'.dol_buildpath($l[2], 1).'">'.$langs->trans($l[3]).'</a></td></tr>';
	}
}
print '</table>';

print '</div><div class="fichetwothirdright">';

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

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
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
print '<span class="opacitymedium small">'.$langs->trans('EffectifAide').'</span>';

print '</div></div>';

llxFooter();
$db->close();
