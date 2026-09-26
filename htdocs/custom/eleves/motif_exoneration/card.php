<?php
/**
 * Fiche : motifs d'exonération des frais d'inscription.
 * Fichier : custom/eleves/motif_exoneration/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_motif_exoneration.class.php');

ecole_crud_card(new EcoleMotifExoneration($db), eleves_crud_config('motif_exoneration'));
