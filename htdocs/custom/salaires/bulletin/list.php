<?php
/**
 * Liste des bulletins de paie (un filtre par colonne : numéro, employé, période, mode de paie, montants, lot, statut ;
 * exports PDF / Excel avec les mêmes filtres).
 *
 * Fichier : custom/salaires/bulletin/list.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));
ecole_crud_list(new EcoleSalaire($db), salaires_crud_config('bulletin'));
