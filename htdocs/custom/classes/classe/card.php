<?php
/**
 * Fiche : classe.
 * Fichier : custom/classes/classe/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_classe.class.php');

ecole_crud_card(new EcoleClasse($db), ecole_crud_config('classe'));
