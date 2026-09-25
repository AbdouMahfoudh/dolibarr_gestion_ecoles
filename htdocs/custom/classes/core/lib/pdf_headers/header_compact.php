<?php
/**
 * En-tête PDF « compact » : bandeau fin d'une ligne (établissement / titre). Idéal pour les listes.
 * Repris du module Pointage.
 *
 * Fichier : custom/classes/core/lib/pdf_headers/header_compact.php
 */

/**
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_compact($pdf, $company)
{
	list($langs, $rtl, $title, $subtitle, $ref) = ecole_pdf_header_context($pdf);

	$pageW = $pdf->getPageWidth();
	$margin = 15.0;
	$bandH = 15.0;
	$pdf->SetFillColor(67, 142, 204);
	$pdf->Rect(0, 0, $pageW, $bandH, 'F');
	$pdf->SetFillColor(25, 71, 130);
	$pdf->Rect(0, $bandH, $pageW, 1.4, 'F');

	$titleLine = $title.($ref !== '' ? '   ('.ecole_pdf_ref_line($pdf, $ref).')' : '');
	$colW = ($pageW - 2 * $margin) / 2 - 2.0;
	if (!$rtl) {
		$socX = $margin;
		$socAlign = 'L';
		$titleX = $pageW - $margin - $colW;
		$titleAlign = 'R';
	} else {
		$titleX = $margin;
		$titleAlign = 'L';
		$socX = $pageW - $margin - $colW;
		$socAlign = 'R';
	}

	$logoBox = 9.0;
	if (!empty($company['logo_path'])) {
		try {
			$pdf->Image($company['logo_path'], $rtl ? ($socX + $colW - $logoBox) : $socX, 3.0, $logoBox, $logoBox, '', '', '', false, 300, '', false, false, 0, true);
			if (!$rtl) {
				$socX += $logoBox + 3.0;
			}
			$colW -= $logoBox + 3.0;
		} catch (Exception $e) {
			// logo illisible : ignoré
		}
	}

	$pdf->SetTextColor(255, 255, 255);
	ecole_pdf_font($pdf, 'B', 11, $rtl);
	$pdf->SetXY($socX, 4.6);
	$pdf->Cell($colW, 6, $company['name'] !== '' ? $company['name'] : '—', 0, 0, $socAlign);
	ecole_pdf_font($pdf, 'B', 9.5, $rtl);
	$pdf->SetXY($titleX, 2.4);
	$pdf->MultiCell($colW, 3.7, $titleLine, 0, $titleAlign, false, 2);
	if ($subtitle !== '') {
		ecole_pdf_font($pdf, '', 6.8, $rtl);
		$pdf->SetX($titleX);
		$pdf->MultiCell($colW, 3.1, $subtitle, 0, $titleAlign, false, 2);
	}
}
