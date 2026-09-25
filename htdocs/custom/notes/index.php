<?php
/**
 * Accueil du module Notes : la saisie des notes, ou les résultats pour qui ne saisit pas.
 * Fichier : custom/notes/index.php
 */

require 'init.php';

$saisie = $user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'note', 'saisir') || $user->hasRight('notes', 'note', 'saisirtout');
header('Location: '.dol_buildpath($saisie ? '/notes/saisie.php' : '/notes/resultats.php', 1));
exit;
