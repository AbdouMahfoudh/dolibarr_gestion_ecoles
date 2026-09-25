<?php
/**
 * Fiche : configuration du module Notes (distinction).
 * Fichier : custom/notes/distinction/card.php
 */

require '../init.php';
dol_include_once('/notes/class/ecole_note_distinction.class.php');

ecole_crud_card(new EcoleNoteDistinction($db), notes_crud_config('distinction'));
