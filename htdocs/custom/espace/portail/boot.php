<?php
/**
 * Point d'entrée commun des pages de l'espace parents et élèves.
 *
 * Ces pages n'utilisent pas la connexion de l'interface de gestion (NOLOGIN) : elles ont leur propre
 * connexion et leur propre session (voir portail.lib.php). Les données de l'école sont lues avec
 * les fonctions des modules Élèves, Classes et Notes, toujours limitées aux élèves de l'accès connecté.
 *
 * Fichier : custom/espace/portail/boot.php
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', 1);
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', 1);
}

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
if (!isModEnabled('espace') || !isModEnabled('eleves')) {
	http_response_code(404);
	print 'Not available';
	exit;
}

dol_include_once('/classes/core/lib/classes.lib.php');
dol_include_once('/eleves/core/lib/eleves.lib.php');
dol_include_once('/espace/core/lib/espace.lib.php');
dol_include_once('/espace/core/lib/portail.lib.php');
// Année scolaire affichée : l'année en cours
ecole_annee_vue_forcer(ecole_annee_active());

// Les pages ne s'ouvrent que par les adresses de l'espace (aiguilleur) : une ancienne adresse en .php est renvoyée à l'accueil
if (!defined('ESPACE_ROUTER')) {
	header('Location: '.espace_route_url(''));
	exit;
}
