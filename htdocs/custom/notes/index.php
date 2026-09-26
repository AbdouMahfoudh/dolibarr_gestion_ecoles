<?php
/**
 * Tableau de bord du module Notes (direction, secrétariat : droit « voir les notes » ou « résultats ») :
 * évaluations, notes saisies, clôture des trimestres par classe, évaluations récentes, raccourcis.
 * Un enseignant qui ne fait que saisir arrive directement sur la saisie des notes.
 *
 * Fichier : custom/notes/index.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/dashboard.lib.php');

$voit = $user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'bulletin', 'lire');
if (!$voit) {
	$saisie = $user->hasRight('notes', 'note', 'saisir') || $user->hasRight('notes', 'note', 'saisirtout');
	header('Location: '.dol_buildpath($saisie ? '/notes/saisie.php' : '/notes/resultats.php', 1));
	exit;
}
$langs->loadLangs(array('notes@notes', 'classes@classes', 'other'));
$base = dol_buildpath('/notes/', 1);

llxHeader('', $langs->trans('EdTableauNotes'), '', '', 0, 0, '', '', '', 'mod-notes page-index');
print ecole_dash_hero($langs->trans('EdTableauNotes'), dol_escape_htmltag(ecole_annee_label(ecole_annee_vue())));
print ecole_annee_selecteur();

$st = ecole_dash_notes();
$kpis = array(
	array($langs->trans('EdEvaluations'), $st['evaluations'], 'fa-file-alt', 'purple', $base.'saisie.php'),
	array($langs->trans('EdNotesSaisies'), $st['notes'], 'fa-pen', 'blue', $base.'historique.php'),
);
for ($t = 1; $t <= 3; $t++) {
	$kpis[] = array($langs->trans('EdClotureTrimestre', $t), $st['cloturees'][$t].' / '.$st['classes'], 'fa-lock', $st['classes'] > 0 && $st['cloturees'][$t] >= $st['classes'] ? 'green' : 'orange', $base.'cloture.php');
}
print ecole_dash_kpis($kpis);

print '<div class="ed-cols">';
print ecole_dash_box($langs->trans('EdEvaluationsParTrimestre'), 'fa-chart-bar', ecole_dash_graph('notes_trim', 'bars', $st['par_trimestre'], array($langs->trans('EdEvaluations'))));
$lignes = array();
for ($t = 1; $t <= 3; $t++) {
	$lignes[] = array($langs->trans('EdClotureTrimestre', $t), $st['cloturees'][$t].' / '.$st['classes'], $st['classes'] > 0 ? round(100 * $st['cloturees'][$t] / $st['classes']) : 0);
}
print ecole_dash_box($langs->trans('EdAvancementClotures'), 'fa-tasks', ecole_dash_rows($lignes), $base.'cloture.php');

// Dernières évaluations créées
$html = '';
$sql = "SELECT v.fk_classe, v.trimestre, v.label, v.date_creation, c.ref as cref, m.label_fr, m.label_ar FROM ".MAIN_DB_PREFIX."ecole_evaluation v";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."ecole_classe c ON c.rowid = v.fk_classe LEFT JOIN ".MAIN_DB_PREFIX."ecole_matiere m ON m.rowid = v.fk_matiere";
$sql .= " WHERE v.entity IN (".getEntity('ecole_evaluation').") AND v.status = 1".ecole_annee_sql('v.annee')." ORDER BY v.date_creation DESC LIMIT 5";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$html .= '<div class="ed-row"><a href="'.$base.'saisie.php?fk_classe='.((int) $o->fk_classe).'&trimestre='.((int) $o->trimestre).'"><b>'.dol_escape_htmltag($o->cref).'</b> · '.dol_escape_htmltag(ecole_label($o)).' <span class="opacitymedium">T'.((int) $o->trimestre).'</span></a>';
	$html .= '<span class="opacitymedium">'.dol_print_date($db->jdate($o->date_creation), 'dayhour', 'tzuserrel').'</span></div>';
}
print ecole_dash_box($langs->trans('EdDernieresEvaluations'), 'fa-history', $html !== '' ? $html : '<div class="ed-empty">'.$langs->trans('EdAucuneDonnee').'</div>', $base.'historique.php');

print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($user->hasRight('notes', 'note', 'saisir') || $user->hasRight('notes', 'note', 'saisirtout'), $base.'saisie.php', $langs->trans('MenuSaisieNotes'), 'fa-pen'),
	array($user->hasRight('notes', 'bulletin', 'lire'), $base.'resultats.php', $langs->trans('MenuResultats'), 'fa-trophy'),
	array($user->hasRight('notes', 'bulletin', 'conseil'), $base.'conseil.php', $langs->trans('MenuConseil'), 'fa-users'),
	array($user->hasRight('notes', 'bulletin', 'lire'), $base.'difficultes.php', $langs->trans('MenuDifficultes'), 'fa-life-ring'),
	array(true, $base.'cloture.php', $langs->trans('MenuCloture'), 'fa-lock'),
	array($user->hasRight('notes', 'config', 'gerer'), $base.'modele/list.php', $langs->trans('MenuModelesBulletin'), 'fa-file-alt'),
)));
print '</div>';

llxFooter();
$db->close();
