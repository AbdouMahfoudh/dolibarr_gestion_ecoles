<?php
/**
 * Liste : creneau.
 * Fichier : custom/classes/creneau/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_creneau.class.php');

ecole_crud_list(new EcoleCreneau($db), ecole_crud_config('creneau'));
