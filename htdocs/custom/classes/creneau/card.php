<?php
/**
 * Fiche : creneau.
 * Fichier : custom/classes/creneau/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_creneau.class.php');

ecole_crud_card(new EcoleCreneau($db), ecole_crud_config('creneau'));
