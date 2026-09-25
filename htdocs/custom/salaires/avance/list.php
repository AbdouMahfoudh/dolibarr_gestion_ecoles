<?php
/**
 * Liste des avances sur salaire (filtre par colonne, état en cours / soldé / annulé, exports PDF / Excel).
 *
 * Fichier : custom/salaires/avance/list.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_avance.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));
ecole_crud_list(new EcoleSalaireAvance($db), salaires_crud_config('avance'));
