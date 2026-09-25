<?php
/**
 * Liste : pièce à fournir (personnel).
 * Fichier : custom/personnel/doc_type/list.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe_doc_type.class.php');

ecole_crud_list(new EcoleEmployeDocType($db), personnel_crud_config('doc_type'));
