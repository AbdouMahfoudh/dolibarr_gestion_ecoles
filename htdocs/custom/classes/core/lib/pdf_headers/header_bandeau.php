<?php
/**
 * En-têtes PDF « bandeau bleu » et « bandeau sombre » : bandeau plein largeur, logo et
 * établissement d'un côté, cartouche titre / sous-titre / réf de l'autre (inversé en arabe).
 * Repris du module Pointage.
 *
 * Fichier : custom/classes/core/lib/pdf_headers/header_bandeau.php
 */

/**
 * Rendu du bandeau selon une palette.
 *
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @param array    $palette band, strip, title_text
 * @return void
 */
function ecole_pdf_header_layout_bandeau($pdf, $company, $palette)
{
	list($langs, $rtl, $title, $subtitle, $ref) = ecole_pdf_header_context($pdf);
	$band = $palette['band'];
	$strip = $palette['strip'];
	$titleColor = $palette['title_text'];

	$pageW = $pdf->getPageWidth();
	$margin = 15.0;
	$headerH = 36.0;

	$pdf->SetFillColor($band[0], $band[1], $band[2]);
	$pdf->Rect(0, 0, $pageW, $headerH, 'F');
	$pdf->SetFillColor($strip[0], $strip[1], $strip[2]);
	$pdf->Rect(0, 0, $pageW, 4, 'F');

	$logoBox = 24.0;
	$gap = 6.0;
	$small = ($pageW < 200); // A5 (reçus) : largeurs proportionnelles ; A4 inchangé
	$titleBoxW = $small ? $pageW * 0.32 : 68.0;
	if (!$rtl) {
		$logoX = $margin;
		$titleX = $pageW - $margin - $titleBoxW;
		$infoX = $logoX + $logoBox + $gap;
		$infoW = max($small ? 30.0 : 40.0, $titleX - $gap - $infoX);
		$infoAlign = 'L';
	} else {
		$logoX = $pageW - $margin - $logoBox;
		$titleX = $margin;
		$infoX = $titleX + $titleBoxW + $gap;
		$infoW = max($small ? 30.0 : 40.0, $logoX - $gap - $infoX);
		$infoAlign = 'R';
	}

	$pdf->SetFillColor(255, 255, 255);
	$pdf->Rect($logoX, 6, $logoBox, $logoBox, 'F');
	if (!empty($company['logo_path'])) {
		try {
			$pdf->Image($company['logo_path'], $logoX + 2, 8, $logoBox - 4, $logoBox - 4, '', '', '', false, 300, '', false, false, 0, true);
		} catch (Exception $e) {
			// logo illisible : ignoré
		}
	}

	$pdf->SetTextColor(255, 255, 255);
	ecole_pdf_font($pdf, 'B', 13, $rtl);
	$pdf->SetXY($infoX, 8);
	$pdf->Cell($infoW, 7, $company['name'] !== '' ? $company['name'] : '—', 0, 1, $infoAlign);
	ecole_pdf_font($pdf, '', 7.5, $rtl);
	if ($company['address'] !== '') {
		$pdf->SetX($infoX);
		$pdf->MultiCell($infoW, 4, $company['address'], 0, $infoAlign, false, 1);
	}
	$contact = array_filter(array($company['phone'] !== '' ? ecole_pdf_trans($langs, 'EcoleTelephone').' : '.$company['phone'] : '', $company['email']));
	if (!empty($contact)) {
		$pdf->SetX($infoX);
		$pdf->Cell($infoW, 4, implode(' — ', $contact), 0, 1, $infoAlign);
	}

	$pdf->SetFillColor(255, 255, 255);
	$pdf->Rect($titleX, 7, $titleBoxW, 22, 'F');
	$pdf->SetTextColor($titleColor[0], $titleColor[1], $titleColor[2]);
	ecole_pdf_font($pdf, 'B', 11, $rtl);
	$pdf->SetXY($titleX + 2, 8.5);
	$pdf->MultiCell($titleBoxW - 4, 5, $title, 0, 'C', false, 1);
	if ($subtitle !== '') {
		ecole_pdf_font($pdf, '', 7, $rtl);
		$pdf->SetTextColor(80, 80, 90);
		$pdf->SetX($titleX + 2);
		$pdf->MultiCell($titleBoxW - 4, 3.6, $subtitle, 0, 'C', false, 1, '', '', true, 0, false, true, 11, 'T', true);
	}
	if ($ref !== '') {
		ecole_pdf_font($pdf, '', 7, $rtl);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetXY($titleX, 30);
		$pdf->Cell($titleBoxW, 4, ecole_pdf_ref_line($pdf, $ref), 0, 1, $rtl ? 'L' : 'R');
	}
}

/**
 * Style « bandeau bleu » (par défaut).
 *
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_bandeau_bleu($pdf, $company)
{
	ecole_pdf_header_layout_bandeau($pdf, $company, array('band' => array(67, 142, 204), 'strip' => array(25, 71, 130), 'title_text' => array(67, 142, 204)));
}

/**
 * Style « bandeau sombre » : ardoise et filet ambre.
 *
 * @param EcolePDF $pdf     PDF
 * @param array    $company Établissement
 * @return void
 */
function ecole_pdf_header_bandeau_sombre($pdf, $company)
{
	ecole_pdf_header_layout_bandeau($pdf, $company, array('band' => array(31, 41, 55), 'strip' => array(245, 158, 11), 'title_text' => array(31, 41, 55)));
}
