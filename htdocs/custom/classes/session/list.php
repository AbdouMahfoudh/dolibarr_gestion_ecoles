<?php
/**
 * Liste : session.
 * Fichier : custom/classes/session/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_session.class.php');

ecole_crud_list(new EcoleSession($db), ecole_crud_config('session'));
