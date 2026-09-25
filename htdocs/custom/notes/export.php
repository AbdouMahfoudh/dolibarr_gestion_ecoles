<?php
/**
 * Export PDF ou Excel des listes du module Notes, dans la langue de l'utilisateur
 * (mêmes filtres que la liste affichée).
 *
 *   export.php?obj=resultats&fk_classe=<id>&periode=<0..3>&format=<pdf|excel>
 *   export.php?obj=suivi_resultats&periode=<0..3>&format=...
 *   export.php?obj=difficultes&periode=<0..3>[&fk_classe=<id>][&seuil=<n>]&format=...
 *   export.php?obj=conseil&fk_classe=<id>&periode=<0..3>&format=...
 *   export.php?obj=historique[&filtres de l'historique]&format=...
 *   export.php?obj=cloture&trimestre=<1..3>&format=...
 *   export.php?obj=saisie_classes&format=...
 *   export.php?obj=saisie_matieres&fk_classe=<id>&trimestre=<1..3>&format=...
 *   export.php?obj=grille&fk_classe=<id>&fk_matiere=<id>&trimestre=<1..3>&format=...
 *   export.php?obj=eleve&id=<élève>&periode=<0..3>&format=...
 *   export.php?obj=<mention|distinction|modele>&format=...[&filtres de la liste]
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/notes/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/notes/core/lib/exports.lib.php');

$obj = GETPOST('obj', 'aZ09');
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';
$periode = GETPOSTINT('periode');
if (!in_array($periode, array(0, 1, 2, 3), true)) {
	$periode = 1;
}
$trimestre = GETPOSTINT('trimestre');
if (!in_array($trimestre, array(1, 2, 3), true)) {
	$trimestre = 1;
}
$saisie = notes_voit_tout($user) || $user->hasRight('notes', 'note', 'saisir');

// Droits de chaque export (les mêmes que pour voir la liste)
$droits = array(
	'resultats' => $user->hasRight('notes', 'bulletin', 'lire'),
	'suivi_resultats' => $user->hasRight('notes', 'bulletin', 'lire'),
	'difficultes' => $user->hasRight('notes', 'bulletin', 'lire'),
	'conseil' => $user->hasRight('notes', 'bulletin', 'conseil'),
	'historique' => $saisie,
	'cloture' => $user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'cloture', 'gerer'),
	'saisie_classes' => $saisie,
	'saisie_matieres' => $saisie,
	'grille' => $saisie,
	'eleve' => $user->hasRight('notes', 'bulletin', 'lire') || $user->hasRight('notes', 'note', 'lire'),
);
if (isset($droits[$obj])) {
	if (!$droits[$obj]) {
		accessforbidden();
	}
} else {
	$cfg = notes_crud_config($obj);
	if (empty($cfg) || !ecole_crud_can($cfg, 'perm_read')) {
		accessforbidden();
	}
}

// Langue d'abord : tous les libellés du document suivent la langue détectée
$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);
$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes'));

$fk_classe = GETPOSTINT('fk_classe');
$classesRes = in_array($obj, array('resultats', 'conseil', 'difficultes'), true) ? notes_classes_resultats($db, $user) : notes_classes($db, $user);
if (in_array($obj, array('resultats', 'conseil', 'saisie_matieres', 'grille'), true) && !isset($classesRes[$fk_classe])) {
	accessforbidden();
}

if ($obj === 'resultats') {
	$ds = notes_dataset_resultats($db, $classesRes[$fk_classe], $periode);
} elseif ($obj === 'suivi_resultats') {
	$ds = notes_dataset_suivi_resultats($db, $user, $periode);
} elseif ($obj === 'difficultes') {
	$seuil = GETPOST('seuil', 'alpha') !== '' ? (float) price2num(GETPOST('seuil', 'alpha')) : (float) getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10');
	$ds = notes_dataset_difficultes($db, $user, $periode, $seuil, max(0, $fk_classe));
} elseif ($obj === 'conseil') {
	$ds = notes_dataset_conseil($db, $classesRes[$fk_classe], $periode);
} elseif ($obj === 'historique') {
	$ds = notes_dataset_historique($db, $user, notes_historique_filtres(false));
} elseif ($obj === 'cloture') {
	$ds = notes_dataset_cloture($db, $user, $trimestre);
} elseif ($obj === 'saisie_classes') {
	$ds = notes_dataset_saisie_classes($db, $user);
} elseif ($obj === 'saisie_matieres') {
	$ds = notes_dataset_saisie_matieres($db, $user, $classesRes[$fk_classe], $trimestre);
} elseif ($obj === 'grille') {
	if (!notes_peut_voir($db, $user, $fk_classe, GETPOSTINT('fk_matiere'))) {
		accessforbidden();
	}
	$ds = notes_dataset_grille($db, $classesRes[$fk_classe], GETPOSTINT('fk_matiere'), $trimestre);
} elseif ($obj === 'eleve') {
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$eleve = new EcoleEleve($db);
	if (GETPOSTINT('id') <= 0 || $eleve->fetch(GETPOSTINT('id')) <= 0) {
		accessforbidden();
	}
	$ds = notes_dataset_eleve($db, $eleve, $periode);
} else {
	dol_include_once('/notes/class/'.array('mention' => 'ecole_note_mention', 'distinction' => 'ecole_note_distinction', 'modele' => 'ecole_bulletin_modele')[$obj].'.class.php');
	$ds = ecole_export_dataset_list(new $cfg['class']($db), $cfg);
}

if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}
$db->close();
