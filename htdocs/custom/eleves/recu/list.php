<?php
/**
 * Liste des reçus de paiement (filtres : numéro, mode, statut ; exports PDF / Excel).
 * Fichier : custom/eleves/recu/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_recu.class.php');

ecole_crud_list(new EcoleRecu($db), eleves_crud_config('recu'));
