<?php
/**
 * Fiche de paie PDF (A4) : identité de l'employé, période, calcul du salaire (gains, retenues, net à payer),
 * résumé de la présence, signatures ; puis, pour un enseignant, le détail des séances de la période
 * (réglage SALAIRES_PDF_SEANCES). Langue détectée (arabe / français), en-tête de l'établissement choisi dans la
 * configuration du module Classes. Un lot donne un seul PDF avec la fiche de chaque employé.
 *
 * Fichier : custom/salaires/core/lib/salaires_pdf.lib.php
 */

dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');

/**
 * PDF des fiches de paie : l'en-tête complet de l'établissement est dessiné en haut de la première page de
 * chaque fiche (plusieurs fiches dans un même document), les autres pages ont une simple marge.
 */
class SalairesPDF extends EcolePDF
{
	/** @var array<int,array{0:string,1:string,2:string}> Page => titre, sous-titre, référence */
	public $entetes = array();

	/**
	 * En-tête : style de la configuration sur la première page de chaque fiche.
	 *
	 * @return void
	 */
	public function Header()
	{
		$p = $this->getPage();
		if (!isset($this->entetes[$p])) {
			$this->SetTopMargin(12);
			$this->SetY(12);
			return;
		}
		list($this->docTitle, $this->docSubtitle, $this->docRef) = $this->entetes[$p];
		$registry = ecole_pdf_header_registry();
		$style = isset($registry[$this->headerStyle]) ? $this->headerStyle : 'bandeau_bleu';
		$this->SetTopMargin(ecole_pdf_header_top($this, $style));
		$wasRtl = $this->getRTL();
		$this->setRTL(false);
		call_user_func($registry[$style]['render'], $this, $this->company);
		$this->setRTL($wasRtl);
		$this->SetTextColor(0, 0, 0);
		$this->SetY(ecole_pdf_header_top($this, $style));
	}
}

/**
 * Crée le PDF des fiches de paie dans la langue détectée.
 *
 * @param  Translate $langs Langue de l'interface
 * @return array{0:SalairesPDF,1:Translate,2:bool}
 */
function salaires_pdf_create($langs)
{
	$lang = ecole_pdf_resolve_lang($langs);
	$outputlangs = ecole_pdf_use_lang($lang);
	$outputlangs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'bills'));
	$rtl = ($lang === 'ar');
	$pdf = new SalairesPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->outputlangs = $outputlangs;
	$pdf->isRtl = $rtl;
	$pdf->company = ecole_pdf_company();
	$pdf->headerStyle = ecole_pdf_header_style();
	$fonts = ecole_pdf_fonts();
	$pdf->fontFamily = ecole_pdf_font_choice($rtl);
	$pdf->fontSubst = $fonts[$pdf->fontFamily]['subst'];
	$pdf->setRTL(false);
	$pdf->SetCreator('Dolibarr - '.$outputlangs->transnoentities('BulletinDePaie'));
	$pdf->SetAuthor($pdf->company['name']);
	$pdf->setFontSubsetting(true);
	$pdf->SetAutoPageBreak(true, 18);
	// Modèle de bulletin de paie choisi à l'impression (&modele=), sinon le modèle par défaut
	global $db;
	dol_include_once('/classes/class/ecole_pdf_modele.class.php');
	ecole_pdf_modele_appliquer($pdf, EcolePdfModele::charger($db, 'paie', GETPOSTINT('modele')));
	return array($pdf, $outputlangs, $rtl);
}

/**
 * Ligne « Libellé : valeur » dans le sens de lecture.
 *
 * @param  SalairesPDF $pdf   PDF
 * @param  string      $label Libellé
 * @param  string      $value Valeur
 * @param  float       $size  Taille
 * @param  string      $style Style de la valeur
 * @return void
 */
function salaires_pdf_kv($pdf, $label, $value, $size = 9, $style = '')
{
	$rtl = $pdf->isRtl;
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$wl = round($w * 0.30, 2);
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
 * Dans un document arabe, garde dans l'ordre les mots d'un morceau écrit en lettres latines (matricule, nom ou
 * poste saisi en français, montant, « 12 h ») : marqueurs Unicode « gauche à droite ». Sans effet en français ou
 * si le texte contient de l'arabe.
 *
 * @param  string $s   Texte
 * @param  bool   $rtl Document en arabe
 * @return string
 */
function salaires_pdf_ltr($s, $rtl)
{
	$s = (string) $s;
	if (!$rtl || $s === '' || preg_match('/\p{Arabic}/u', $s)) {
		return $s;
	}
	return "\u{202A}".$s."\u{202C}";
}

/**
 * Titre de section.
 *
 * @param  SalairesPDF $pdf   PDF
 * @param  string      $titre Titre
 * @return void
 */
function salaires_pdf_titre($pdf, $titre)
{
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$pdf->Ln(2);
	ecole_pdf_font($pdf, 'B', 10, $pdf->isRtl);
	$c = $pdf->tableColor; // couleur du modèle de bulletin de paie
	$pdf->SetTextColor($c[0], $c[1], $c[2]);
	$pdf->Cell($w, 6, ecole_pdf_bidi(ecole_pdf_text($titre), $pdf->isRtl), 0, 1, $pdf->isRtl ? 'R' : 'L');
	$pdf->SetTextColor(40, 40, 50);
}

/**
 * Tampon en travers de la page (BROUILLON, ANNULÉ).
 *
 * @param  SalairesPDF $pdf   PDF
 * @param  string      $texte Texte
 * @param  int[]       $rgb   Couleur
 * @return void
 */
function salaires_pdf_tampon($pdf, $texte, $rgb)
{
	$pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
	ecole_pdf_font($pdf, 'B', 44, $pdf->isRtl);
	$pdf->StartTransform();
	$cx = $pdf->getPageWidth() / 2;
	$cy = $pdf->getPageHeight() / 2;
	$pdf->Rotate(30, $cx, $cy);
	$pdf->SetAlpha(0.18);
	$w = $pdf->GetStringWidth($texte);
	$pdf->Text($cx - $w / 2, $cy - 10, $texte);
	$pdf->SetAlpha(1);
	$pdf->StopTransform();
	$pdf->SetTextColor(40, 40, 50);
}

/**
 * Ajoute la fiche de paie d'un bulletin au PDF (nouvelle page avec l'en-tête de l'établissement).
 *
 * @param  DoliDB       $db PDF
 * @param  SalairesPDF  $pdf PDF
 * @param  EcoleSalaire $b   Bulletin
 * @return void
 */
function salaires_pdf_fiche($db, $pdf, $b)
{
	global $conf;
	$ol = $pdf->outputlangs;
	$rtl = $pdf->isRtl;
	$registry = ecole_pdf_header_registry();
	$emp = new EcoleEmploye($db);
	$emp->fetch((int) $b->fk_employe);
	$calc = $b->getCalcul();
	$m = function ($v) use ($ol, $conf) {
		return price(price2num((float) $v, 'MT'), 0, $ol, 1, -1, -1, $conf->currency);
	};
	// Dates sans heure : affichées telles qu'enregistrées (pas de conversion de fuseau, sinon décalage d'un jour)
	$periode = ecole_pdf_trans($ol, 'PeriodeDuAu', dol_print_date($b->date_debut, 'day', 'tzserver', $ol), dol_print_date($b->date_fin, 'day', 'tzserver', $ol));

	$moisLabel = ucfirst($ol->transnoentities('Month'.substr((string) $b->mois, 5, 2))).' '.substr((string) $b->mois, 0, 4);
	$pdf->entetes[$pdf->getNumPages() + 1] = array(ecole_pdf_trans($ol, 'FicheDePaie').' — '.$moisLabel, $periode, $b->ref);
	$pdf->SetMargins(12, ecole_pdf_header_top($pdf, $pdf->headerStyle), 12);
	$pdf->AddPage();
	$pageFiche = $pdf->getPage();
	$pdf->SetTextColor(40, 40, 50);

	// Employé et période (en arabe, chaque morceau en lettres latines garde l'ordre de ses mots)
	$ltr = function ($s) use ($rtl) {
		return salaires_pdf_ltr($s, $rtl);
	};
	$nom = ecole_label($emp);
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Employe'), $rtl ? $ltr($nom).' - '.$ltr($emp->ref) : $emp->ref.' - '.$nom, 10, 'B');
	$poste = trim((string) $emp->poste);
	$cats = personnel_categories_texte((string) $emp->categories, $ol);
	if ($poste !== '' || $cats !== '') {
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'PosteEmploye'), trim($ltr($poste).($poste !== '' && $cats !== '' ? ' — ' : '').$ltr($cats)));
	}
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'MoisSalaire'), $moisLabel, 10, 'B');
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Periode'), $periode);
	if ($b->mode_paie === 'fixe') {
		$txt = ecole_pdf_trans($ol, 'ModeFixe').' — '.ecole_pdf_trans($ol, 'SalaireMensuel').' : '.$ltr($m($b->salaire_base));
		if ((float) $b->heures_semaine > 0) {
			$txt .= ' — '.ecole_pdf_trans($ol, 'HeuresPrevuesSemaine').' : '.$ltr(price2num((float) $b->heures_semaine).' h');
		}
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'ModeDePaie'), $txt);
	} elseif ($b->mode_paie === 'heure') {
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'ModeDePaie'), ecole_pdf_trans($ol, 'ModeHeure').' — '.ecole_pdf_trans($ol, 'PrixHeure').' : '.$ltr($m($b->taux_horaire)));
	}
	if ((int) $b->status === EcoleSalaire::STATUS_PAYE) {
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Paiement'), dol_print_date($b->date_paiement, 'day', 'tzserver', $ol).' — '.$ltr(salaires_mode_numero_texte($db, (int) $b->fk_mode, (string) $b->numero_compte)).($b->reference_paiement ? ' ('.$ltr($b->reference_paiement).')' : ''));
	}

	// Calcul du salaire
	salaires_pdf_titre($pdf, ecole_pdf_trans($ol, 'CalculDuSalaire'));
	$rows = array();
	foreach ($b->getLignes() as $l) {
		$rows[] = array(salaires_ligne_libelle($l, $ol), salaires_ligne_detail($l, $ol), $l->type === 'gain' ? $m($l->montant) : '', $l->type === 'retenue' ? $m($l->montant) : '');
	}
	$rows[] = array(ecole_pdf_trans($ol, 'Total'), '', $m($b->total_gains), $m($b->total_retenues));
	ecole_pdf_table($pdf, array(ecole_pdf_trans($ol, 'Libelle'), ecole_pdf_trans($ol, 'DetailCalcul'), ecole_pdf_trans($ol, 'Gain'), ecole_pdf_trans($ol, 'Retenue')),
		array(3, 3, 1.5, 1.5), array('L', 'L', 'R', 'R'), $rows);

	// Net à payer
	$mg = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $mg['left'] - $mg['right'];
	$pdf->Ln(2);
	ecole_pdf_font($pdf, 'B', 12, $rtl);
	$pdf->SetFillColor(236, 241, 252);
	$pdf->SetDrawColor(66, 92, 199);
	$net = ecole_pdf_trans($ol, 'NetAPayer').' : '.$m($b->net);
	$pdf->Cell($w, 10, ecole_pdf_bidi($net, $rtl), 1, 1, 'C', true);
	$pdf->SetTextColor(40, 40, 50);

	// Présence de la période
	if ((int) $b->minutes_prevues > 0 || (int) $b->nb_remplacements > 0) {
		salaires_pdf_titre($pdf, ecole_pdf_trans($ol, 'PresencePeriode'));
		$heads = array(ecole_pdf_trans($ol, 'CoursPrevus'), ecole_pdf_trans($ol, 'CoursFaits'), ecole_pdf_trans($ol, 'AbsencesNonJustifiees'),
			ecole_pdf_trans($ol, 'AbsencesJustifiees'), ecole_pdf_trans($ol, 'RemplacementsFaits'));
		$vals = array(personnel_duree($b->minutes_prevues), personnel_duree($b->minutes_faites), personnel_duree($b->minutes_absent_nj),
			personnel_duree($b->minutes_absent_j), personnel_duree($b->minutes_rempl));
		if ($b->mode_paie === 'fixe' && (float) $b->heures_semaine > 0) {
			$heads[] = ecole_pdf_trans($ol, 'HeuresSup');
			$vals[] = personnel_duree($b->minutes_hsup);
		}
		ecole_pdf_table($pdf, $heads, array_fill(0, count($heads), 1), array_fill(0, count($heads), 'C'), array($vals));
	} elseif ((float) $b->jours_absent_nj + (float) $b->jours_absent_j > 0) {
		salaires_pdf_titre($pdf, ecole_pdf_trans($ol, 'PresencePeriode'));
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'JoursAbsence'), ecole_pdf_trans($ol, 'AbsJustNonJust', price2num((float) $b->jours_absent_j), price2num((float) $b->jours_absent_nj)));
	}

	// Note pour l'employé
	if (trim((string) $b->note_public) !== '') {
		$pdf->Ln(2);
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'NotePublicBulletin'), $ltr((string) $b->note_public));
	}

	// Signatures
	$pdf->Ln(8);
	ecole_pdf_font($pdf, 'B', 9, $rtl);
	$gauche = ecole_pdf_trans($ol, 'SignatureEmploye');
	$droite = ecole_pdf_trans($ol, 'SignatureDirection');
	$pdf->Cell($w / 2, 6, $rtl ? $droite : $gauche, 0, 0, 'C');
	$pdf->Cell($w / 2, 6, $rtl ? $gauche : $droite, 0, 1, 'C');

	// Tampon
	if ((int) $b->status === EcoleSalaire::STATUS_BROUILLON) {
		$pdf->setPage($pageFiche);
		salaires_pdf_tampon($pdf, ecole_pdf_trans($ol, 'TamponBrouillon'), array(120, 120, 140));
		$pdf->setPage($pdf->getNumPages());
	} elseif ((int) $b->status === EcoleSalaire::STATUS_ANNULE) {
		$pdf->setPage($pageFiche);
		salaires_pdf_tampon($pdf, ecole_pdf_trans($ol, 'TamponAnnule'), array(220, 40, 40));
		$pdf->setPage($pdf->getNumPages());
	}

	// Détail des séances (enseignants)
	$seances = $b->getSeances();
	if ($seances && getDolGlobalString('SALAIRES_PDF_SEANCES', '1')) {
		$pdf->SetMargins(12, 12, 12);
		$pdf->AddPage();
		salaires_pdf_titre($pdf, ecole_pdf_trans($ol, 'SeancesPeriode').' — '.$ltr($b->ref).' — '.($rtl ? $ltr($nom).' - '.$ltr($emp->ref) : $emp->ref.' - '.$nom));
		ecole_pdf_font($pdf, '', 8, $rtl);
		$pdf->Cell($w, 5, ecole_pdf_bidi($periode, $rtl), 0, 1, $rtl ? 'R' : 'L');
		$rows = array();
		$tot = array(0, 0);
		foreach ($seances as $s) {
			$etat = salaires_etat_seance($s->etat, $ol);
			if ($s->etat === 'retard') {
				$etat .= ' ('.$ol->transnoentities('NbMinutes', $s->retard).')';
			} elseif ($s->etat === 'absent') {
				$etat .= ' — '.$ol->transnoentities((int) $s->justifiee ? 'Justifiee' : 'NonJustifiee');
			}
			$tot[0] += (int) $s->minutes;
			$tot[1] += ($s->etat === 'absent') ? 0 : (int) $s->minutes_payees;
			$rows[] = array(dol_print_date(personnel_date_ts(substr($s->date_seance, 0, 10)), 'day', 'tzuser', $ol), $s->creneau.' '.$s->heure_debut.'-'.$s->heure_fin,
				$s->classe, $s->matiere, ecole_pdf_text($etat), personnel_duree($s->minutes), $s->etat === 'absent' ? '' : personnel_duree($s->minutes_payees));
		}
		$rows[] = array(ecole_pdf_trans($ol, 'Total'), '', '', '', '', personnel_duree($tot[0]), personnel_duree($tot[1]));
		ecole_pdf_table($pdf, array(ecole_pdf_trans($ol, 'Date'), ecole_pdf_trans($ol, 'Creneau'), ecole_pdf_trans($ol, 'Classe'), ecole_pdf_trans($ol, 'Matiere'),
			ecole_pdf_trans($ol, 'Presence'), ecole_pdf_trans($ol, 'Duree'), ecole_pdf_trans($ol, 'HeuresPayees')),
			array(1.3, 1.6, 1, 2.2, 2.2, 0.9, 0.9), array('C', 'C', 'C', 'L', 'L', 'C', 'C'), $rows);
	}
}

/**
 * Reçu d'avance sur salaire ou reconnaissance de prêt, à signer par l'employé : montant, versement, conditions de
 * retenue (et échéancier prévu d'un prêt).
 *
 * @param  DoliDB             $db     Handler base
 * @param  EcoleSalaireAvance $object Avance ou prêt (EcoleSalairePret)
 * @return void                       PDF envoyé au navigateur
 */
function salaires_pdf_avance_pret($db, $object)
{
	global $langs, $conf, $mysoc;
	$pret = ($object->typeRetenue === 'pret');
	list($pdf, $ol, $rtl) = salaires_pdf_create($langs);
	$emp = new EcoleEmploye($db);
	$emp->fetch((int) $object->fk_employe);
	$ltr = function ($s) use ($rtl) {
		return salaires_pdf_ltr($s, $rtl);
	};
	$m = function ($v) use ($ol, $conf) {
		return price(price2num((float) $v, 'MT'), 0, $ol, 1, -1, -1, $conf->currency);
	};
	$champ = $object->champDate;
	$date = dol_print_date($object->$champ, 'day', 'tzserver', $ol);
	$registry = ecole_pdf_header_registry();
	$pdf->entetes[1] = array(ecole_pdf_trans($ol, $pret ? 'ReconnaissancePret' : 'RecuAvance'), $date, $object->ref);
	$pdf->SetMargins(12, ecole_pdf_header_top($pdf, $pdf->headerStyle), 12);
	$pdf->AddPage();
	$pdf->SetTextColor(40, 40, 50);
	$mg = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $mg['left'] - $mg['right'];

	$nom = ecole_label($emp);
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Employe'), $rtl ? $ltr($nom).' - '.$ltr($emp->ref) : $emp->ref.' - '.$nom, 10, 'B');
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Date'), $date);
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Montant'), $ltr($m($object->montant)), 11, 'B');
	salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'ModePaiement'), $ltr(salaires_mode_numero_texte($db, (int) $object->fk_mode, (string) $object->numero_compte)).($object->reference_paiement ? ' ('.$ltr($object->reference_paiement).')' : ''));
	if ($pret) {
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'Mensualites'), $ltr(((int) $object->nb_echeances).' × '.$m($object->montant_echeance)));
		salaires_pdf_kv($pdf, ecole_pdf_trans($ol, 'PremierMoisRetenue'), ucfirst($ol->transnoentities('Month'.substr((string) $object->premier_mois, 5, 2))).' '.substr((string) $object->premier_mois, 0, 4));
	}
	$pdf->Ln(4);

	// Engagement de l'employé
	ecole_pdf_font($pdf, '', 10, $rtl);
	if ($pret) {
		$texte = ecole_pdf_trans($ol, 'TextePret', $ltr($nom), $ltr(ecole_pdf_text($pdf->company['name'])), $ltr($m($object->montant)), (int) $object->nb_echeances, $ltr($m($object->montant_echeance)));
	} else {
		$texte = ecole_pdf_trans($ol, 'TexteAvance', $ltr($nom), $ltr(ecole_pdf_text($pdf->company['name'])), $ltr($m($object->montant)));
	}
	$pdf->MultiCell($w, 6, ecole_pdf_bidi($texte, $rtl), 0, $rtl ? 'R' : 'L', false, 1, $mg['left']);

	// Échéancier prévu
	if ($pret && (int) $object->status === EcoleSalaireAvance::STATUS_VERSE) {
		$ech = $object->echeancier();
		$rows = array();
		foreach ($ech['retenues'] as $r) {
			if ((float) $r->montant > 0) { // déjà filtré : bulletins validés ou payés
				$mr = $r->mois ? $r->mois : dol_print_date($db->jdate($r->date_fin), '%Y-%m', 'tzserver');
				$rows[] = array(ucfirst($ol->transnoentities('Month'.substr($mr, 5, 2))).' '.substr($mr, 0, 4).' ('.$ltr($r->ref).')', $m($r->montant), ecole_pdf_trans($ol, 'Retenu'));
			}
		}
		foreach ($ech['prevues'] as $e) {
			$rows[] = array(ucfirst($ol->transnoentities('Month'.substr($e['mois'], 5, 2))).' '.substr($e['mois'], 0, 4), $m($e['montant']), ecole_pdf_trans($ol, 'Prevu'));
		}
		if ($rows) {
			salaires_pdf_titre($pdf, ecole_pdf_trans($ol, 'Echeancier'));
			ecole_pdf_table($pdf, array(ecole_pdf_trans($ol, 'Mois'), ecole_pdf_trans($ol, 'Mensualite'), ecole_pdf_trans($ol, 'Etat')), array(2, 1.5, 1.5), array('L', 'R', 'C'), $rows);
		}
	}

	// Lieu, date, signatures
	$pdf->Ln(8);
	ecole_pdf_font($pdf, '', 10, $rtl);
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	$pdf->Cell($w, 6, ecole_pdf_trans($ol, 'FaitLeSalaires', $ltr($ville), $date), 0, 1, $rtl ? 'R' : 'L');
	$pdf->Ln(4);
	ecole_pdf_font($pdf, 'B', 9, $rtl);
	$g = ecole_pdf_trans($ol, 'SignatureEmploye');
	$d = ecole_pdf_trans($ol, 'SignatureDirection');
	$pdf->Cell($w / 2, 6, $rtl ? $d : $g, 0, 0, 'C');
	$pdf->Cell($w / 2, 6, $rtl ? $g : $d, 0, 1, 'C');
	if ((int) $object->status === EcoleSalaireAvance::STATUS_ANNULE) {
		salaires_pdf_tampon($pdf, ecole_pdf_trans($ol, 'TamponAnnule'), array(220, 40, 40));
	}
	$pdf->Output(ecole_export_filename(($pret ? 'pret_' : 'avance_').$object->ref, 'pdf'), 'I');
}

/**
 * Envoie au navigateur un PDF avec la fiche de paie de chaque bulletin.
 *
 * @param  DoliDB         $db        Handler base
 * @param  EcoleSalaire[] $bulletins Bulletins
 * @param  string         $nom       Nom du fichier (sans extension)
 * @return void
 */
function salaires_pdf_output($db, $bulletins, $nom)
{
	global $langs;
	list($pdf) = salaires_pdf_create($langs);
	foreach ($bulletins as $b) {
		salaires_pdf_fiche($db, $pdf, $b);
	}
	if ($pdf->getNumPages() === 0) {
		$pdf->AddPage();
	}
	$pdf->SetTitle(ecole_pdf_text($nom));
	$pdf->Output(ecole_export_filename($nom, 'pdf'), 'I');
}
