<?php
/**
 * Fiche d'une avance sur salaire (voir core/avance_pret_card.inc.php).
 *
 * Fichier : custom/salaires/avance/card.php
 */

require '../init.php';
$type = 'avance';
include dol_buildpath('/salaires/core/avance_pret_card.inc.php', 0);
