<?php
/**
 * Fiche : salle.
 * Fichier : custom/classes/salle/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_salle.class.php');

ecole_crud_card(new EcoleSalle($db), ecole_crud_config('salle'));
