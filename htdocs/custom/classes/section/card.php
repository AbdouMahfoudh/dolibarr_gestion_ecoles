<?php
/**
 * Fiche : section.
 * Fichier : custom/classes/section/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_section.class.php');

ecole_crud_card(new EcoleSection($db), ecole_crud_config('section'));
