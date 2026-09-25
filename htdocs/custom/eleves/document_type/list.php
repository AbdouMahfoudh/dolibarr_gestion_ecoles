<?php
/**
 * Liste : pièce à fournir.
 * Fichier : custom/eleves/document_type/list.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_document_type.class.php');

ecole_crud_list(new EcoleDocumentType($db), eleves_crud_config('document_type'));
