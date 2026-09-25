<?php
/**
 * Point d'entrée commun des pages de l'espace du personnel : démarrage de l'Espace (session propre, sans menus
 * Dolibarr) puis fonctions de l'espace du personnel. Ces pages ne s'ouvrent que par les adresses de l'espace.
 *
 * Fichier : custom/personnel/portail/init_espace.php
 */

require_once dirname(__DIR__, 2).'/espace/portail/boot.php';

if (!isModEnabled('personnel')) {
	http_response_code(404);
	print 'Not available';
	exit;
}
dol_include_once('/personnel/core/lib/espace_personnel.lib.php');
