<?php
/**
 * Attestation d'inscription d'un élève (PDF A4), remise au parent après la validation de l'inscription.
 * Distincte de l'attestation de solde (attestation.php) et du certificat de scolarité (module Notes).
 * Fichier : custom/eleves/eleve/attestation_inscription.php
 */

require '../init.php';
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/recu_pdf.lib.php');

if (!$user->hasRight('eleves', 'eleve', 'attestation')) {
	accessforbidden();
}
$eleve = new EcoleEleve($db);
if (GETPOSTINT('id') <= 0 || $eleve->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
if (!$eleve->inscriptionValidee()) {
	accessforbidden($langs->trans('AttestationInscriptionNonValidee'));
}
eleves_pdf_attestation_inscription($db, $eleve);
$db->close();
