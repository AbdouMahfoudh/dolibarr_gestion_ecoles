<?php
/**
 * En-tête PDF « classique » : papier à en-tête centré (logo, établissement, coordonnées,
 * double filet, titre). Symétrique, donc identique en arabe. Repris du module Pointage.
 *
 * Fichier : custom/classes/core/lib/pdf_headers/header_classique.php
 */

/**
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_classique($pdf, $company)
{
	list($langs, $rtl, $title, $subtitle, $ref) = ecole_pdf_header_context($pdf);
	$accent = array(25, 71, 130);
	$grey = array(110, 116, 128);

	$pageW = $pdf->getPageWidth();
	$margin = 15.0;
	$contentW = $pageW - 2 * $margin;
	$y = 8.0;

	if (!empty($company['logo_path'])) {
		try {
			$pdf->Image($company['logo_path'], ($pageW - 32.0) / 2, 6.0, 32.0, 14.0, '', '', '', false, 300, '', false, false, 0, true);
			$y = 22.5;
		} catch (Exception $e) {
			// logo illisible : ignoré
		}
	}

	$pdf->SetTextColor(30, 34, 42);
	ecole_pdf_font($pdf, 'B', 15, $rtl);
	$pdf->SetXY($margin, $y);
	$pdf->Cell($contentW, 7, $company['name'] !== '' ? $company['name'] : '—', 0, 0, 'C');
	$y += 7.6;

	$contact = array_filter(array($company['address'], $company['phone'] !== '' ? ecole_pdf_trans($langs, 'EcoleTelephone').' : '.$company['phone'] : '', $company['email']));
	if (!empty($contact)) {
		ecole_pdf_font($pdf, '', 7.5, $rtl);
		$pdf->SetTextColor($grey[0], $grey[1], $grey[2]);
		$pdf->SetXY($margin, $y);
		$pdf->Cell($contentW, 4, implode('    ', $contact), 0, 0, 'C');
		$y += 5.0;
	}
	$y += 1.5;

	$pdf->SetDrawColor($accent[0], $accent[1], $accent[2]);
	$pdf->SetLineWidth(0.6);
	$pdf->Line($margin, $y, $pageW - $margin, $y);
	$pdf->SetLineWidth(0.2);
	$pdf->Line($margin, $y + 1.1, $pageW - $margin, $y + 1.1);
	$y += 4.5;

	$pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
	ecole_pdf_font($pdf, 'B', 13, $rtl);
	$pdf->SetXY($margin, $y);
	$pdf->Cell($contentW, 6, $title, 0, 0, 'C');
	$y += 6.6;
	if ($subtitle !== '') {
		ecole_pdf_font($pdf, '', 8.5, $rtl);
		$pdf->SetTextColor(80, 80, 90);
		$pdf->SetXY($margin, $y);
		$pdf->Cell($contentW, 4.2, $subtitle, 0, 0, 'C');
		$y += 4.6;
	}
	if ($ref !== '') {
		ecole_pdf_font($pdf, '', 8, $rtl);
		$pdf->SetTextColor($grey[0], $grey[1], $grey[2]);
		$pdf->SetXY($margin, $y);
		$pdf->Cell($contentW, 4.2, ecole_pdf_ref_line($pdf, $ref), 0, 0, 'C');
	}
}
