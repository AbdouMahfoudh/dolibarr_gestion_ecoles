<?php
/**
 * Fiche : responsable.
 * Fichier : custom/eleves/responsable/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_responsable.class.php');

ecole_crud_card(new EcoleResponsable($db), eleves_crud_config('responsable'));
