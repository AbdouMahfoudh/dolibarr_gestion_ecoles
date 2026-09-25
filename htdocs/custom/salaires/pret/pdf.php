<?php
/**
 * Reconnaissance de prêt (PDF à signer par l'employé).
 *
 * Fichier : custom/salaires/pret/pdf.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');
dol_include_once('/salaires/core/lib/salaires_pdf.lib.php');

if (!$user->hasRight('salaires', 'bulletin', 'exporter') || !$user->hasRight('salaires', 'avance', 'lire')) {
	accessforbidden();
}
$object = new EcoleSalairePret($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
salaires_pdf_avance_pret($db, $object);
$db->close();
