<?php
/**
 * Liste : types de sanctions.
 * Fichier : custom/eleves/sanction_type/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_sanction_type.class.php');

ecole_crud_list(new EcoleSanctionType($db), eleves_crud_config('sanction_type'));
