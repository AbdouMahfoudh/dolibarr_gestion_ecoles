<?php
/**
 * Export PDF ou Excel des listes du module Espace parents (mêmes filtres que l'écran), dans la langue de l'utilisateur.
 *
 *   export.php?obj=acces&format=<pdf|excel>[&filtres de la liste][&lang=fr|ar]
 *
 * Fichier : custom/espace/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');

if (GETPOST('obj', 'aZ09') !== 'acces' || !$user->hasRight('espace', 'acces', 'lire')) {
	accessforbidden();
}
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';

$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);
$langs->loadLangs(array('espace@espace', 'eleves@eleves', 'classes@classes'));

$ds = espace_export_dataset_acces($db);
if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}
$db->close();
