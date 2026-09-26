<?php
/**
 * Engagement du responsable (PDF A4, un seul document) : engagements actifs de la configuration
 * (Élèves > Configuration > Engagements), à signer par le responsable à l'inscription.
 * Fichier : custom/eleves/eleve/engagement.php
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
$err = eleves_pdf_engagement($db, $eleve);
if ($err !== '') {
	setEventMessages($err, null, 'errors');
	header('Location: '.dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $eleve->id));
	exit;
}
$db->close();
