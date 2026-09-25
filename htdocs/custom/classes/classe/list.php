<?php
/**
 * Liste : classe.
 * Fichier : custom/classes/classe/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_classe.class.php');

ecole_crud_list(new EcoleClasse($db), ecole_crud_config('classe'));
