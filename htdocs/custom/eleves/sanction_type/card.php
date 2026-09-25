<?php
/**
 * Fiche : types de sanctions.
 * Fichier : custom/eleves/sanction_type/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_sanction_type.class.php');

ecole_crud_card(new EcoleSanctionType($db), eleves_crud_config('sanction_type'));
