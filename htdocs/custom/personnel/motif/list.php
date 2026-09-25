<?php
/**
 * Liste : motif du personnel.
 * Fichier : custom/personnel/motif/list.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe_motif.class.php');

ecole_crud_list(new EcoleEmployeMotif($db), personnel_crud_config('motif'));
