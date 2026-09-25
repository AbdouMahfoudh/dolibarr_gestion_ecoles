<?php
/**
 * Impression d'un reçu de paiement (PDF A5, A4 ou ticket 80 mm selon la configuration), langue détectée.
 * Fichier : custom/eleves/recu/pdf.php
 */

require '../init.php';
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/eleves/class/ecole_recu.class.php');
dol_include_once('/eleves/core/lib/recu_pdf.lib.php');

if (!$user->hasRight('eleves', 'paiement', 'recu')) {
	accessforbidden();
}
$recu = new EcoleRecu($db);
if (GETPOSTINT('id') <= 0 || $recu->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
eleves_pdf_recu($db, $recu);
$db->close();
