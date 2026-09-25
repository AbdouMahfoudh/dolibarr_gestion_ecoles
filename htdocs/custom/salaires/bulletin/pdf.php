<?php
/**
 * Fiche de paie PDF d'un bulletin (langue forçable : &lang=fr ou &lang=ar).
 *
 * Fichier : custom/salaires/bulletin/pdf.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/core/lib/salaires_pdf.lib.php');

if (!$user->hasRight('salaires', 'bulletin', 'exporter')) {
	accessforbidden();
}
$object = new EcoleSalaire($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
salaires_pdf_output($db, array($object), 'fiche_paie_'.$object->ref);
$db->close();
