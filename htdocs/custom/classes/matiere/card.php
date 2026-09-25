<?php
/**
 * Fiche : matiere.
 * Fichier : custom/classes/matiere/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_matiere.class.php');

ecole_crud_card(new EcoleMatiere($db), ecole_crud_config('matiere'));
