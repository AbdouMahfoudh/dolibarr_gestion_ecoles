<?php
/**
 * Déconnexion de l'espace parents et élèves.
 *
 * Fichier : custom/espace/portail/logout.php
 */

require 'boot.php';

espace_session_fermer();
header('Location: '.espace_page_url('connexion', array('deconnecte' => 1)));
$db->close();
exit;
