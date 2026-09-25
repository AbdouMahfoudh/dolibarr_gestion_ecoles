<?php
/**
 * Liste : configuration du module Notes (mention).
 * Fichier : custom/notes/mention/list.php
 */

require '../init.php';
dol_include_once('/notes/class/ecole_note_mention.class.php');

ecole_crud_list(new EcoleNoteMention($db), notes_crud_config('mention'));
