<?php
/**
 * Certificat de scolarité d'un élève inscrit (PDF, langue de l'utilisateur ou ?lang=fr / ?lang=ar).
 *
 *   certificat.php?id=<élève>[&lang=fr|ar]
 *
 * Fichier : custom/notes/certificat.php
 */

require 'init.php';
dol_include_once('/notes/core/lib/bulletin_pdf.lib.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves'));

if (!$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}
$eleve = new EcoleEleve($db);
if (GETPOSTINT('id') <= 0 || $eleve->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (!in_array((int) $eleve->status, EcoleEleve::statusOccupantPlace(), true)) {
	llxHeader('', $langs->trans('CertificatScolarite'));
	print '<div class="warning">'.$langs->trans('ErrorCertificatNonInscrit').'</div>';
	llxFooter();
	$db->close();
	exit;
}
notes_pdf_certificat($db, $eleve);
$db->close();
