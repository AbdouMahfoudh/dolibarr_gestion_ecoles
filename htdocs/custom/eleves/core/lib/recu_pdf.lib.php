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
	// Modèle de reçu choisi à l'impression (&modele=), sinon le modèle par défaut : langue, mise en page, options
	dol_include_once('/classes/class/ecole_pdf_modele.class.php');
	$modele = EcolePdfModele::charger($db, 'recu', GETPOSTINT('modele'));
	list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', $format === 'A4' ? 'A4' : 'A5', $modele ? $modele->langue : '');
	ecole_pdf_modele_appliquer($pdf, $modele);
	$outputlangs->loadLangs(array('eleves@eleves', 'bills', 'classes@classes'));
	ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'RecuDePaiement'), dol_print_date($recu->date_recu, 'day', 'tzuser', $outputlangs), $recu->ref);
	if ($format !== 'A4') {
		$pdf->SetMargins(10, $pdf->getMargins()['top'], 10);
	}
	$pdf->SetTextColor(40, 40, 50);
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];

	$resp = null;
	if ($recu->fk_responsable > 0) {
		$r = new EcoleResponsable($db);
		if ($r->fetch((int) $recu->fk_responsable) > 0) {
			$resp = $r;
		}
	}
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
	// Situation après ce paiement (reste en retard par élève)
	$situations = array();
	if ((int) $recu->status === EcoleRecu::STATUS_VALIDE && ecole_pdf_option($pdf, 'opt_situation', true)) {
		$objs = array();
		foreach ($eleves as $eid) {
			$e = new EcoleEleve($db);
			if ($e->fetch($eid) > 0) {
				$objs[] = $e;
			}
		}
		$sits = eleves_situations($db, $objs);
		foreach ($objs as $e) {
			$si = $sits[(int) $e->id];
			$situations[] = array(eleves_pdf_eleve_nom($e->ref, ecole_label($e), $rtl), ($si['impaye'] > 0) ? ecole_pdf_trans($outputlangs, 'ResteImpayeAuJour', price($si['impaye'], 0, $outputlangs, 1, -1, -1, $conf->currency)) : ecole_pdf_trans($outputlangs, 'AJourAuJour'));
		}
	}

	// Corps du reçu (imprimé deux fois avec l'option « souche » : exemplaire du payeur + exemplaire de l'école)
	$corps = function ($exemplaire) use ($db, $pdf, $outputlangs, $rtl, $recu, $resp, $rows, $situations, $conf, $w) {
		if ($exemplaire !== '') {
			ecole_pdf_titre_section($pdf, $exemplaire);
		}
		if ($resp) {
			eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'RecuDe'), ecole_label($resp).($resp->telephone ? ' — '.$resp->telephone : ''), 9, 'B');
		}
		eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'DatePaiement'), dol_print_date($recu->date_recu, 'day', 'tzuser', $outputlangs));
		eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'ModePaiement'), eleves_mode_label($db, $recu->fk_mode).($recu->reference_paiement ? ' ('.$recu->reference_paiement.')' : ''));
		$pdf->Ln(2);
		ecole_pdf_table($pdf, array(ecole_pdf_trans($outputlangs, 'Eleve'), ecole_pdf_trans($outputlangs, 'Libelle'), ecole_pdf_trans($outputlangs, 'Montant').' ('.$conf->currency.')'),
			array(3, 3.2, 1.4), array('L', 'L', 'R'), $rows);
		if (ecole_pdf_option($pdf, 'opt_lettres_recu', false)) {
			$pdf->Ln(1);
			eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'PdfArreteSomme'), ecole_montant_lettres($recu->montant, $pdf), 8, 'B');
		}
		$pdf->Ln(3);
		foreach ($situations as $si) {
			eleves_pdf_kv($pdf, $si[0], $si[1], 8);
		}
		if ((int) $recu->status !== EcoleRecu::STATUS_VALIDE) {
			eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'RecuAnnule'), (string) $recu->motif_annulation, 9, 'B');
		}
		// Caissier et signature
		$pdf->Ln(4);
		if (ecole_pdf_option($pdf, 'opt_caissier', true)) {
			eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'EnregistrePar'), ecole_user_label($db, $recu->fk_user_creat), 8);
		}
		if (ecole_pdf_option($pdf, 'opt_signature_recu', true)) {
			ecole_pdf_font($pdf, '', 8, $rtl);
			$pdf->Ln(2);
			$pdf->Cell($w, 5, ecole_pdf_trans($outputlangs, 'SignatureCachet'), 0, 1, $rtl ? 'L' : 'R');
			ecole_pdf_signature_cachet($pdf, 18.0);
		}
	};

	if (ecole_pdf_option($pdf, 'opt_souche', false)) {
		$corps(ecole_pdf_trans($outputlangs, 'PdfExemplairePayeur'));
		// Ligne de découpe puis exemplaire de l'école
		$pdf->Ln(4);
		$m = $pdf->getMargins();
		$pdf->SetDrawColor(150, 150, 160);
		$pdf->SetLineStyle(array('width' => 0.3, 'dash' => '3,2', 'color' => array(150, 150, 160)));
		$pdf->Line($m['left'], $pdf->GetY(), $m['left'] + $w, $pdf->GetY());
		$pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
		$pdf->Ln(4);
		$corps(ecole_pdf_trans($outputlangs, 'PdfExemplaireEcole').' — '.$recu->ref);
	} else {
		$corps('');
	}

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
		array(ecole_pdf_trans($outputlangs, 'NumeroAppel'), $eleve->numero_appel ? (string) (int) $eleve->numero_appel : ''),
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

	// Responsable (parent) : nom, lien de parenté, matricule, téléphones, e-mail, adresse
	$resp = array();
	if ((int) $eleve->fk_responsable > 0) {
		dol_include_once('/eleves/class/ecole_responsable.class.php');
		$r = new EcoleResponsable($db);
		if ($r->fetch((int) $eleve->fk_responsable) > 0) {
			$liens = $r->fields['lien_parente']['arrayofkeyval'];
			$resp = array(
				array(ecole_pdf_trans($outputlangs, 'NomComplet'), ecole_pdf_text(ecole_label($r))),
				array(ecole_pdf_trans($outputlangs, 'LienParente'), (!empty($r->lien_parente) && isset($liens[$r->lien_parente])) ? ecole_pdf_trans($outputlangs, $liens[$r->lien_parente]) : ''),
				array(ecole_pdf_trans($outputlangs, 'Matricule'), (string) $r->ref),
				array(ecole_pdf_trans($outputlangs, 'Telephone'), (string) $r->telephone),
				array(ecole_pdf_trans($outputlangs, 'WhatsApp'), (string) $r->whatsapp),
				array(ecole_pdf_trans($outputlangs, 'Email'), (string) $r->email),
				array(ecole_pdf_trans($outputlangs, 'Adresse'), ecole_pdf_text(str_replace(array("\r", "\n"), ' ', (string) $r->adresse))),
			);
		}
	}

	$lw = $w * 0.34;
	$vw = $w - $lw;
	$bloc = function ($titre, $lignes) use ($pdf, $m, $w, $lw, $vw, $rtl, $align) {
		ecole_pdf_font($pdf, 'B', 11, $rtl);
		$c = $pdf->tableColor;
		$pdf->SetTextColor($c[0], $c[1], $c[2]);
		$pdf->Cell($w, 7, $titre, 0, 1, $align);
		$pdf->SetTextColor(40, 40, 50);
		foreach ($lignes as $l) {
			if ($l[1] === '') {
				continue;
			}
			$y = $pdf->GetY();
			ecole_pdf_font($pdf, 'B', 10, $rtl);
			$pdf->SetFillColor(242, 244, 248);
			$xl = $rtl ? $m['left'] + $vw : $m['left'];
			$xv = $rtl ? $m['left'] : $m['left'] + $lw;
			$pdf->MultiCell($lw, 7.5, $l[0], 'B', $align, true, 0, $xl, $y, true, 0, false, true, 7.5, 'M');
			ecole_pdf_font($pdf, '', 10, $rtl);
			$pdf->MultiCell($vw, 7.5, eleves_pdf_ltr((string) $l[1], $rtl), 'B', $align, false, 1, $xv, $y, true, 0, false, true, 7.5, 'M');
		}
		$pdf->Ln(4);
	};
	$bloc(ecole_pdf_trans($outputlangs, 'Eleve'), $lignes);
	if (!empty($resp)) {
		$bloc(ecole_pdf_trans($outputlangs, 'Responsable'), $resp);
	}
	$pdf->Ln(2);

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

/**
 * Engagement du responsable (un seul document) : tous les engagements actifs de la configuration
 * (règlement intérieur, paiement...), variables remplacées, puis « Fait à ..., le ... » et signatures
 * du responsable (et de l'élève à partir du collège si un engagement le demande).
 *
 * @param  DoliDB     $db    Handler base
 * @param  EcoleEleve $eleve Élève
 * @return string            '' si OK (PDF envoyé), sinon message d'erreur
 */
function eleves_pdf_engagement($db, $eleve)
{
	global $langs, $conf, $mysoc;
	dol_include_once('/eleves/class/ecole_engagement.class.php');
	dol_include_once('/eleves/class/ecole_responsable.class.php');
	$engagements = EcoleEngagement::actifs($db);
	if (empty($engagements)) {
		return $langs->trans('AucunEngagementActif');
	}
	list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', 'A4');
	$outputlangs->loadLangs(array('eleves@eleves', 'classes@classes'));
	$today = dol_now();
	$annee = eleves_annee_label(eleves_annee_scolaire());
	ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, 'EngagementResponsable'), $annee, $eleve->ref);
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$align = $rtl ? 'R' : 'L';

	// Valeurs des variables
	$resp = new EcoleResponsable($db);
	$aResp = ((int) $eleve->fk_responsable > 0 && $resp->fetch((int) $eleve->fk_responsable) > 0);
	$liens = $resp->fields['lien_parente']['arrayofkeyval'];
	$s = eleves_situation($db, $eleve);
	$mensualite = 0;
	foreach ($s['mois'] as $mo) {
		if ($mo['dans'] && $mo['du'] > 0) {
			$mensualite = $mo['du'];
			break;
		}
	}
	$periodes = eleves_periodes();
	$classe = ecole_pdf_text(ecole_row_label($db, 'ecole_classe', $eleve->fk_classe));
	$prix = function ($v) use ($outputlangs, $conf) {
		return price($v, 0, $outputlangs, 1, -1, -1, $conf->currency);
	};
	$vars = array(
		'[responsable]' => $aResp ? ecole_pdf_text(ecole_label($resp)) : '....................',
		'[lien]' => ($aResp && !empty($resp->lien_parente) && isset($liens[$resp->lien_parente])) ? ecole_pdf_trans($outputlangs, $liens[$resp->lien_parente]) : '..........',
		'[eleve]' => ecole_pdf_text(ecole_label($eleve)),
		'[matricule]' => $eleve->ref,
		'[classe]' => $classe,
		'[annee]' => $annee,
		'[ecole]' => is_object($mysoc) ? ecole_pdf_text($mysoc->name) : '',
		'[frais_inscription]' => (!empty($s['inscription']['exonere']) && $s['inscription']['du'] <= 0) ? ecole_pdf_trans($outputlangs, 'EtatExonere') : $prix($s['inscription']['du']),
		'[mensualite]' => $prix($mensualite),
		'[jour_limite]' => (string) min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10))),
		'[premier_mois]' => !empty($periodes) ? eleves_periode_label(reset($periodes)) : '',
		'[dernier_mois]' => !empty($periodes) ? eleves_periode_label(end($periodes)) : '',
	);

	// Élève à partir du collège (niveau autre que maternelle et primaire)
	$collegeOuPlus = false;
	$resql = $db->query("SELECT n.ref FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau WHERE c.rowid = ".((int) $eleve->fk_classe));
	if ($resql && ($o = $db->fetch_object($resql))) {
		$collegeOuPlus = !in_array((string) $o->ref, array('MAT', 'PRI'), true);
	}
	$signatureEleve = false;

	$pdf->SetTextColor(40, 40, 50);
	foreach ($engagements as $eng) {
		$titre = $rtl ? ((string) $eng->label_ar !== '' ? $eng->label_ar : $eng->label_fr) : ((string) $eng->label_fr !== '' ? $eng->label_fr : $eng->label_ar);
		$texte = $rtl ? ((string) $eng->texte_ar !== '' ? $eng->texte_ar : $eng->texte_fr) : ((string) $eng->texte_fr !== '' ? $eng->texte_fr : $eng->texte_ar);
		$texte = strtr((string) $texte, $vars);
		if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
			$pdf->AddPage();
		}
		ecole_pdf_titre_section($pdf, $titre);
		ecole_pdf_font($pdf, '', 10, $rtl);
		foreach (preg_split('/\r\n|\r|\n/', $texte) as $ligne) {
			$ligne = trim($ligne);
			if ($ligne === '') {
				$pdf->Ln(2);
				continue;
			}
			if (preg_match('/^[-•*]\s*(.*)$/u', $ligne, $r)) {
				// Puce, avec retrait
				$retrait = 6;
				$pdf->MultiCell($w - $retrait, 5.5, ecole_pdf_bidi('• '.$r[1], $rtl), 0, $align, false, 1, $rtl ? $m['left'] : $m['left'] + $retrait);
			} else {
				$pdf->MultiCell($w, 5.5, ecole_pdf_bidi($ligne, $rtl), 0, $align, false, 1, $m['left']);
			}
		}
		$pdf->Ln(3);
		if ((int) $eng->signature_eleve && $collegeOuPlus) {
			$signatureEleve = true;
		}
	}

	// Lieu, date et signatures
	if ($pdf->GetY() > $pdf->getPageHeight() - 55) {
		$pdf->AddPage();
	}
	$pdf->Ln(4);
	ecole_pdf_font($pdf, '', 10, $rtl);
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'FaitLe', $ville, dol_print_date($today, 'day', 'tzuser', $outputlangs)), 0, 1, $rtl ? 'L' : 'R');
	$pdf->Ln(4);
	ecole_pdf_font($pdf, 'B', 10, $rtl);
	$cases = array(ecole_pdf_trans($outputlangs, 'SignatureResponsable'));
	if ($signatureEleve) {
		$cases[] = ecole_pdf_trans($outputlangs, 'SignatureDeEleve');
	}
	$cases[] = ecole_pdf_trans($outputlangs, 'LaDirection');
	if ($rtl) {
		$cases = array_reverse($cases);
	}
	$cw = $w / count($cases);
	$y = $pdf->GetY();
	foreach ($cases as $i => $c) {
		$pdf->SetXY($m['left'] + $i * $cw, $y);
		$pdf->Cell($cw, 6, $c, 0, 0, 'C');
	}
	$pdf->Ln(8);
	ecole_pdf_signature_cachet($pdf, 22.0);
	$pdf->Output(ecole_export_filename('engagement_'.$eleve->ref, 'pdf'), 'I');
	return '';
}
