<?php
/**
 * Tableau de bord du module Établissement : chiffres clés (classes, élèves, matières, salles, cours),
 * élèves par niveau, classes par niveau avec tarifs, examens à venir, raccourcis.
 *
 * Fichier : custom/classes/index.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/dashboard.lib.php');

$langs->loadLangs(array('classes@classes', 'other'));

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}

$p = MAIN_DB_PREFIX;
$base = dol_buildpath('/classes/', 1);

llxHeader('', $langs->trans('TableauDeBordEtablissement'), '', '', 0, 0, '', '', '', 'mod-classes page-index');
print ecole_dash_hero($langs->trans('TableauDeBordEtablissement'), dol_escape_htmltag(ecole_nom_etablissement()));

// Chiffres clés
$nbClasses = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_classe WHERE entity IN (".getEntity('ecole_classe').") AND status = 1");
$nbMatieres = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_matiere WHERE entity IN (".getEntity('ecole_matiere').") AND status = 1");
$nbSalles = count(ecole_salles($db, true));
$nbCours = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_edt_cours WHERE entity IN (".getEntity('ecole_classe').")");
$minutes = ecole_minutes_semaine($db, "e.entity IN (".getEntity('ecole_classe').")");
$nbProfs = count(ecole_users_categories($db, array('enseignant')));
$nbEleves = isModEnabled('eleves') ? (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND status IN (1,3)") : 0;
$kpis = array(
	array($langs->trans('EdClassesActives'), $nbClasses, 'fa-chalkboard', 'blue', $base.'classe/list.php'),
	array($langs->trans('EdMatieres'), $nbMatieres, 'fa-book', 'purple', $base.'matiere/list.php'),
	array($langs->trans('EdSalles'), $nbSalles, 'fa-door-open', 'teal', $base.'salle/list.php'),
	array($langs->trans('EdEnseignants'), $nbProfs, 'fa-chalkboard-teacher', 'orange', ''),
	array($langs->trans('EdCoursSemaine'), $nbCours, 'fa-calendar-alt', 'green', $base.'classe/emplois.php', $langs->trans('HeuresParSemaine', ecole_duree($minutes))),
);
if (isModEnabled('eleves')) {
	array_splice($kpis, 1, 0, array(array($langs->trans('EdElevesInscrits'), $nbEleves, 'fa-user-graduate', 'pink', dol_buildpath('/eleves/eleve/list.php', 1).'?search_status=1,3')));
}
print ecole_dash_kpis($kpis);

print '<div class="ed-cols">';

// Élèves par niveau
if (isModEnabled('eleves')) {
	$st = ecole_dash_eleves();
	$data = array();
	foreach ($st['niveaux'] as $n) {
		$data[] = array($n[0], $n[1]);
	}
	print ecole_dash_box($langs->trans('EdElevesParNiveau'), 'fa-chart-bar', ecole_dash_graph('etab_niveaux', 'bars', $data, array($langs->trans('EdElevesInscrits'))));
}

// Classes par niveau (nombre, mensualité, frais d'inscription)
$lignes = array();
$sql = "SELECT n.rowid, n.label_fr, n.label_ar, COUNT(c.rowid) as nb, MIN(c.mensualite) as mini, MAX(c.mensualite) as maxi";
$sql .= " FROM ".$p."ecole_niveau n LEFT JOIN ".$p."ecole_classe c ON c.fk_niveau = n.rowid AND c.status = 1";
$sql .= " WHERE n.entity IN (".getEntity('ecole_niveau').") AND n.status = 1 GROUP BY n.rowid, n.label_fr, n.label_ar, n.position ORDER BY n.position";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$prix = '';
	if ($o->nb > 0) {
		$prix = ((float) $o->mini === (float) $o->maxi) ? price($o->mini) : price($o->mini).' - '.price($o->maxi);
		$prix = ' <span class="opacitymedium small">'.$langs->trans('Mensualite').' : '.$prix.' '.$conf->currency.'</span>';
	}
	$lignes[] = array(dol_escape_htmltag(ecole_label($o)).$prix, (int) $o->nb.' '.$langs->trans('EcoleClasses'), $nbClasses > 0 ? round(100 * $o->nb / $nbClasses) : 0);
}
print ecole_dash_box($langs->trans('EdClassesParNiveau'), 'fa-layer-group', ecole_dash_rows($lignes), $base.'classe/list.php');

// Examens des 7 prochains jours
$auj = ecole_dash_aujourdhui();
$html = '';
foreach ($auj['examens'] as $e) {
	$html .= '<div class="ed-row"><span><b>'.dol_escape_htmltag($e->cref).'</b> · '.dol_escape_htmltag(ecole_label($e)).'</span><span class="opacitymedium">'.dol_print_date($db->jdate($e->date_examen), 'day').' '.dol_escape_htmltag($e->heure_debut).'</span></div>';
}
print ecole_dash_box($langs->trans('EdExamensAVenir'), 'fa-file-signature', $html !== '' ? $html : '<div class="ed-empty">'.$langs->trans('EdAucunExamen').'</div>');

// Raccourcis
print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($user->hasRight('classes', 'ecrire'), $base.'classe/card.php?action=create', $langs->trans('MenuNouvelleClasse'), 'fa-plus'),
	array(true, $base.'classe/emplois.php', $langs->trans('MenuEmploisDuTemps'), 'fa-calendar-alt'),
	array(true, $base.'classe/tarifs.php', $langs->trans('MenuTarifs'), 'fa-money-bill-wave'),
	array(true, $base.'salle/disponibilite.php', $langs->trans('MenuDisponibilite'), 'fa-door-open'),
	array($user->hasRight('classes', 'ecrire'), $base.'matiere/card.php?action=create', $langs->trans('MenuNouvelleMatiere'), 'fa-book'),
	array($user->hasRight('classes', 'config'), $base.'niveau/list.php', $langs->trans('MenuNiveaux'), 'fa-layer-group'),
	array($user->hasRight('classes', 'config'), $base.'creneau/list.php', $langs->trans('MenuCreneaux'), 'fa-clock'),
	array($user->hasRight('classes', 'config'), $base.'pdf_modele/list.php', $langs->trans('MenuModelesPdf'), 'fa-file-pdf'),
	array($user->hasRight('classes', 'config'), $base.'admin/setup.php', $langs->trans('MenuReglages'), 'fa-cog'),
)));

print '</div>';

llxFooter();
$db->close();
