<?php
/**
 * Liste : responsable.
 * Fichier : custom/eleves/responsable/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_responsable.class.php');

ecole_crud_list(new EcoleResponsable($db), eleves_crud_config('responsable'));
