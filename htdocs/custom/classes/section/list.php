<?php
/**
 * Liste : section.
 * Fichier : custom/classes/section/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_section.class.php');

ecole_crud_list(new EcoleSection($db), ecole_crud_config('section'));
