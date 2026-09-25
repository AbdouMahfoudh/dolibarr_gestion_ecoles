<?php
/**
 * Export PDF ou Excel d'une liste du module Salaires, dans la langue de l'utilisateur
 * (mêmes filtres et même tri que la liste affichée).
 *
 *   export.php?obj=<bulletin|lot|avance|pret>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=lot_bulletins&id=<lot>&format=<pdf|excel>
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/salaires/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');

$obj = GETPOST('obj', 'aZ09');
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';

if (!$user->hasRight('salaires', 'bulletin', 'exporter') || !$user->hasRight('salaires', 'bulletin', 'lire')) {
	accessforbidden();
}

// Langue d'abord : tous les libellés du document suivent la langue détectée
$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);
$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes'));

if ($obj === 'lot_bulletins') {
	$lot = new EcoleSalaireLot($db);
	if (GETPOSTINT('id') <= 0 || $lot->fetch(GETPOSTINT('id')) <= 0) {
		accessforbidden();
	}
	$ds = salaires_export_dataset_lot($db, $lot);
} else {
	$cfg = salaires_crud_config($obj);
	if (empty($cfg) || !ecole_crud_can($cfg, 'perm_read')) {
		accessforbidden();
	}
	$ds = ecole_export_dataset_list(new $cfg['class']($db), $cfg);
}

if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}
$db->close();
