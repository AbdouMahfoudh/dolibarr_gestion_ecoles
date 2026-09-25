<?php
/**
 * En-tête PDF « minimal » : logo et établissement d'un côté, titre de l'autre, filet fin.
 * Repris du module Pointage.
 *
 * Fichier : custom/classes/core/lib/pdf_headers/header_minimal.php
 */

/**
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_minimal($pdf, $company)
{
	list($langs, $rtl, $title, $subtitle, $ref) = ecole_pdf_header_context($pdf);
	$accent = array(67, 142, 204);
	$grey = array(110, 116, 128);

	$pageW = $pdf->getPageWidth();
	$margin = 15.0;
	$logoBox = 16.0;
	$small = ($pageW < 200); // A5 (reçus) : largeurs proportionnelles ; A4 inchangé
	$titleW = $small ? $pageW * 0.35 : 74.0;
	$top = 9.0;
	if (!$rtl) {
		$logoX = $margin;
		$infoX = $margin + $logoBox + 5.0;
		$infoW = ($pageW - $margin - $titleW - 4.0) - $infoX;
		$infoAlign = 'L';
		$titleX = $pageW - $margin - $titleW;
		$titleAlign = 'R';
	} else {
		$logoX = $pageW - $margin - $logoBox;
		$infoX = $margin + $titleW + 4.0;
		$infoW = ($pageW - $margin - $logoBox - 5.0) - $infoX;
		$infoAlign = 'R';
		$titleX = $margin;
		$titleAlign = 'L';
	}
	$infoW = max($small ? 30.0 : 45.0, $infoW);

	if (!empty($company['logo_path'])) {
		try {
			$pdf->Image($company['logo_path'], $logoX, $top - 1.0, $logoBox, $logoBox, '', '', '', false, 300, '', false, false, 0, true);
		} catch (Exception $e) {
			// logo illisible : ignoré
		}
	}

	$pdf->SetTextColor(30, 34, 42);
	ecole_pdf_font($pdf, 'B', 13, $rtl);
	$pdf->SetXY($infoX, $top);
	$pdf->Cell($infoW, 6, $company['name'] !== '' ? $company['name'] : '—', 0, 2, $infoAlign);
	ecole_pdf_font($pdf, '', 7.5, $rtl);
	$pdf->SetTextColor($grey[0], $grey[1], $grey[2]);
	if ($company['address'] !== '') {
		$pdf->SetX($infoX);
		$pdf->MultiCell($infoW, 3.6, $company['address'], 0, $infoAlign, false, 2);
	}
	$contact = array_filter(array($company['phone'] !== '' ? ecole_pdf_trans($langs, 'EcoleTelephone').' : '.$company['phone'] : '', $company['email']));
	if (!empty($contact)) {
		$pdf->SetX($infoX);
		$pdf->Cell($infoW, 3.8, implode(' — ', $contact), 0, 2, $infoAlign);
	}

	$pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
	ecole_pdf_font($pdf, 'B', 14, $rtl);
	$pdf->SetXY($titleX, $top);
	$pdf->MultiCell($titleW, 5.5, $title, 0, $titleAlign, false, 2);
	if ($subtitle !== '') {
		ecole_pdf_font($pdf, '', 8, $rtl);
		$pdf->SetTextColor(80, 80, 90);
		$pdf->SetX($titleX);
		$pdf->MultiCell($titleW, 4, $subtitle, 0, $titleAlign, false, 2);
	}
	if ($ref !== '') {
		ecole_pdf_font($pdf, '', 8, $rtl);
		$pdf->SetTextColor($grey[0], $grey[1], $grey[2]);
		$pdf->SetX($titleX);
		$pdf->MultiCell($titleW, 4, ecole_pdf_ref_line($pdf, $ref), 0, $titleAlign, false, 2);
	}

	$pdf->SetDrawColor($accent[0], $accent[1], $accent[2]);
	$pdf->SetLineWidth(0.5);
	$pdf->Line($margin, 30.0, $pageW - $margin, 30.0);
}
