<?php
/**
 * Fiche : configuration du module Notes (mention).
 * Fichier : custom/notes/mention/card.php
 */

require '../init.php';
dol_include_once('/notes/class/ecole_note_mention.class.php');

ecole_crud_card(new EcoleNoteMention($db), notes_crud_config('mention'));
