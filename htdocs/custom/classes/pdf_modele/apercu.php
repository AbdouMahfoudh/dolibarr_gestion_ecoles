<?php
/**
 * Aperçu d'un modèle de PDF (en français ou en arabe) sur des données d'exemple :
 * en-tête, couleur, taille du texte, orientation (listes) et filigrane du modèle.
 *
 *   apercu.php?id=<modèle>&lang=<fr|ar>
 *
 * Fichier : custom/classes/pdf_modele/apercu.php
 */

require '../init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/classes/class/ecole_pdf_modele.class.php');

if (!$user->hasRight('classes', 'config')) {
	accessforbidden();
}
$modele = new EcolePdfModele($db);
if (GETPOSTINT('id') <= 0 || $modele->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}
list($pdf, $outputlangs, $rtl, $lang) = ecole_pdf_create($langs, 'P', ($modele->type_doc === 'recu' && getDolGlobalString('ELEVES_RECU_FORMAT', 'A5') === 'A5') ? 'A5' : 'A4');
$outputlangs->load('classes@classes');
ecole_pdf_modele_appliquer($pdf, $modele);

// Données d'exemple
$headers = array(ecole_pdf_trans($outputlangs, 'ApercuColNum'), ecole_pdf_trans($outputlangs, 'ApercuColNom'), ecole_pdf_trans($outputlangs, 'ApercuColClasse'), ecole_pdf_trans($outputlangs, 'ApercuColMontant'));
$noms = $rtl ? array('عائشة محمد سالم', 'أحمد سيدي محمد', 'فاطمة الزهراء أحمد', 'محمد الأمين يحيى') : array('Aïcha Mohamed Salem', 'Ahmed Sidi Mohamed', 'Fatimetou Ahmed', 'Mohamed Lemine Yahya');
$rows = array();
for ($i = 1; $i <= 12; $i++) {
	$rows[] = array((string) $i, $noms[$i % 4], '4AS', price(1500 * (1 + $i % 3), 0, $outputlangs));
}
$ds = array('headers' => $headers, 'rows' => $rows);
if ($modele->type_doc === 'liste') {
	$o = $modele->orientation === 'auto' ? ecole_export_orientation($pdf, $ds) : $modele->orientation;
	if ($o === 'L') {
		$pdf->setPageOrientation('L');
	}
}
$titres = array('liste' => 'PdfTypeListe', 'recu' => 'PdfTypeRecu', 'paie' => 'PdfTypePaie');
ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, $titres[$modele->type_doc]), ecole_pdf_trans($outputlangs, 'ApercuDonneesExemple'), 'APERCU');
ecole_pdf_table($pdf, $headers, array(0.5, 3, 1, 1.4), array('C', 'L', 'C', 'R'), $rows);
$pdf->Output('apercu_'.$modele->ref.'_'.$lang.'.pdf', 'I');
