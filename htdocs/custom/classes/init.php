<?php
/**
 * Point d'entrée commun des pages du module Classes : charge l'environnement Dolibarr
 * et les bibliothèques du module. Chaque page commence par « require '../init.php'; ».
 *
 * Fichier : custom/classes/init.php
 */

$res = defined('DOL_DOCUMENT_ROOT') ? 1 : 0; // déjà chargé (ex. tests en ligne de commande)
$tmp = realpath(__FILE__);
$i = strlen($tmp) - 1;
while (!$res && $i > 0) {
	if (substr($tmp, $i, 1) === DIRECTORY_SEPARATOR && file_exists(substr($tmp, 0, $i)."/main.inc.php")) {
		$res = @include substr($tmp, 0, $i)."/main.inc.php";
		break;
	}
	$i--;
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/classes/core/lib/classes.lib.php');
dol_include_once('/classes/core/lib/crud.lib.php');
dol_include_once('/classes/core/lib/edt.lib.php');
