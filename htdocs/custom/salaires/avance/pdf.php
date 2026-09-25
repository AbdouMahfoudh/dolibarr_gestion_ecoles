<?php
/**
 * Reçu d'avance sur salaire (PDF à signer par l'employé).
 *
 * Fichier : custom/salaires/avance/pdf.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_avance.class.php');
dol_include_once('/salaires/core/lib/salaires_pdf.lib.php');

if (!$user->hasRight('salaires', 'bulletin', 'exporter') || !$user->hasRight('salaires', 'avance', 'lire')) {
	accessforbidden();
}
$object = new EcoleSalaireAvance($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
salaires_pdf_avance_pret($db, $object);
$db->close();
