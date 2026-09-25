<?php
/**
 * Fiche : autres frais (uniforme, transport...).
 * Fichier : custom/eleves/frais_type/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_frais_type.class.php');

ecole_crud_card(new EcoleFraisType($db), eleves_crud_config('frais_type'));
