<?php
/**
 * Aperçu d'un style d'en-tête ou d'une police des PDF (document d'exemple).
 *   header_preview.php?pdf_header=<style>&lang=<fr|ar>
 *   header_preview.php?pdf_font=<police>&lang=<fr|ar>
 *
 * Fichier : custom/classes/admin/header_preview.php
 */

require '../init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');

if (!$user->hasRight('classes', 'config') && !$user->admin) {
	accessforbidden();
}

list($pdf, $outputlangs) = ecole_pdf_create($langs);
$registry = ecole_pdf_header_registry();
$fonts = ecole_pdf_fonts();
$ar = ($outputlangs->defaultlang === 'ar_SA');
// Sous-titre : la police essayée, ou le style d'en-tête
$label = GETPOST('pdf_font', 'aZ09') !== '' ? ecole_pdf_trans($outputlangs, $fonts[$pdf->fontFamily]['label']) : ecole_pdf_trans($outputlangs, $registry[$pdf->headerStyle]['label']);

ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'ApercuDocTitre'), $label, 'APERCU');
ecole_pdf_table(
	$pdf,
	array('#', ecole_pdf_trans($outputlangs, 'Ref'), ecole_pdf_trans($outputlangs, 'EcoleLibelle'), ecole_pdf_trans($outputlangs, 'Status')),
	array(0.5, 1.2, 4, 1.2),
	array('C', 'C', 'L', 'C'),
	array(
		array('1', 'CHING', $ar ? 'قاعة شنقيط' : 'Salle Chinguetti', ecole_pdf_trans($outputlangs, 'EcoleActif')),
		array('2', 'OUAD', $ar ? 'قاعة وادان' : 'Salle Ouadane', ecole_pdf_trans($outputlangs, 'EcoleActif')),
		array('3', 'LABO', $ar ? 'مختبر العلوم' : 'Laboratoire des sciences', ecole_pdf_trans($outputlangs, 'EcoleActif')),
		// Français et arabe mêlés, accents, chiffres et symboles : pour juger la police
		array('4', 'E00004', 'Abdou El hassen — عبدو الحسن', ecole_pdf_trans($outputlangs, 'EcoleActif')),
		array('5', '1AS', 'Élève, créneau, français, à côté — اللغة العربية، الرياضيات', '12:15 → 14:00'),
		array('6', 'MRU', '0123456789 — ٠١٢٣٤٥٦٧٨٩ — 1 500,00', '« 20/20 »'),
	)
);
$pdf->Output('apercu_'.(GETPOST('pdf_font', 'aZ09') !== '' ? $pdf->fontFamily : $pdf->headerStyle).'.pdf', 'I');

$db->close();
