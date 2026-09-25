<?php
/**
 * Fiche : configuration du module Notes (modele).
 * Fichier : custom/notes/modele/card.php
 */

require '../init.php';
dol_include_once('/notes/class/ecole_bulletin_modele.class.php');

ecole_crud_card(new EcoleBulletinModele($db), notes_crud_config('modele'));
