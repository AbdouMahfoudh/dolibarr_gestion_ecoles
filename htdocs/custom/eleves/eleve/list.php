<?php
/**
 * Liste des élèves (filtres : classe, sexe, statut, pièces manquantes ; exports PDF / Excel).
 * Fichier : custom/eleves/eleve/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');

ecole_crud_list(new EcoleEleve($db), eleves_crud_config('eleve'));
