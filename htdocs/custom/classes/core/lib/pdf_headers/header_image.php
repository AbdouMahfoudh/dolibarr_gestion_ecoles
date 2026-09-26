<?php
/**
 * En-tête PDF « image complète » : l'image téléversée dans la configuration (nom, logo et coordonnées déjà
 * dessinés, non modifiables) en haut de la page, sur toute la largeur ; le contenu commence sous l'image
 * (hauteur calculée selon ses proportions). Sans image : bandeau bleu.
 *
 * Fichier : custom/classes/core/lib/pdf_headers/header_image.php
 */

/** Marge autour de l'image d'en-tête (mm) */
define('ECOLE_PDF_ENTETE_IMAGE_MARGE', 6.0);

/**
 * Hauteur de l'image d'en-tête sur la page (mm), 0 si aucune image.
 *
 * @param  float $pageW Largeur de la page (mm)
 * @return float
 */
function ecole_pdf_header_image_hauteur($pageW)
{
	$img = ecole_pdf_image_path('ECOLE_PDF_ENTETE_IMAGE');
	$size = $img !== '' ? @getimagesize($img) : false;
	if (!$size || $size[0] <= 0) {
		return 0.0;
	}
	$w = $pageW - 2 * ECOLE_PDF_ENTETE_IMAGE_MARGE;
	return min(80.0, $w * $size[1] / $size[0]);
}

/**
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_image($pdf, $company)
{
	$h = ecole_pdf_header_image_hauteur($pdf->getPageWidth());
	if ($h <= 0) {
		ecole_pdf_header_bandeau_bleu($pdf, $company);
		return;
	}
	$w = $pdf->getPageWidth() - 2 * ECOLE_PDF_ENTETE_IMAGE_MARGE;
	$pdf->Image(ecole_pdf_image_path('ECOLE_PDF_ENTETE_IMAGE'), ECOLE_PDF_ENTETE_IMAGE_MARGE, ECOLE_PDF_ENTETE_IMAGE_MARGE, $w, $h, '', '', '', true, 300);
	// Titre du document sous l'image
	list($langs, $rtl, $title, $subtitle, $ref) = ecole_pdf_header_context($pdf);
	$y = ECOLE_PDF_ENTETE_IMAGE_MARGE + $h + 2;
	$pdf->SetTextColor(30, 34, 42);
	ecole_pdf_font($pdf, 'B', 12, $rtl);
	$pdf->SetXY(ECOLE_PDF_ENTETE_IMAGE_MARGE, $y);
	$pdf->Cell($w, 6, $title, 0, 2, 'C');
	$ligne = trim($subtitle.($ref !== '' ? '   '.ecole_pdf_ref_line($pdf, $ref) : ''));
	if ($ligne !== '') {
		ecole_pdf_font($pdf, '', 8, $rtl);
		$pdf->SetTextColor(110, 116, 128);
		$pdf->Cell($w, 4, $ligne, 0, 2, 'C');
	}
}
