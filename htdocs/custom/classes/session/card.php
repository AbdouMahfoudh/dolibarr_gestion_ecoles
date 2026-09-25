<?php
/**
 * Fiche : session.
 * Fichier : custom/classes/session/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_session.class.php');

ecole_crud_card(new EcoleSession($db), ecole_crud_config('session'));
