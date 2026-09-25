<?php
/**
 * Attestation de solde d'un élève (PDF A4) : frais d'inscription et mensualités dus, payés, reste, à la date du jour.
 * Fichier : custom/eleves/eleve/attestation.php
 */

require '../init.php';
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/recu_pdf.lib.php');

if (!$user->hasRight('eleves', 'paiement', 'recu')) {
	accessforbidden();
}
$eleve = new EcoleEleve($db);
if (GETPOSTINT('id') <= 0 || $eleve->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
eleves_pdf_attestation($db, $eleve);
$db->close();
