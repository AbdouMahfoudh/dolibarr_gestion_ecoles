<?php
/**
 * Fiche : engagements du responsable.
 * Fichier : custom/eleves/engagement/card.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_engagement.class.php');

ecole_crud_card(new EcoleEngagement($db), eleves_crud_config('engagement'));
