<?php
/**
 * Entrée de l'espace du personnel : même aiguilleur que l'espace des parents et élèves, mais à une autre adresse
 * (…/personnel, règle de réécriture « personnel/... ») avec sa propre connexion et sa propre session.
 * Les pages des parents et élèves n'y sont pas accessibles, et celles du personnel ne le sont pas à …/espace.
 *
 * Fichier : custom/espace/portail/personnel.php
 */

define('ESPACE_ZONE', 'personnel');
require __DIR__.'/router.php';
