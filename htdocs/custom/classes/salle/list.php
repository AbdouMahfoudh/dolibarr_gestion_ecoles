<?php
/**
 * Liste : salle.
 * Fichier : custom/classes/salle/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_salle.class.php');

ecole_crud_list(new EcoleSalle($db), ecole_crud_config('salle'));
