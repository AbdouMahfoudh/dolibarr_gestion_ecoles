<?php
/**
 * Fiches de paie de tous les bulletins (non annulés) d'un lot, dans un seul PDF.
 *
 * Fichier : custom/salaires/lot/pdf.php
 */

require '../init.php';
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');
dol_include_once('/salaires/core/lib/salaires_pdf.lib.php');

if (!$user->hasRight('salaires', 'bulletin', 'exporter')) {
	accessforbidden();
}
$lot = new EcoleSalaireLot($db);
if (GETPOSTINT('id') <= 0 || $lot->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
$bulletins = array_filter($lot->getBulletins(), function ($b) {
	return (int) $b->status !== EcoleSalaire::STATUS_ANNULE;
});
salaires_pdf_output($db, $bulletins, 'fiches_paie_'.$lot->ref);
$db->close();
