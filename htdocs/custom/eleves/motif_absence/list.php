<?php
/**
 * Liste : motifs de justification des absences et retards.
 * Fichier : custom/eleves/motif_absence/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_motif_absence.class.php');

ecole_crud_list(new EcoleMotifAbsence($db), eleves_crud_config('motif_absence'));
