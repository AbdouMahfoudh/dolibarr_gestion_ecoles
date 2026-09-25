<?php
/**
 * Fiche : niveau.
 * Fichier : custom/classes/niveau/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_niveau.class.php');

ecole_crud_card(new EcoleNiveau($db), ecole_crud_config('niveau'));
