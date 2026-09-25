<?php
/**
 * Liste : niveau.
 * Fichier : custom/classes/niveau/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_niveau.class.php');

ecole_crud_list(new EcoleNiveau($db), ecole_crud_config('niveau'));
