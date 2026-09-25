<?php
/**
 * Liste : matiere.
 * Fichier : custom/classes/matiere/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_matiere.class.php');

ecole_crud_list(new EcoleMatiere($db), ecole_crud_config('matiere'));
