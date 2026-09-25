<?php
/**
 * Liste des lots de salaires (période, nombre de bulletins, total net, état des bulletins ; exports PDF / Excel).
 *
 * Fichier : custom/salaires/lot/list.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');

$langs->loadLangs(array('salaires@salaires'));
ecole_crud_list(new EcoleSalaireLot($db), salaires_crud_config('lot'));
