<?php
/**
 * Liste : engagements du responsable.
 * Fichier : custom/eleves/engagement/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_engagement.class.php');

ecole_crud_list(new EcoleEngagement($db), eleves_crud_config('engagement'));
