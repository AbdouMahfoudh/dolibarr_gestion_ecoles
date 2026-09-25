<?php
/**
 * Liste des prêts au personnel (filtre par colonne, état en cours / soldé / annulé, exports PDF / Excel).
 *
 * Fichier : custom/salaires/pret/list.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));
ecole_crud_list(new EcoleSalairePret($db), salaires_crud_config('pret'));
