<?php
/**
 * Documents imprimés des paiements : reçu de paiement (A5, A4 ou ticket 80 mm selon la configuration)
 * et attestation de solde. Langue détectée (arabe / français), en-tête de l'établissement choisi dans
 * la configuration du module Classes. Jamais de mot « facture » : reçus, frais, mensualités.
 *
 * Fichier : custom/eleves/core/lib/recu_pdf.lib.php
 */

dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

/**
 * Ligne « Libellé : valeur » dans le sens de lecture.
 *
 * @param  EcolePDF $pdf   PDF
 * @param  string   $label Libellé
 * @param  string   $value Valeur
 * @param  float    $size  Taille de police
 * @param  string   $style Style de la valeur ('' ou 'B')
 * @return void
 */
function eleves_pdf_kv($pdf, $label, $value, $size = 9, $style = '')
{
	// Deux cellules (libellé / valeur) : un nom latin dans un texte arabe garde l'ordre de ses mots
	$rtl = $pdf->isRtl;
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$wl = round($w * 0.38, 2);
	$wv = $w - $wl;
	$label = ecole_pdf_bidi(ecole_pdf_text($label).' :', $rtl);
	$value = ecole_pdf_bidi(ecole_pdf_text($value), $rtl);
	ecole_pdf_font($pdf, $style, $size, $rtl);
	$h = max(5, $pdf->getStringHeight($wv, $value), $pdf->getStringHeight($wl, $label));
	$y = $pdf->GetY();
	if ($rtl) {
		$pdf->MultiCell($wv, $h, $value, 0, 'R', false, 0, $m['left'], $y);
		ecole_pdf_font($pdf, '', $size, $rtl);
		$pdf->MultiCell($wl, $h, $label, 0, 'R', false, 1, $m['left'] + $wv, $y);
	} else {
		ecole_pdf_font($pdf, '', $size, $rtl);
		$pdf->MultiCell($wl, $h, $label, 0, 'L', false, 0, $m['left'], $y);
		ecole_pdf_font($pdf, $style, $size, $rtl);
		$pdf->MultiCell($wv, $h, $value, 0, 'L', false, 1, $m['left'] + $wl, $y);
	}
}

/**
 * « Nom - matricule » d'un élève dans le sens de lecture : en arabe le nom d'abord (à droite),
 * puis le matricule ; en français le matricule d'abord.
 *
 * @param  string $ref   Matricule
 * @param  string $label Nom dans la langue du document
 * @param  bool   $rtl   Document en arabe
 * @return string
 */
function eleves_pdf_eleve_nom($ref, $label, $rtl)
{
	return $rtl ? $label.' - '.$ref : $ref.' - '.$label;
}

/**
 * Dans un texte arabe, garde dans l'ordre les mots d'un nom écrit en lettres latines
 * (marqueurs Unicode « gauche à droite »). Sans effet en français ou si le texte contient de l'arabe.
 *
 * @param  string $s   Texte
 * @param  bool   $rtl Document en arabe
 * @return string
 */
function eleves_pdf_ltr($s, $rtl)
{
	$s = (string) $s;
	if (!$rtl || $s === '' || preg_match('/\p{Arabic}/u', $s)) {
		return $s;
	}
	return "\u{202A}".$s."\u{202C}";
}

/**
 * Tampon « ANNULÉ » en travers de la page.
 *
 * @param  EcolePDF  $pdf   PDF
 * @param  Translate $langs Traductions
 * @return void
 */
function eleves_pdf_annule($pdf, $langs)
{
	$pdf->SetTextColor(220, 40, 40);
	ecole_pdf_font($pdf, 'B', 40, $pdf->isRtl);
	$pdf->StartTransform();
	$cx = $pdf->getPageWidth() / 2;
	$cy = $pdf->getPageHeight() / 2;
	$pdf->Rotate(30, $cx, $cy);
	$pdf->SetAlpha(0.25);
	$pdf->Text($cx - 45, $cy - 10, ecole_pdf_trans($langs, 'RecuAnnuleTampon'));
	$pdf->SetAlpha(1);
	$pdf->StopTransform();
	$pdf->SetTextColor(0, 0, 0);
}

/**
 * Reçu de paiement.
 *
 * @param  DoliDB    $db   Handler base
 * @param  EcoleRecu $recu Reçu (avec ses lignes)
 * @return void            PDF envoyé au navigateur
 */
function eleves_pdf_recu($db, $recu)
{
	global $langs, $conf;
	dol_include_once('/eleves/class/ecole_responsable.class.php');

	$format = getDolGlobalString('ELEVES_RECU_FORMAT', 'A5');
	if ($format === 'TICKET') {
		eleves_pdf_recu_ticket($db, $recu);
		return;
	}
	list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', $format === 'A4' ? 'A4' : 'A5');
	$outputlangs->loadLangs(array('eleves@eleves', 'bills'));
	ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'RecuDePaiement'), dol_print_date($recu->date_recu, 'day', 'tzuser', $outputlangs), $recu->ref);
	if ($format !== 'A4') {
		$pdf->SetMargins(10, $pdf->getMargins()['top'], 10);
	}
	$pdf->SetTextColor(40, 40, 50);

	// Payeur, mode, référence
	if ($recu->fk_responsable > 0) {
		$resp = new EcoleResponsable($db);
		if ($resp->fetch((int) $recu->fk_responsable) > 0) {
			eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'RecuDe'), ecole_label($resp).($resp->telephone ? ' — '.$resp->telephone : ''), 9, 'B');
		}
	}
	eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'DatePaiement'), dol_print_date($recu->date_recu, 'day', 'tzuser', $outputlangs));
	eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'ModePaiement'), eleves_mode_label($db, $recu->fk_mode).($recu->reference_paiement ? ' ('.$recu->reference_paiement.')' : ''));
	$pdf->Ln(2);

	// Détail par élève
	$rows = array();
	$eleves = array();
	$prev = 0;
	foreach ($recu->lignes as $l) {
		$nom = ((int) $l->fk_eleve !== $prev) ? eleves_pdf_eleve_nom($l->eleve_ref, ecole_label($l), $rtl) : '';
		$prev = (int) $l->fk_eleve;
		$eleves[(int) $l->fk_eleve] = (int) $l->fk_eleve;
		$rows[] = array($nom, ecole_pdf_text(eleves_ligne_libelle($l)), price($l->montant, 0, $outputlangs));
	}
	$rows[] = array('', ecole_pdf_trans($outputlangs, 'Total'), price($recu->montant, 0, $outputlangs, 1, -1, -1, $conf->currency));
	ecole_pdf_table($pdf, array(ecole_pdf_trans($outputlangs, 'Eleve'), ecole_pdf_trans($outputlangs, 'Libelle'), ecole_pdf_trans($outputlangs, 'Montant').' ('.$conf->currency.')'),
		array(3, 3.2, 1.4), array('L', 'L', 'R'), $rows);
	$pdf->Ln(3);

	// Situation après ce paiement (reste en retard par élève)
	if ((int) $recu->status === EcoleRecu::STATUS_VALIDE) {
		$objs = array();
		foreach ($eleves as $eid) {
			$e = new EcoleEleve($db);
			if ($e->fetch($eid) > 0) {
				$objs[] = $e;
			}
		}
		$sits = eleves_situations($db, $objs);
		foreach ($objs as $e) {
			$s = $sits[(int) $e->id];
			$txt = ($s['impaye'] > 0) ? ecole_pdf_trans($outputlangs, 'ResteImpayeAuJour', price($s['impaye'], 0, $outputlangs, 1, -1, -1, $conf->currency)) : ecole_pdf_trans($outputlangs, 'AJourAuJour');
			eleves_pdf_kv($pdf, eleves_pdf_eleve_nom($e->ref, ecole_label($e), $rtl), $txt, 8);
		}
	} else {
		eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'RecuAnnule'), (string) $recu->motif_annulation, 9, 'B');
	}

	// Caissier et signature
	$pdf->Ln(4);
	eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'EnregistrePar'), ecole_user_label($db, $recu->fk_user_creat), 8);
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	ecole_pdf_font($pdf, '', 8, $rtl);
	$pdf->Ln(2);
	$pdf->Cell($w, 5, ecole_pdf_trans($outputlangs, 'SignatureCachet'), 0, 1, $rtl ? 'L' : 'R');

	if ((int) $recu->status === EcoleRecu::STATUS_ANNULE) {
		eleves_pdf_annule($pdf, $outputlangs);
	}
	$pdf->Output(ecole_export_filename('recu_'.$recu->ref, 'pdf'), 'I');
}

/**
 * Reçu au format ticket de caisse (imprimante thermique 80 mm).
 *
 * @param  DoliDB    $db   Handler base
 * @param  EcoleRecu $recu Reçu
 * @return void
 */
function eleves_pdf_recu_ticket($db, $recu)
{
	global $langs, $conf;
	$lang = ecole_pdf_resolve_lang($langs);
	$outputlangs = ecole_pdf_use_lang($lang);
	$outputlangs->loadLangs(array('eleves@eleves', 'bills'));
	$rtl = ($lang === 'ar');
	$company = ecole_pdf_company();

	$h = 95 + 6 * count($recu->lignes);
	$pdf = new EcolePDF('P', 'mm', array(80, $h), true, 'UTF-8', false);
	$fonts = ecole_pdf_fonts();
	$pdf->fontFamily = ecole_pdf_font_choice($rtl); // police choisie dans la configuration
	$pdf->fontSubst = $fonts[$pdf->fontFamily]['subst'];
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(4, 4, 4);
	$pdf->SetAutoPageBreak(false);
	$pdf->AddPage();
	$w = 72;
	$line = function ($txt, $size = 8, $style = '', $align = 'C') use ($pdf, $w, $rtl) {
		ecole_pdf_font($pdf, $style, $size, $rtl);
		$pdf->MultiCell($w, 4, ecole_pdf_bidi(ecole_pdf_text($txt), $rtl), 0, $align, false, 1, 4);
	};
	$sep = function () use ($pdf) {
		$pdf->Line(4, $pdf->GetY() + 1, 76, $pdf->GetY() + 1);
		$pdf->Ln(2.5);
	};
	$line($company['name'], 10, 'B');
	if ($company['address']) {
		$line($company['address'], 7);
	}
	if ($company['phone']) {
		$line($company['phone'], 7);
	}
	$sep();
	$line(ecole_pdf_trans($outputlangs, 'RecuDePaiement').' '.$recu->ref, 9, 'B');
	$line(dol_print_date($recu->date_recu, 'day', 'tzuser', $outputlangs).' — '.eleves_mode_label($db, $recu->fk_mode).($recu->reference_paiement ? ' ('.$recu->reference_paiement.')' : ''), 7);
	$sep();
	$prev = 0;
	foreach ($recu->lignes as $l) {
		if ((int) $l->fk_eleve !== $prev) {
			$line(eleves_pdf_eleve_nom($l->eleve_ref, ecole_label($l), $rtl), 8, 'B', $rtl ? 'R' : 'L');
			$prev = (int) $l->fk_eleve;
		}
		$amount = price($l->montant, 0, $outputlangs);
		$libelle = eleves_ligne_libelle($l);
		$line($rtl ? ($amount.'  '.$libelle) : ($libelle.'  '.$amount), 7, '', $rtl ? 'R' : 'L');
	}
	$sep();
	$line(ecole_pdf_trans($outputlangs, 'Total').' : '.price($recu->montant, 0, $outputlangs, 1, -1, -1, $conf->currency), 10, 'B');
	if ((int) $recu->status === EcoleRecu::STATUS_ANNULE) {
		$line(ecole_pdf_trans($outputlangs, 'RecuAnnuleTampon'), 12, 'B');
	}
	$line(ecole_pdf_trans($outputlangs, 'EnregistrePar').' : '.ecole_user_label($db, $recu->fk_user_creat), 7);
	$pdf->Output(ecole_export_filename('recu_'.$recu->ref, 'pdf'), 'I');
}

/**
 * Attestation de solde d'un élève : ce qui est dû, payé et reste à payer à ce jour.
 *
 * @param  DoliDB     $db     Handler base
 * @param  EcoleEleve $eleve  Élève
 * @return void
 */
function eleves_pdf_attestation($db, $eleve)
{
	global $langs, $conf, $mysoc;
	list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', 'A4');
	$outputlangs->loadLangs(array('eleves@eleves', 'classes@classes'));
	$s = eleves_situation($db, $eleve);
	$today = dol_now();
	ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'AttestationSolde'), eleves_annee_label(eleves_annee_scolaire()), $eleve->ref);
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$pdf->SetTextColor(40, 40, 50);

	$classe = ecole_pdf_text(ecole_row_label($db, 'ecole_classe', $eleve->fk_classe));
	ecole_pdf_font($pdf, '', 10, $rtl);
	$texte = ecole_pdf_trans($outputlangs, 'AttestationTexte', eleves_pdf_ltr(ecole_pdf_text($pdf->company['name']), $rtl), eleves_pdf_ltr(ecole_label($eleve), $rtl),
		eleves_pdf_ltr($eleve->ref, $rtl), eleves_pdf_ltr($classe, $rtl), eleves_pdf_ltr(dol_print_date($today, 'day', 'tzuser', $outputlangs), $rtl));
	$pdf->MultiCell($w, 6, $texte, 0, $rtl ? 'R' : 'L', false, 1, $m['left']);
	$pdf->Ln(3);

	$rows = array();
	$ins = $s['inscription'];
	if ($ins['du'] > 0 || $ins['paye'] > 0 || !empty($ins['exonere'])) {
		$rows[] = array(ecole_pdf_trans($outputlangs, 'FraisInscription').(!empty($ins['exonere']) ? ' ('.ecole_pdf_trans($outputlangs, 'ExonereDe', price($ins['exonere'], 0, $outputlangs)).')' : ''), price($ins['du'], 0, $outputlangs), price($ins['paye'], 0, $outputlangs), price($ins['reste'], 0, $outputlangs));
	}
	foreach ($s['mois'] as $mo) {
		$rows[] = array(ecole_pdf_trans($outputlangs, 'MensualiteDe', $mo['label']), price($mo['du'], 0, $outputlangs), price($mo['paye'], 0, $outputlangs), price($mo['reste'], 0, $outputlangs));
	}
	$rows[] = array(ecole_pdf_trans($outputlangs, 'Total'), price($s['total_du'], 0, $outputlangs), price($s['total_paye'], 0, $outputlangs), price($s['reste'], 0, $outputlangs));
	ecole_pdf_table($pdf, array(ecole_pdf_trans($outputlangs, 'Libelle'), ecole_pdf_trans($outputlangs, 'MontantDu'), ecole_pdf_trans($outputlangs, 'DejaPaye'), ecole_pdf_trans($outputlangs, 'Reste')),
		array(3, 1.3, 1.3, 1.3), array('L', 'R', 'R', 'R'), $rows);
	$pdf->Ln(4);

	ecole_pdf_font($pdf, 'B', 10, $rtl);
	if ($s['impaye'] > 0) {
		$conclusion = ecole_pdf_trans($outputlangs, 'AttestationImpaye', price($s['impaye'], 0, $outputlangs, 1, -1, -1, $conf->currency), dol_print_date($today, 'day', 'tzuser', $outputlangs));
	} else {
		$conclusion = ecole_pdf_trans($outputlangs, 'AttestationAJour', dol_print_date($today, 'day', 'tzuser', $outputlangs));
	}
	$pdf->MultiCell($w, 6, $conclusion, 0, $rtl ? 'R' : 'L', false, 1, $m['left']);
	$pdf->Ln(10);
	ecole_pdf_font($pdf, '', 10, $rtl);
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'FaitLe', $ville, dol_print_date($today, 'day', 'tzuser', $outputlangs)), 0, 1, $rtl ? 'L' : 'R');
	$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'LaDirection'), 0, 1, $rtl ? 'L' : 'R');
	ecole_pdf_signature_cachet($pdf);

	$pdf->Output(ecole_export_filename('attestation_'.$eleve->ref, 'pdf'), 'I');
}

/**
 * Attestation d'inscription d'un élève (remise au parent après la validation de l'inscription) :
 * identité, matricule, classe, année scolaire, date d'inscription, frais d'inscription payés ou exonérés,
 * « Fait à ..., le ... », signature et cachet de la direction.
 *
 * @param  DoliDB     $db    Handler base
 * @param  EcoleEleve $eleve Élève
 * @return void
 */
function eleves_pdf_attestation_inscription($db, $eleve)
{
	global $langs, $conf, $mysoc;
	list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', 'A4');
	$outputlangs->loadLangs(array('eleves@eleves', 'classes@classes'));
	$today = dol_now();
	$annee = eleves_annee_label(eleves_annee_scolaire());
	ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'AttestationInscription'), $annee, $eleve->ref);
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$align = $rtl ? 'R' : 'L';
	$pdf->SetTextColor(40, 40, 50);
	$pdf->Ln(6);

	// Texte d'attestation
	$classe = ecole_pdf_text(ecole_row_label($db, 'ecole_classe', $eleve->fk_classe));
	$ecole = is_object($mysoc) ? ecole_pdf_text($mysoc->name) : '';
	$feminin = ($eleve->sexe === 'F');
	ecole_pdf_font($pdf, '', 11, $rtl);
	$texte = ecole_pdf_trans($outputlangs, $feminin ? 'AttestationInscriptionTexteF' : 'AttestationInscriptionTexteM', eleves_pdf_ltr($ecole, $rtl),
		eleves_pdf_ltr(ecole_pdf_text(ecole_label($eleve)), $rtl), eleves_pdf_ltr($classe, $rtl), eleves_pdf_ltr($annee, $rtl));
	$pdf->MultiCell($w, 7, $texte, 0, $align, false, 1, $m['left']);
	$pdf->Ln(4);

	// Fiche d'identité et de scolarité
	$naissance = '';
	if (!empty($eleve->date_naissance)) {
		$naissance = dol_print_date($eleve->date_naissance, 'day', 'tzuser', $outputlangs);
		if (!empty($eleve->lieu_naissance)) {
			$naissance = ecole_pdf_trans($outputlangs, 'NeLeA', $naissance, ecole_pdf_text($eleve->lieu_naissance));
		}
	}
	$lignes = array(
		array(ecole_pdf_trans($outputlangs, 'NomComplet'), ecole_pdf_text(ecole_label($eleve))),
		array(ecole_pdf_trans($outputlangs, 'Matricule'), $eleve->ref),
		array(ecole_pdf_trans($outputlangs, 'DateLieuNaissance'), $naissance),
		array(ecole_pdf_trans($outputlangs, 'Classe'), $classe),
		array(ecole_pdf_trans($outputlangs, 'AnneeScolaire'), $annee),
		array(ecole_pdf_trans($outputlangs, 'DateInscription'), $eleve->date_inscription ? dol_print_date($eleve->date_inscription, 'day', 'tzuser', $outputlangs) : ''),
	);

	// Frais d'inscription : payés, exonérés ou restant dus
	$s = eleves_situation($db, $eleve);
	$ins = $s['inscription'];
	if (!empty($ins['exonere']) && $ins['du'] <= 0) {
		$frais = ecole_pdf_trans($outputlangs, 'EtatExonere');
		$motif = eleves_motif_exo_label($db, $eleve->fk_motif_exo);
		$frais .= $motif !== '' ? ' ('.ecole_pdf_text($motif).')' : '';
	} elseif ($ins['brut'] <= 0) {
		$frais = ecole_pdf_trans($outputlangs, 'AucunFraisInscription');
	} else {
		$frais = ecole_pdf_trans($outputlangs, 'FraisPayesSur', price($ins['paye'], 0, $outputlangs, 1, -1, -1, $conf->currency), price($ins['du'], 0, $outputlangs, 1, -1, -1, $conf->currency));
		if (!empty($ins['exonere'])) {
			$frais .= ' — '.ecole_pdf_trans($outputlangs, 'ExonereDe', price($ins['exonere'], 0, $outputlangs, 1, -1, -1, $conf->currency));
		}
	}
	$lignes[] = array(ecole_pdf_trans($outputlangs, 'FraisInscription'), $frais);

	$lw = $w * 0.34;
	$vw = $w - $lw;
	foreach ($lignes as $l) {
		if ($l[1] === '') {
			continue;
		}
		$y = $pdf->GetY();
		ecole_pdf_font($pdf, 'B', 10, $rtl);
		$pdf->SetFillColor(242, 244, 248);
		$xl = $rtl ? $m['left'] + $vw : $m['left'];
		$xv = $rtl ? $m['left'] : $m['left'] + $lw;
		$pdf->MultiCell($lw, 8, $l[0], 'B', $align, true, 0, $xl, $y, true, 0, false, true, 8, 'M');
		ecole_pdf_font($pdf, '', 10, $rtl);
		$pdf->MultiCell($vw, 8, eleves_pdf_ltr((string) $l[1], $rtl), 'B', $align, false, 1, $xv, $y, true, 0, false, true, 8, 'M');
	}
	$pdf->Ln(6);

	ecole_pdf_font($pdf, '', 10, $rtl);
	$pdf->MultiCell($w, 6, ecole_pdf_trans($outputlangs, 'AttestationInscriptionFin'), 0, $align, false, 1, $m['left']);
	$pdf->Ln(10);
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'FaitLe', $ville, dol_print_date($today, 'day', 'tzuser', $outputlangs)), 0, 1, $rtl ? 'L' : 'R');
	ecole_pdf_font($pdf, 'B', 10, $rtl);
	$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'LaDirection'), 0, 1, $rtl ? 'L' : 'R');
	ecole_pdf_signature_cachet($pdf);

	$pdf->Output(ecole_export_filename('attestation_inscription_'.$eleve->ref, 'pdf'), 'I');
}
