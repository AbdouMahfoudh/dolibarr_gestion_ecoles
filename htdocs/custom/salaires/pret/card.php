<?php
/**
 * Fiche d'un prêt au personnel (voir core/avance_pret_card.inc.php).
 *
 * Fichier : custom/salaires/pret/card.php
 */

require '../init.php';
$type = 'pret';
include dol_buildpath('/salaires/core/avance_pret_card.inc.php', 0);
