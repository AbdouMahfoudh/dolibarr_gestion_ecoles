<?php
/**
 * Liste des employés (filtre sur chaque colonne : nom, catégorie, poste, téléphone, mode de paie, date d'embauche,
 * pièces, compte, statut ; exports PDF / Excel avec les mêmes filtres).
 * Fichier : custom/personnel/employe/list.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe.class.php');

ecole_crud_list(new EcoleEmploye($db), personnel_crud_config('employe'));
