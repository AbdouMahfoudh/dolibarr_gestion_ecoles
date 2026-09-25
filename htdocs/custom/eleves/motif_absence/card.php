<?php
/**
 * Fiche : motifs de justification des absences et retards.
 * Fichier : custom/eleves/motif_absence/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_motif_absence.class.php');

ecole_crud_card(new EcoleMotifAbsence($db), eleves_crud_config('motif_absence'));
