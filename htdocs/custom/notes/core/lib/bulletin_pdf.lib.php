<?php
/**
 * Documents PDF du module Notes : bulletin trimestriel, relevé annuel, certificat de scolarité.
 *
 * Le bulletin suit un modèle (llx_ecole_bulletin_modele) :
 *  - langue « mixte » : page de gauche à droite, titres en français et en arabe, chaque matière écrite
 *    dans sa langue d'enseignement ; « fr » : tout en français ; « ar » : tout en arabe, de droite à gauche ;
 *  - style visuel : classique (bleu), moderne (vert), sobre (noir et blanc, pour la photocopie) ;
 *  - colonnes et blocs affichés (détail devoirs / composition, coefficients, rangs, moyennes de la classe...).
 * L'en-tête de l'établissement est celui choisi dans la configuration du module Classes, sur chaque page.
 *
 * Fichier : custom/notes/core/lib/bulletin_pdf.lib.php
 */

dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/notes/core/lib/calcul.lib.php');
dol_include_once('/notes/class/ecole_bulletin_modele.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

/**
 * PDF des bulletins : en-tête de l'établissement sur chaque page (un élève par page).
 */
class NotesPDF extends EcolePDF
{
	/**
	 * Retire le texte « Powered by TCPDF » ajouté par TCPDF en bas de la dernière page.
	 *
	 * @return void
	 */
	public function sansLienTcpdf()
	{
		$this->tcpdflink = false;
	}

	/**
	 * En-tête du style choisi sur toutes les pages.
	 *
	 * @return void
	 */
	public function Header()
	{
		if ($this->headerStyle === NOTES_ENTETE_NB) {
			$this->enteteNoirBlanc();
			return;
		}
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

	/**
	 * En-tête en noir et blanc (bulletins « sans couleur ») : logo, établissement et coordonnées centrés,
	 * double filet noir, titre, sous-titre et référence.
	 *
	 * @return void
	 */
	protected function enteteNoirBlanc()
	{
		$this->SetTopMargin(NOTES_ENTETE_NB_HAUT);
		$wasRtl = $this->getRTL();
		$this->setRTL(false);
		$pageW = $this->getPageWidth();
		$marge = 12.0;
		$w = $pageW - 2 * $marge;
		$y = 8.0;
		if (!empty($this->company['logo_path'])) {
			try {
				$this->Image($this->company['logo_path'], ($pageW - 30.0) / 2, 6.0, 30.0, 13.0, '', '', '', false, 300, '', false, false, 0, true);
				$y = 20.5;
			} catch (Exception $e) {
				// logo illisible : ignoré
			}
		}
		$this->SetTextColor(0, 0, 0);
		ecole_pdf_font($this, 'B', 14, $this->isRtl);
		$this->SetXY($marge, $y);
		$this->Cell($w, 6.5, $this->company['name'] !== '' ? $this->company['name'] : '-', 0, 0, 'C');
		$y += 7;
		$contact = array_filter(array($this->company['address'], $this->company['phone'], $this->company['email']));
		if (!empty($contact)) {
			ecole_pdf_font($this, '', 7.5, $this->isRtl);
			$this->SetTextColor(60, 60, 60);
			$this->SetXY($marge, $y);
			$this->Cell($w, 4, implode('    ', $contact), 0, 0, 'C');
			$y += 5;
		}
		$y += 1;
		$this->SetDrawColor(0, 0, 0);
		$this->SetLineWidth(0.6);
		$this->Line($marge, $y, $pageW - $marge, $y);
		$this->SetLineWidth(0.2);
		$this->Line($marge, $y + 1.1, $pageW - $marge, $y + 1.1);
		$y += 3.5;
		$this->SetTextColor(0, 0, 0);
		ecole_pdf_font($this, 'B', 13, $this->isRtl);
		$this->SetXY($marge, $y);
		$this->Cell($w, 6, ecole_pdf_text($this->docTitle), 0, 0, 'C');
		$y += 6;
		ecole_pdf_font($this, '', 8.5, $this->isRtl);
		$sous = trim(ecole_pdf_text($this->docSubtitle).($this->docRef !== '' ? '   -   '.ecole_pdf_ref_line($this, ecole_pdf_text($this->docRef)) : ''), ' -');
		$this->SetXY($marge, $y);
		$this->Cell($w, 5, $sous, 0, 0, 'C');
		$this->setRTL($wasRtl);
		$this->SetY(NOTES_ENTETE_NB_HAUT);
	}

	/**
	 * Pied de page : trait noir pour les bulletins en noir et blanc, sinon celui des documents de l'école.
	 *
	 * @return void
	 */
	public function Footer()
	{
		if ($this->headerStyle !== NOTES_ENTETE_NB) {
			parent::Footer();
			return;
		}
		$this->ecoleFiligrane();
		$m = $this->getMargins();
		$w = $this->getPageWidth() - $m['left'] - $m['right'];
		$this->SetY(-14);
		$this->SetDrawColor(0, 0, 0);
		$this->SetLineWidth(0.3);
		$this->Line($m['left'], $this->GetY(), $m['left'] + $w, $this->GetY());
		$this->SetY(-12);
		ecole_pdf_font($this, '', 7, $this->isRtl);
		$this->SetTextColor(60, 60, 60);
		$left = ecole_pdf_trans($this->outputlangs, 'EcolePdfFooter', dol_print_date(dol_now(), 'dayhour', 'tzuser', $this->outputlangs));
		$right = ecole_pdf_trans($this->outputlangs, 'Page').' '.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
		$this->Cell($w / 2, 4, $this->isRtl ? $right : $left, 0, 0, 'L');
		$this->Cell($w / 2, 4, $this->isRtl ? $left : $right, 0, 0, 'R');
	}
}

/** En-tête noir et blanc propre aux bulletins, et hauteur réservée en haut de page */
define('NOTES_ENTETE_NB', 'noir_blanc');
define('NOTES_ENTETE_NB_HAUT', 50.0);

/**
 * Contexte d'écriture d'un document : langue(s), sens, couleurs du style.
 */
class NotesDoc
{
	/** @var NotesPDF */
	public $pdf;
	/** @var string 'mixte' | 'fr' | 'ar' */
	public $mode = 'mixte';
	/** @var bool Arabe de droite à gauche */
	public $rtl = false;
	/** @var Translate Français */
	public $fr;
	/** @var Translate Arabe */
	public $ar;
	/** @var array Couleurs du style */
	public $c = array();
	/** @var string Mise en page : classique | lignes | encadre */
	public $layout = 'classique';

	/**
	 * Texte traduit selon le mode : « Français / عربي » en mixte.
	 *
	 * @param  string $key     Clé
	 * @param  mixed  ...$args Paramètres
	 * @return string
	 */
	public function t($key, ...$args)
	{
		if ($this->mode === 'ar') {
			return ecole_pdf_trans($this->ar, $key, ...$args);
		}
		if ($this->mode === 'fr') {
			return ecole_pdf_trans($this->fr, $key, ...$args);
		}
		return ecole_pdf_trans($this->fr, $key, ...$args).' / '.ecole_pdf_trans($this->ar, $key, ...$args);
	}

	/**
	 * Titre de colonne : sur deux lignes en mixte (français puis arabe).
	 *
	 * @param  string $key     Clé
	 * @param  mixed  ...$args Paramètres
	 * @return string
	 */
	public function th($key, ...$args)
	{
		if ($this->mode === 'mixte') {
			return ecole_pdf_trans($this->fr, $key, ...$args)."\n".ecole_pdf_trans($this->ar, $key, ...$args);
		}
		return $this->t($key, ...$args);
	}

	/**
	 * Libellé d'une ligne bilingue (label_fr / label_ar ou nom_fr / nom_ar) selon le mode ;
	 * en mixte, $langue impose une langue (matière), sinon les deux.
	 *
	 * @param  object      $o      Ligne
	 * @param  string|null $langue 'fr' | 'ar' | null
	 * @return string
	 */
	public function lib($o, $langue = null)
	{
		$fr = isset($o->label_fr) ? (string) $o->label_fr : (isset($o->nom_fr) ? (string) $o->nom_fr : '');
		$ar = isset($o->label_ar) ? (string) $o->label_ar : (isset($o->nom_ar) ? (string) $o->nom_ar : '');
		if ($this->mode === 'ar' || ($this->mode === 'mixte' && $langue === 'ar')) {
			return $ar !== '' ? $ar : $fr;
		}
		if ($this->mode === 'fr' || $langue === 'fr') {
			return $fr;
		}
		return $fr.($ar !== '' && $ar !== $fr ? ' / '.$ar : '');
	}

	/**
	 * Traductions de la langue principale du document (dates, en-tête).
	 *
	 * @return Translate
	 */
	public function main()
	{
		return $this->mode === 'ar' ? $this->ar : $this->fr;
	}
}

/**
 * Couleurs proposées pour les bulletins : clé => array(clé de traduction, couleur principale RVB).
 * « aucune » : noir et blanc, sans aucun fond coloré (photocopie, imprimante noir et blanc).
 *
 * @return array<string,array{0:string,1:int[]}>
 */
function notes_pdf_couleurs_liste()
{
	return array(
		'bleu' => array('CouleurBleu', array(25, 71, 130)),
		'vert' => array('CouleurVert', array(0, 121, 107)),
		'bordeaux' => array('CouleurBordeaux', array(128, 28, 52)),
		'violet' => array('CouleurViolet', array(94, 53, 150)),
		'orange' => array('CouleurOrange', array(190, 94, 14)),
		'gris' => array('CouleurGris', array(78, 86, 98)),
		'aucune' => array('CouleurAucune', array(0, 0, 0)),
	);
}

/**
 * Couleurs d'un bulletin : fond des titres, texte des titres, traits, lignes alternées, cadres, accent, totaux, texte.
 * Anciens styles acceptés : « moderne » = vert, « sobre » = sans couleur.
 *
 * @param  string $couleur Couleur (voir notes_pdf_couleurs_liste)
 * @return array
 */
function notes_pdf_couleurs($couleur)
{
	$couleur = ($couleur === 'moderne') ? 'vert' : (($couleur === 'sobre') ? 'aucune' : $couleur);
	$liste = notes_pdf_couleurs_liste();
	if ($couleur === 'aucune') {
		return array('head' => array(255, 255, 255), 'headtxt' => array(0, 0, 0), 'line' => array(0, 0, 0), 'alt' => array(255, 255, 255),
			'box' => array(255, 255, 255), 'accent' => array(0, 0, 0), 'total' => array(255, 255, 255), 'txt' => array(0, 0, 0), 'aucune' => true);
	}
	$b = isset($liste[$couleur]) ? $liste[$couleur][1] : $liste['bleu'][1];
	$mix = function ($t) use ($b) {
		return array((int) round($b[0] + (255 - $b[0]) * $t), (int) round($b[1] + (255 - $b[1]) * $t), (int) round($b[2] + (255 - $b[2]) * $t));
	};
	return array('head' => $b, 'headtxt' => array(255, 255, 255), 'line' => $mix(0.62), 'alt' => $mix(0.94), 'box' => $mix(0.91),
		'accent' => $b, 'total' => $mix(0.84), 'txt' => array(30, 34, 42), 'aucune' => false);
}

/**
 * Crée le document dans la langue du modèle, avec sa mise en page, sa couleur et son en-tête.
 *
 * @param  string      $mode    mixte | fr | ar
 * @param  string      $style   Mise en page : classique | lignes | encadre (anciens : moderne, sobre)
 * @param  string      $couleur Couleur (voir notes_pdf_couleurs_liste)
 * @param  string|null $entete  Style d'en-tête (vide = celui de la configuration du module Classes)
 * @return NotesDoc
 */
function notes_pdf_doc($mode, $style, $couleur = 'bleu', $entete = null)
{
	global $conf;
	$d = new NotesDoc();
	$d->mode = in_array($mode, array('mixte', 'fr', 'ar'), true) ? $mode : 'mixte';
	$d->rtl = ($d->mode === 'ar');
	foreach (array('fr' => 'fr_FR', 'ar' => 'ar_SA') as $k => $code) {
		$t = new Translate('', $conf);
		$t->setDefaultLang($code);
		$t->loadLangs(array('main', 'other', 'classes@classes', 'eleves@eleves', 'notes@notes'));
		$d->$k = $t;
	}
	// La langue principale sert aussi aux fonctions globales (dates, séparateur décimal, en-tête)
	$main = ecole_pdf_use_lang($d->mode === 'ar' ? 'ar' : 'fr');
	$main->loadLangs(array('eleves@eleves', 'notes@notes'));

	$pdf = new NotesPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->sansLienTcpdf();
	$pdf->outputlangs = $main;
	$pdf->isRtl = $d->rtl;
	$pdf->company = ecole_pdf_company($d->mode === 'ar');
	// Bulletin bilingue : nom français et nom arabe de l'établissement (s'il est réglé)
	if ($d->mode === 'mixte' && ecole_nom_etablissement(true) !== ecole_nom_etablissement(false)) {
		$pdf->company['name'] = ecole_pdf_text(ecole_nom_etablissement(false).' / '.ecole_nom_etablissement(true));
	}
	if ($style === 'moderne' || $style === 'sobre') {
		$couleur = ($style === 'moderne') ? 'vert' : 'aucune';
		$style = 'classique';
	}
	$registry = ecole_pdf_header_registry();
	if ($entete === NOTES_ENTETE_NB || (empty($entete) && $couleur === 'aucune')) {
		// Sans couleur : en-tête noir et blanc (sauf si un autre en-tête est choisi pour le modèle)
		$pdf->headerStyle = NOTES_ENTETE_NB;
	} else {
		$pdf->headerStyle = (!empty($entete) && isset($registry[$entete])) ? $entete : ecole_pdf_header_style();
	}
	$fonts = ecole_pdf_fonts();
	$pdf->fontFamily = ecole_pdf_font_choice($d->mode !== 'fr'); // du texte arabe : police des documents arabes
	$pdf->fontSubst = $fonts[$pdf->fontFamily]['subst'];
	$pdf->setRTL(false);
	$pdf->SetCreator('Dolibarr - Notes');
	$pdf->SetAuthor($pdf->company['name']);
	$pdf->setFontSubsetting(true);
	$pdf->SetMargins(12, $pdf->headerStyle === NOTES_ENTETE_NB ? NOTES_ENTETE_NB_HAUT : ecole_pdf_header_top($pdf, $pdf->headerStyle), 12);
	$pdf->SetAutoPageBreak(true, 18);
	$d->pdf = $pdf;
	$d->layout = in_array($style, array('classique', 'lignes', 'encadre'), true) ? $style : 'classique';
	$d->c = notes_pdf_couleurs($couleur);
	return $d;
}

/**
 * Tableau stylé selon la mise en page :
 *  - classique : grille complète, titres sur fond de couleur ;
 *  - lignes    : traits horizontaux seulement, titres en couleur soulignés, aéré ;
 *  - encadre   : cadre arrondi autour du tableau, titres sur fond de couleur, séparations horizontales.
 * Lignes : tableau de textes ou array('cells' => ..., 'bold' => bool, 'fill' => rgb). Colonnes inversées en arabe.
 *
 * @param  NotesDoc $d       Document
 * @param  string[] $headers Titres
 * @param  float[]  $ratios  Largeurs relatives
 * @param  string[] $aligns  Alignements (L, C, R)
 * @param  array    $rows    Lignes
 * @param  float    $size    Taille de police
 * @return void
 */
function notes_pdf_table($d, $headers, $ratios, $aligns, $rows, $size = 8.5)
{
	$pdf = $d->pdf;
	$rtl = $d->rtl;
	$layout = $d->layout;
	$m = $pdf->getMargins();
	$x0 = $m['left'];
	$pageW = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$sum = array_sum($ratios);
	$widths = array();
	foreach ($ratios as $r) {
		$widths[] = $pageW * $r / $sum;
	}
	if ($rtl) {
		$widths = array_reverse($widths);
		$headers = array_reverse($headers);
		$aligns = array_map(function ($a) {
			return ecole_pdf_align($a, true);
		}, array_reverse($aligns));
	}
	$c = $d->c;
	$sansCouleur = !empty($c['aucune']);
	$bordCell = ($layout === 'classique') ? 1 : 'B';
	$topTable = null;

	$printHeader = function () use ($pdf, $headers, $widths, $rtl, $c, $size, $layout, $x0, $pageW, $sansCouleur, &$topTable) {
		ecole_pdf_font($pdf, 'B', $size - 0.5, $rtl);
		$h = 7;
		foreach ($headers as $i => $label) {
			$h = max($h, $pdf->getStringHeight($widths[$i], $label) + 1);
		}
		$y = $pdf->GetY();
		$topTable = $y;
		$pdf->SetLineWidth(0.2);
		if ($layout === 'lignes') {
			// Titres en couleur, sans fond, soulignés d'un trait épais
			$pdf->SetTextColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
			$x = $x0;
			foreach ($headers as $i => $label) {
				$pdf->MultiCell($widths[$i], $h, $label, 0, 'C', false, 0, $x, $y, true, 0, false, true, $h, 'M');
				$x += $widths[$i];
			}
			$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
			$pdf->SetLineWidth(0.6);
			$pdf->Line($x0, $y + $h, $x0 + $pageW, $y + $h);
			$pdf->SetLineWidth(0.2);
		} else {
			$fill = !$sansCouleur;
			$pdf->SetFillColor($c['head'][0], $c['head'][1], $c['head'][2]);
			$pdf->SetTextColor($c['headtxt'][0], $c['headtxt'][1], $c['headtxt'][2]);
			$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
			$x = $x0;
			foreach ($headers as $i => $label) {
				$pdf->MultiCell($widths[$i], $h, $label, $layout === 'classique' ? 1 : 0, 'C', $fill, 0, $x, $y, true, 0, false, true, $h, 'M');
				$x += $widths[$i];
			}
			if ($layout === 'encadre' || $sansCouleur) {
				$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
				$pdf->SetLineWidth($sansCouleur ? 0.4 : 0.2);
				$pdf->Line($x0, $y + $h, $x0 + $pageW, $y + $h);
				$pdf->SetLineWidth(0.2);
			}
		}
		$pdf->SetXY($x0, $y + $h);
	};
	// Cadre arrondi du tableau (mise en page « encadré ») sur la partie de la page courante
	$cadre = function ($bas) use ($pdf, $layout, $c, $x0, $pageW, &$topTable) {
		if ($layout !== 'encadre' || $topTable === null) {
			return;
		}
		$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->SetLineWidth(0.5);
		$pdf->RoundedRect($x0, $topTable, $pageW, $bas - $topTable, 2, '1111', 'D');
		$pdf->SetLineWidth(0.2);
	};
	$printHeader();

	$bottom = $pdf->getPageHeight() - 18;
	$alt = false;
	foreach ($rows as $row) {
		$cells = isset($row['cells']) ? $row['cells'] : $row;
		$bold = !empty($row['bold']);
		$cells = array_map(function ($x) use ($rtl) {
			return ecole_pdf_bidi((string) $x, $rtl);
		}, array_values($cells));
		if ($rtl) {
			$cells = array_reverse($cells);
		}
		ecole_pdf_font($pdf, $bold ? 'B' : '', $size, $rtl);
		$h = ($layout === 'lignes') ? 7.2 : 6.5;
		foreach ($cells as $i => $x) {
			$h = max($h, $pdf->getStringHeight($widths[$i], $x) + 1);
		}
		if ($pdf->GetY() + $h > $bottom) {
			$cadre($pdf->GetY());
			$pdf->AddPage();
			$printHeader();
			ecole_pdf_font($pdf, $bold ? 'B' : '', $size, $rtl);
		}
		$alt = !$alt;
		if (isset($row['fill'])) {
			$fill = $row['fill'];
		} elseif ($bold) {
			$fill = $c['total'];
		} else {
			$fill = ($alt && $layout !== 'lignes') ? $c['alt'] : array(255, 255, 255);
		}
		$pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
		$pdf->SetTextColor($c['txt'][0], $c['txt'][1], $c['txt'][2]);
		$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
		$pdf->SetAutoPageBreak(false, 0);
		$x = $x0;
		$y = $pdf->GetY();
		if ($bold && $layout !== 'classique') {
			// Ligne des totaux : trait au-dessus
			$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
			$pdf->SetLineWidth(0.4);
			$pdf->Line($x0, $y, $x0 + $pageW, $y);
			$pdf->SetLineWidth(0.2);
			$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
		}
		foreach ($cells as $i => $txt) {
			$pdf->MultiCell($widths[$i], $h, $txt, $bordCell, $aligns[$i], !$sansCouleur || $bold, 0, $x, $y, true, 0, false, true, $h, 'M');
			$x += $widths[$i];
		}
		$pdf->SetAutoPageBreak(true, 18);
		$pdf->SetXY($x0, $y + $h);
	}
	$cadre($pdf->GetY());
}

/**
 * Bloc d'informations en deux colonnes de paires « libellé : valeur », selon la mise en page :
 * cadre coloré (classique), traits au-dessus et au-dessous (lignes), cadre avec barre de couleur (encadré).
 *
 * @param  NotesDoc $d      Document
 * @param  array    $paires Liste de array(libellé, valeur, gras?)
 * @param  float    $size   Taille de police
 * @return void
 */
function notes_pdf_infos($d, $paires, $size = 9)
{
	$pdf = $d->pdf;
	$rtl = $d->rtl;
	$c = $d->c;
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$col = $w / 2;
	$wl = $col * 0.42;
	$wv = $col - $wl;
	$rowsN = (int) ceil(count($paires) / 2);
	$lh = 5.6;
	// Hauteur réelle (valeurs longues sur plusieurs lignes)
	$heights = array();
	for ($r = 0; $r < $rowsN; $r++) {
		$h = $lh;
		for ($k = 0; $k < 2; $k++) {
			$p = isset($paires[$r * 2 + $k]) ? $paires[$r * 2 + $k] : null;
			if ($p) {
				ecole_pdf_font($pdf, !empty($p[2]) ? 'B' : '', $size, $rtl);
				$h = max($h, $pdf->getStringHeight($wv, ecole_pdf_bidi($p[1], $rtl)), $pdf->getStringHeight($wl, ecole_pdf_bidi($p[0], $rtl)));
			}
		}
		$heights[$r] = $h;
	}
	$y0 = $pdf->GetY();
	$total = array_sum($heights) + 2;
	if ($y0 + $total > $pdf->getPageHeight() - 18) {
		$pdf->AddPage();
		$y0 = $pdf->GetY();
	}
	$pdf->SetLineWidth(0.2);
	if ($d->layout === 'lignes') {
		$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->Line($m['left'], $y0, $m['left'] + $w, $y0);
		$pdf->Line($m['left'], $y0 + $total, $m['left'] + $w, $y0 + $total);
	} elseif ($d->layout === 'encadre') {
		$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->SetFillColor($c['box'][0], $c['box'][1], $c['box'][2]);
		$pdf->SetLineWidth(0.5);
		$pdf->RoundedRect($m['left'], $y0, $w, $total, 2, '1111', empty($c['aucune']) ? 'DF' : 'D');
		$pdf->SetLineWidth(0.2);
		// Barre de couleur du côté du début de lecture
		$pdf->SetFillColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->Rect($rtl ? $m['left'] + $w - 1.6 : $m['left'], $y0 + 1, 1.6, $total - 2, 'F');
	} else {
		$pdf->SetFillColor($c['box'][0], $c['box'][1], $c['box'][2]);
		$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
		$pdf->RoundedRect($m['left'], $y0, $w, $total, 1.5, '1111', empty($c['aucune']) ? 'DF' : 'D');
	}
	$y = $y0 + 1;
	$gris = !empty($c['aucune']) ? array(60, 60, 60) : array(95, 100, 110);
	for ($r = 0; $r < $rowsN; $r++) {
		for ($k = 0; $k < 2; $k++) {
			$p = isset($paires[$r * 2 + $k]) ? $paires[$r * 2 + $k] : null;
			if (!$p) {
				continue;
			}
			// Première paire à gauche en français, à droite en arabe
			$x = $rtl ? ($m['left'] + $w - ($k + 1) * $col) : ($m['left'] + $k * $col);
			$pdf->SetTextColor($gris[0], $gris[1], $gris[2]);
			ecole_pdf_font($pdf, '', $size - 0.5, $rtl);
			$lab = ecole_pdf_bidi($p[0], $rtl);
			$val = ecole_pdf_bidi($p[1], $rtl);
			if ($rtl) {
				$pdf->MultiCell($wl - 2, $heights[$r], $lab, 0, 'R', false, 0, $x + $wv, $y, true, 0, false, true, $heights[$r], 'T');
				$pdf->SetTextColor($c['txt'][0], $c['txt'][1], $c['txt'][2]);
				ecole_pdf_font($pdf, !empty($p[2]) ? 'B' : '', $size, $rtl);
				$pdf->MultiCell($wv, $heights[$r], $val, 0, 'R', false, 0, $x + 1, $y, true, 0, false, true, $heights[$r], 'T');
			} else {
				$pdf->MultiCell($wl, $heights[$r], $lab, 0, 'L', false, 0, $x + 2.5, $y, true, 0, false, true, $heights[$r], 'T');
				$pdf->SetTextColor($c['txt'][0], $c['txt'][1], $c['txt'][2]);
				ecole_pdf_font($pdf, !empty($p[2]) ? 'B' : '', $size, $rtl);
				$pdf->MultiCell($wv - 1, $heights[$r], $val, 0, 'L', false, 0, $x + $wl + 1, $y, true, 0, false, true, $heights[$r], 'T');
			}
		}
		$y += $heights[$r];
	}
	$pdf->SetXY($m['left'], $y0 + $total + 2);
}

/**
 * Cadre « Observation de la direction » (lignes vides pour écrire à la main si aucune observation),
 * selon la mise en page : cadre arrondi, trait au-dessus, ou cadre avec bandeau de titre coloré.
 *
 * @param  NotesDoc $d     Document
 * @param  string   $texte Observation
 * @return void
 */
function notes_pdf_observation($d, $texte)
{
	$pdf = $d->pdf;
	$rtl = $d->rtl;
	$c = $d->c;
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$texte = trim((string) $texte);
	ecole_pdf_font($pdf, '', 9, $rtl);
	$h = max(14, ($texte !== '' ? $pdf->getStringHeight($w - 4, $texte) : 0) + 8);
	if ($pdf->GetY() + $h > $pdf->getPageHeight() - 18) {
		$pdf->AddPage();
	}
	$y = $pdf->GetY();
	$titreY = $y + 1;
	$pdf->SetLineWidth(0.2);
	if ($d->layout === 'lignes') {
		$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->Line($m['left'], $y, $m['left'] + $w, $y);
		$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
		$pdf->Line($m['left'], $y + $h, $m['left'] + $w, $y + $h);
		$pdf->SetTextColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
	} elseif ($d->layout === 'encadre') {
		$pdf->SetDrawColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
		$pdf->SetLineWidth(0.5);
		$pdf->RoundedRect($m['left'], $y, $w, $h, 2, '1111', 'D');
		$pdf->SetLineWidth(0.2);
		if (empty($c['aucune'])) {
			$pdf->SetFillColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
			$pdf->RoundedRect($m['left'], $y, $w, 6, 2, '1001', 'F');
			$pdf->SetTextColor(255, 255, 255);
		} else {
			$pdf->SetTextColor(0, 0, 0);
		}
		$titreY = $y + 0.6;
	} else {
		$pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
		$pdf->RoundedRect($m['left'], $y, $w, $h, 1.5, '1111', 'D');
		$pdf->SetTextColor($c['accent'][0], $c['accent'][1], $c['accent'][2]);
	}
	ecole_pdf_font($pdf, 'B', 8.5, $rtl);
	$pdf->MultiCell($w - 4, 5, $d->t('ObservationDirection'), 0, $rtl ? 'R' : 'L', false, 1, $m['left'] + 2, $titreY);
	$pdf->SetTextColor($c['txt'][0], $c['txt'][1], $c['txt'][2]);
	ecole_pdf_font($pdf, '', 9, $rtl);
	if ($texte !== '') {
		$pdf->MultiCell($w - 4, 5, ecole_pdf_bidi($texte, $rtl), 0, $rtl ? 'R' : 'L', false, 1, $m['left'] + 2, $y + 6.5);
	}
	$pdf->SetXY($m['left'], $y + $h + 3);
}

/**
 * Signatures en bas de page : « Fait à ..., le ... », la direction et (option) les parents.
 *
 * @param  NotesDoc $d       Document
 * @param  bool     $parents Case « Signature des parents »
 * @return void
 */
function notes_pdf_signatures($d, $parents)
{
	global $mysoc;
	$pdf = $d->pdf;
	$rtl = $d->rtl;
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	if ($pdf->GetY() + 26 > $pdf->getPageHeight() - 18) {
		$pdf->AddPage();
	}
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	$date = dol_print_date(dol_now(), 'day', 'tzuser', $d->main());
	ecole_pdf_font($pdf, '', 8.5, $rtl);
	$pdf->SetTextColor($d->c['txt'][0], $d->c['txt'][1], $d->c['txt'][2]);
	$pdf->Cell($w, 5, $d->t('FaitALe', $ville, $date), 0, 1, $rtl ? 'L' : 'R');
	$y = $pdf->GetY() + 1;
	ecole_pdf_font($pdf, 'B', 8.5, $rtl);
	$dir = $d->t('LeDirecteur');
	$par = $d->t('SignatureParents');
	$half = $w / 2;
	if ($parents) {
		$pdf->MultiCell($half, 5, $rtl ? $dir : $par, 0, 'C', false, 0, $m['left'], $y);
		$pdf->MultiCell($half, 5, $rtl ? $par : $dir, 0, 'C', false, 1, $m['left'] + $half, $y);
	} else {
		$pdf->MultiCell($half, 5, $dir, 0, 'C', false, 1, $rtl ? $m['left'] : $m['left'] + $half, $y);
	}
	$pdf->Ln(14);
}

/**
 * Note affichée dans une cellule : moyenne sur 20 ou « Abs » / « Disp » pour une composition.
 *
 * @param  NotesDoc   $d    Document
 * @param  float|null $v    Valeur
 * @param  string     $code Code de la note (composition)
 * @return string
 */
function notes_pdf_note($d, $v, $code = '')
{
	if ($code === NOTES_ABS) {
		return $d->mode === 'ar' ? ecole_pdf_trans($d->ar, 'NoteAbsent') : ecole_pdf_trans($d->fr, 'NoteAbsentCourt');
	}
	if ($code === NOTES_DISP) {
		return $d->mode === 'ar' ? ecole_pdf_trans($d->ar, 'NoteDispense') : ecole_pdf_trans($d->fr, 'NoteDispenseCourt');
	}
	return $v === null ? '-' : notes_moy($v);
}

/**
 * Rang « 3e / 25 » dans la langue du document.
 *
 * @param  NotesDoc $d       Document
 * @param  int|null $rang    Rang
 * @param  bool     $exaequo Ex aequo
 * @param  int      $sur     Nombre d'élèves classés
 * @return string
 */
function notes_pdf_rang($d, $rang, $exaequo, $sur)
{
	if ($rang === null) {
		return '-';
	}
	$l = ($d->mode === 'ar') ? $d->ar : $d->fr;
	return notes_rang_label($rang, $exaequo, $l).' / '.$sur;
}

/**
 * Bloc d'identité de l'élève (nom, matricule, classe, effectif, année scolaire, date de naissance).
 *
 * @param  NotesDoc $d      Document
 * @param  object   $eleve  Élève (ligne notes_eleves)
 * @param  object   $classe Classe (ref, label_fr, label_ar)
 * @param  int      $nb     Effectif classé
 * @return void
 */
function notes_pdf_identite($d, $eleve, $classe, $nb)
{
	global $db;
	$naiss = isset($eleve->naissance) ? (string) $eleve->naissance : ''; // aperçu : date d'exemple
	$resql = $naiss !== '' ? null : $db->query("SELECT date_naissance, lieu_naissance FROM ".$db->prefix()."ecole_eleve WHERE rowid = ".((int) $eleve->rowid));
	if ($resql && ($o = $db->fetch_object($resql)) && $o->date_naissance) {
		$naiss = dol_print_date($db->jdate($o->date_naissance), 'day', 'tzuser', $d->main()).($o->lieu_naissance ? ' - '.$o->lieu_naissance : '');
	}
	$nom = $d->mode === 'mixte' ? trim($eleve->nom_fr.(!empty($eleve->nom_ar) ? '  /  '.$eleve->nom_ar : '')) : $d->lib($eleve);
	$paires = array(
		array($d->t('NomEleve'), $nom, true),
		array($d->t('Matricule'), $eleve->ref),
		array($d->t('Classe'), $classe->ref.' - '.$d->lib($classe)),
		array($d->t('Effectif'), (string) $nb),
		array($d->t('AnneeScolaire'), eleves_annee_label(eleves_annee_scolaire())),
	);
	if ($naiss !== '') {
		$paires[] = array($d->t('DateNaissance'), $naiss);
	}
	notes_pdf_infos($d, $paires, 9);
}

/**
 * Bulletin trimestriel d'un élève (une page), ajouté au document.
 *
 * @param  DoliDB              $db     Handler base
 * @param  NotesDoc            $d      Document
 * @param  EcoleBulletinModele $modele Modèle
 * @param  object              $classe Classe
 * @param  array               $calc   Résultats du trimestre (notes_calcul_trimestre)
 * @param  int                 $eid    Élève
 * @param  array               $conseil Choix du conseil (fk_eleve => ligne)
 * @return void
 */
function notes_pdf_page_bulletin($db, $d, $modele, $classe, $calc, $eid, $conseil)
{
	$pdf = $d->pdf;
	$t = (int) $calc['trimestre'];
	$e = $calc['eleves'][$eid];
	$r = $calc['res'][$eid];
	$pdf->docTitle = $d->t('BulletinDeNotes');
	$pdf->docSubtitle = eleves_annee_label(eleves_annee_scolaire()).' - '.$d->t('Trimestre'.$t); // année d'abord : à côté de l'arabe elle serait inversée
	$pdf->docRef = $e->ref;
	$pdf->AddPage();

	notes_pdf_identite($d, $e, $classe, $calc['nb_classes']);

	// Tableau des matières
	$simple = !empty($modele->simplifie);
	$headers = array($d->th('Matiere'));
	$ratios = array(3.2);
	$aligns = array('L');
	if ($simple) {
		$headers[] = $d->th('NoteObtenue');
		$ratios[] = 1.4;
		$aligns[] = 'C';
	} else {
		if (!empty($modele->aff_detail)) {
			array_push($headers, $d->th('Devoirs'), $d->th('Composition'));
			array_push($ratios, 1.1, 1.1);
			array_push($aligns, 'C', 'C');
		}
		$headers[] = $d->th('MoyenneSur20');
		$ratios[] = 1.1;
		$aligns[] = 'C';
		if (!empty($modele->aff_coef)) {
			array_push($headers, $d->th('Coef'), $d->th('Total'));
			array_push($ratios, 0.7, 1.0);
			array_push($aligns, 'C', 'C');
		}
	}
	if (!empty($modele->aff_rang_matiere)) {
		$headers[] = $d->th('Rang');
		$ratios[] = 0.8;
		$aligns[] = 'C';
	}
	if (!empty($modele->aff_moy_classe)) {
		$headers[] = $d->th('MoyClasse');
		$ratios[] = 1.0;
		$aligns[] = 'C';
	}
	if (!empty($modele->aff_min_max)) {
		array_push($headers, $d->th('NoteMinClasse'), $d->th('NoteMaxClasse'));
		array_push($ratios, 0.9, 0.9);
		array_push($aligns, 'C', 'C');
	}

	$rows = array();
	$totCoef = 0.0;
	$totPoints = 0.0;
	$totMaxPts = 0.0;
	$totNote = 0.0;
	foreach ($calc['matieres'] as $mid => $m) {
		$rm = $r['matieres'][$mid];
		$st = $calc['stats']['matieres'][$mid];
		$ligne = array($d->lib($m, $m->langue));
		if ($simple) {
			// Note complète (ex. 28 / 30) sur le maximum de la matière
			if ($rm['moyenne'] !== null) {
				$pts = $rm['moyenne'] / 20 * (float) $m->note_max;
				$totNote += $pts;
				$totMaxPts += (float) $m->note_max;
				$ligne[] = notes_fmt(round($pts, 2)).' / '.notes_fmt($m->note_max);
			} else {
				$ligne[] = '- / '.notes_fmt($m->note_max);
			}
		} else {
			if (!empty($modele->aff_detail)) {
				$ligne[] = notes_pdf_note($d, $rm['devoirs']);
				$ligne[] = notes_pdf_note($d, $rm['compo'], $rm['compo_code']);
			}
			$ligne[] = notes_pdf_note($d, $rm['moyenne']);
			if (!empty($modele->aff_coef)) {
				$ligne[] = notes_fmt($m->coefficient);
				$ligne[] = $rm['moyenne'] !== null ? notes_moy($rm['moyenne'] * (float) $m->coefficient) : '-';
			}
		}
		if ($rm['moyenne'] !== null) {
			$totCoef += (float) $m->coefficient;
			$totPoints += $rm['moyenne'] * (float) $m->coefficient;
		}
		if (!empty($modele->aff_rang_matiere)) {
			$ligne[] = $rm['rang'] !== null ? (string) $rm['rang'] : '-';
		}
		if (!empty($modele->aff_moy_classe)) {
			$ligne[] = notes_pdf_note($d, $st['moy']);
		}
		if (!empty($modele->aff_min_max)) {
			$ligne[] = notes_pdf_note($d, $st['min']);
			$ligne[] = notes_pdf_note($d, $st['max']);
		}
		$rows[] = $ligne;
	}
	// Ligne des totaux
	$tot = array($d->t('Total'));
	if ($simple) {
		$tot[] = $totMaxPts > 0 ? notes_fmt(round($totNote, 2)).' / '.notes_fmt($totMaxPts) : '-';
	} else {
		if (!empty($modele->aff_detail)) {
			array_push($tot, '', '');
		}
		$tot[] = notes_pdf_note($d, $r['moyenne']);
		if (!empty($modele->aff_coef)) {
			$tot[] = notes_fmt($totCoef);
			$tot[] = notes_moy($totPoints);
		}
	}
	while (count($tot) < count($headers)) {
		$tot[] = '';
	}
	$rows[] = array('cells' => $tot, 'bold' => true);
	notes_pdf_table($d, $headers, $ratios, $aligns, $rows);
	$pdf->Ln(3);

	// Résultats
	$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
	$mention = notes_mention($db, $r['moyenne']);
	$paires = array(array($d->t('MoyenneGenerale'), notes_moy($r['moyenne']).' / 20', true));
	if (!empty($modele->aff_rang)) {
		$paires[] = array($d->t('Rang'), notes_pdf_rang($d, $r['rang'], $r['exaequo'], $calc['nb_classes']), true);
	}
	$paires[] = array($d->t('Mention'), $mention ? $d->lib($mention) : '-', true);
	if (!empty($modele->aff_distinction)) {
		$dist = notes_distinction($db, $c, $r['moyenne']);
		$paires[] = array($d->t('Distinction'), $dist ? $d->lib($dist) : '-', (bool) $dist);
	}
	if (!empty($modele->aff_moy_classe)) {
		$sg = $calc['stats']['generale'];
		$paires[] = array($d->t('MoyenneClasse'), notes_moy($sg['moy']));
		$paires[] = array($d->t('PlusFortePlusFaible'), notes_moy($sg['max']).'  -  '.notes_moy($sg['min']));
	}
	if (!empty($modele->aff_rappel) && $t > 1) {
		for ($p = 1; $p < $t; $p++) {
			if (isset($calc['rappel'][$p])) {
				// Aperçu d'un modèle : trimestres précédents fournis avec les données d'exemple
				$rp = $calc['rappel'][$p];
				$nbp = $calc['nb_classes'];
			} else {
				$prev = notes_calcul_trimestre($db, (int) $classe->rowid, $p);
				$rp = isset($prev['res'][$eid]) ? $prev['res'][$eid] : null;
				$nbp = $prev['nb_classes'];
			}
			$paires[] = array($d->t('MoyenneDuTrimestre'.$p), $rp ? notes_moy($rp['moyenne']).(!empty($modele->aff_rang) && $rp['rang'] !== null ? '  ('.notes_pdf_rang($d, $rp['rang'], $rp['exaequo'], $nbp).')' : '') : '-');
		}
	}
	notes_pdf_infos($d, $paires, 9.5);
	notes_pdf_observation($d, $c ? (string) $c->observation : '');
	notes_pdf_signatures($d, !empty($modele->aff_signature_parent));
}

/**
 * Relevé annuel d'un élève (une page), ajouté au document.
 *
 * @param  DoliDB              $db      Handler base
 * @param  NotesDoc            $d       Document
 * @param  EcoleBulletinModele $modele  Modèle
 * @param  object              $classe  Classe
 * @param  array               $calc    Résultats annuels (notes_calcul_annuel)
 * @param  int                 $eid     Élève
 * @param  array               $conseil Choix du conseil (période 0)
 * @return void
 */
function notes_pdf_page_releve($db, $d, $modele, $classe, $calc, $eid, $conseil)
{
	$pdf = $d->pdf;
	$e = $calc['eleves'][$eid];
	$r = $calc['res'][$eid];
	$regle = $calc['regle'];
	$pdf->docTitle = $d->t('ReleveAnnuel');
	$pdf->docSubtitle = $d->t('AnneeScolaire').' '.eleves_annee_label(eleves_annee_scolaire());
	$pdf->docRef = $e->ref;
	$pdf->AddPage();

	notes_pdf_identite($d, $e, $classe, $calc['nb_classes']);

	$avecCoef = empty($modele->simplifie) && !empty($modele->aff_coef);
	$headers = array($d->th('Matiere'));
	$ratios = array(3.2);
	$aligns = array('L');
	if ($avecCoef) {
		$headers[] = $d->th('Coef');
		$ratios[] = 0.7;
		$aligns[] = 'C';
	}
	for ($t = 1; $t <= 3; $t++) {
		$headers[] = $d->th('TrimestreCourt'.$t);
		$ratios[] = 1.0;
		$aligns[] = 'C';
	}
	$headers[] = $d->th('MoyenneAnnuelle');
	$ratios[] = 1.2;
	$aligns[] = 'C';
	if (!empty($modele->aff_rang_matiere)) {
		$headers[] = $d->th('Rang');
		$ratios[] = 0.8;
		$aligns[] = 'C';
	}
	if (!empty($modele->aff_moy_classe)) {
		$headers[] = $d->th('MoyClasse');
		$ratios[] = 1.0;
		$aligns[] = 'C';
	}
	$rows = array();
	foreach ($calc['matieres'] as $mid => $m) {
		$rm = $r['matieres'][$mid];
		$ligne = array($d->lib($m, $m->langue));
		if ($avecCoef) {
			$ligne[] = notes_fmt($m->coefficient);
		}
		for ($t = 1; $t <= 3; $t++) {
			$ligne[] = notes_pdf_note($d, $rm['t'][$t]).(!empty($r['exclus'][$t]) && $rm['t'][$t] !== null ? ' *' : '');
		}
		$ligne[] = notes_pdf_note($d, $rm['moyenne']);
		if (!empty($modele->aff_rang_matiere)) {
			$ligne[] = $rm['rang'] !== null ? (string) $rm['rang'] : '-';
		}
		if (!empty($modele->aff_moy_classe)) {
			$ligne[] = notes_pdf_note($d, $calc['stats']['matieres'][$mid]['moy']);
		}
		$rows[] = $ligne;
	}
	$tot = array($d->t('MoyenneGenerale'));
	if ($avecCoef) {
		$tot[] = '';
	}
	for ($t = 1; $t <= 3; $t++) {
		$tot[] = notes_pdf_note($d, $r['trimestres'][$t]).(!empty($r['exclus'][$t]) ? ' *' : '');
	}
	$tot[] = notes_pdf_note($d, $r['moyenne']);
	while (count($tot) < count($headers)) {
		$tot[] = '';
	}
	$rows[] = array('cells' => $tot, 'bold' => true);
	notes_pdf_table($d, $headers, $ratios, $aligns, $rows);
	if (in_array(true, $r['exclus'], true)) {
		ecole_pdf_font($pdf, '', 7.5, $d->rtl);
		$pdf->SetTextColor(100, 100, 110);
		$m = $pdf->getMargins();
		$pdf->MultiCell($pdf->getPageWidth() - $m['left'] - $m['right'], 4, '* '.$d->t('TrimestreNonCompte'), 0, $d->rtl ? 'R' : 'L', false, 1, $m['left']);
	}
	$pdf->Ln(3);

	$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
	$mention = notes_mention($db, $r['moyenne']);
	$paires = array(array($d->t('MoyenneAnnuelle'), notes_moy($r['moyenne']).' / 20', true));
	if (!empty($modele->aff_rang)) {
		$paires[] = array($d->t('RangAnnuel'), notes_pdf_rang($d, $r['rang'], $r['exaequo'], $calc['nb_classes']), true);
	}
	$paires[] = array($d->t('Mention'), $mention ? $d->lib($mention) : '-', true);
	if (!empty($modele->aff_distinction)) {
		$dist = notes_distinction($db, $c, $r['moyenne']);
		$paires[] = array($d->t('Distinction'), $dist ? $d->lib($dist) : '-', (bool) $dist);
	}
	$decision = notes_decision($regle, $c, $r['moyenne']);
	if ($regle['decision'] !== 'aucune') {
		$sexe = '';
		$resql = $db->query("SELECT sexe FROM ".$db->prefix()."ecole_eleve WHERE rowid = ".((int) $eid));
		if ($resql && ($o = $db->fetch_object($resql))) {
			$sexe = (string) $o->sexe;
		}
		$lib = $decision !== '' ? $d->t(notes_decisions()[$decision].($sexe === 'F' ? 'F' : '')) : '-';
		$paires[] = array($d->t('DecisionFinAnneeCourt'), $lib, true);
	}
	if (!empty($modele->aff_moy_classe)) {
		$paires[] = array($d->t('MoyenneClasse'), notes_moy($calc['stats']['generale']['moy']));
	}
	notes_pdf_infos($d, $paires, 9.5);
	notes_pdf_observation($d, $c ? (string) $c->observation : '');
	notes_pdf_signatures($d, !empty($modele->aff_signature_parent));
}

/**
 * Bulletins (trimestre 1 à 3) ou relevés annuels (période 0) d'une classe ou d'un élève, envoyés au navigateur.
 *
 * @param  DoliDB $db        Handler base
 * @param  object $classe    Classe (rowid, ref, label_fr, label_ar)
 * @param  int    $periode   0 à 3
 * @param  int    $fk_eleve  Un seul élève (0 = toute la classe)
 * @param  int    $fk_modele Modèle (0 = celui du niveau)
 * @return string            '' si envoyé, sinon message d'erreur
 */
function notes_pdf_bulletins($db, $classe, $periode, $fk_eleve = 0, $fk_modele = 0)
{
	global $langs;
	$modele = notes_modele_pour($db, (int) $classe->rowid, $fk_modele);
	if (!$modele) {
		return $langs->trans('ErrorAucunModele');
	}
	$calc = notes_calcul($db, (int) $classe->rowid, $periode);
	$ids = array_keys($calc['eleves']);
	if ($fk_eleve > 0) {
		if (!isset($calc['eleves'][$fk_eleve])) {
			return $langs->trans('ErrorEleveHorsClasse');
		}
		$ids = array($fk_eleve);
	}
	if (empty($ids)) {
		return $langs->trans('AucunEleveClasse');
	}
	$conseil = notes_conseil($db, (int) $classe->rowid, $periode);
	$d = notes_pdf_doc($modele->langue, $modele->style, $modele->couleur, $modele->entete);
	foreach ($ids as $eid) {
		if ((int) $periode === 0) {
			notes_pdf_page_releve($db, $d, $modele, $classe, $calc, $eid, $conseil);
		} else {
			notes_pdf_page_bulletin($db, $d, $modele, $classe, $calc, $eid, $conseil);
		}
	}
	$nom = ((int) $periode === 0 ? 'releve_' : 'bulletin_T'.((int) $periode).'_').$classe->ref.($fk_eleve > 0 ? '_'.$calc['eleves'][$fk_eleve]->ref : '');
	$d->pdf->Output(ecole_export_filename($nom, 'pdf'), 'I');
	return '';
}

/**
 * Certificat de scolarité d'un élève (langue de l'utilisateur, ou ?lang=fr / ?lang=ar).
 *
 * @param  DoliDB     $db    Handler base
 * @param  EcoleEleve $eleve Élève
 * @return void
 */
function notes_pdf_certificat($db, $eleve)
{
	global $langs, $mysoc;
	$lang = ecole_pdf_resolve_lang($langs);
	$d = notes_pdf_doc($lang, 'classique');
	$pdf = $d->pdf;
	$rtl = $d->rtl;
	$l = $d->main();
	$pdf->docTitle = $d->t('CertificatScolarite');
	$pdf->docSubtitle = $d->t('AnneeScolaire').' '.eleves_annee_label(eleves_annee_scolaire());
	$pdf->docRef = $eleve->ref;
	$pdf->AddPage();
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$pdf->Ln(6);
	ecole_pdf_font($pdf, 'B', 18, $rtl);
	$pdf->SetTextColor(25, 71, 130);
	$pdf->Cell($w, 10, $d->t('CertificatScolarite'), 0, 1, 'C');
	$pdf->Ln(8);

	$classe = '';
	$resql = $db->query("SELECT ref, label_fr, label_ar FROM ".$db->prefix()."ecole_classe WHERE rowid = ".((int) $eleve->fk_classe));
	if ($resql && ($o = $db->fetch_object($resql))) {
		$classe = $o->ref.' - '.$d->lib($o);
	}
	$nom = $d->lib($eleve);
	$ne = $eleve->date_naissance ? dol_print_date($eleve->date_naissance, 'day', 'tzuser', $l) : '';
	$lieu = (string) $eleve->lieu_naissance;
	$ecole = ecole_pdf_text($pdf->company['name']);
	$annee = eleves_annee_label(eleves_annee_scolaire());
	$f = ($eleve->sexe === 'F') ? 'F' : '';
	$ltr = function ($s) use ($rtl) {
		return eleves_pdf_ltr_notes((string) $s, $rtl);
	};

	ecole_pdf_font($pdf, '', 12, $rtl);
	$pdf->SetTextColor(30, 34, 42);
	// 7 valeurs : plus que les 5 paramètres acceptés par les traductions Dolibarr, d'où vsprintf sur le texte traduit
	$modeleTexte = isset($l->tab_translate['CertificatTexte'.$f]) ? $l->tab_translate['CertificatTexte'.$f] : 'CertificatTexte';
	$texte = ecole_pdf_text(vsprintf($modeleTexte, array($ltr($ecole), $ltr($nom), $ltr($eleve->ref), $ltr($ne !== '' ? $ne : '-'), $ltr($lieu !== '' ? $lieu : '-'), $ltr($classe), $ltr($annee))));
	$pdf->MultiCell($w, 8, $texte, 0, $rtl ? 'R' : 'L', false, 1, $m['left'], '', true, 0, false, true, 0, 'T', false);
	$pdf->Ln(6);
	$pdf->MultiCell($w, 8, ecole_pdf_trans($l, 'CertificatFin'), 0, $rtl ? 'R' : 'L', false, 1, $m['left']);
	$pdf->Ln(14);
	$ville = is_object($mysoc) ? ecole_pdf_text($mysoc->town) : '';
	ecole_pdf_font($pdf, '', 11, $rtl);
	$pdf->Cell($w, 6, ecole_pdf_trans($l, 'FaitALe', $ville, dol_print_date(dol_now(), 'day', 'tzuser', $l)), 0, 1, $rtl ? 'L' : 'R');
	ecole_pdf_font($pdf, 'B', 11, $rtl);
	$pdf->Cell($w, 6, ecole_pdf_trans($l, 'LeDirecteur'), 0, 1, $rtl ? 'L' : 'R');
	$pdf->Output(ecole_export_filename('certificat_'.$eleve->ref, 'pdf'), 'I');
}

/**
 * Dans un texte arabe, garde dans l'ordre les mots écrits en lettres latines (marqueurs « gauche à droite »).
 *
 * @param  string $s   Texte
 * @param  bool   $rtl Document en arabe
 * @return string
 */
function eleves_pdf_ltr_notes($s, $rtl)
{
	if (!$rtl || $s === '' || preg_match('/\p{Arabic}/u', $s)) {
		return $s;
	}
	return "\u{202A}".$s."\u{202C}";
}

/**
 * Données d'exemple pour l'aperçu d'un modèle : un élève fictif, 8 matières (françaises et arabes),
 * résultats du 3e trimestre ou de l'année. Rien n'est lu ni écrit dans la base (sauf mentions et distinctions).
 *
 * @param  EcoleBulletinModele $modele  Modèle
 * @param  int                 $periode 3 (bulletin) ou 0 (relevé annuel)
 * @return array               Même forme que notes_calcul_trimestre / notes_calcul_annuel
 */
function notes_apercu_calc($modele, $periode)
{
	// [français, arabe, langue, coefficient, devoirs, composition, moyenne de la classe]
	$exemple = array(
		array('Mathématiques', 'الرياضيات', 'fr', 4, 14.5, 13, 11.2),
		array('Physique-Chimie', 'الفيزياء والكيمياء', 'fr', 3, 12, 11.5, 10.4),
		array('Sciences naturelles', 'العلوم الطبيعية', 'fr', 3, 15, 14, 12.1),
		array('Langue française', 'اللغة الفرنسية', 'fr', 3, 13, 12.5, 11.8),
		array('Langue arabe', 'اللغة العربية', 'ar', 4, 16, 15.5, 12.9),
		array('Instruction islamique', 'التربية الإسلامية', 'ar', 2, 17, 16, 14.2),
		array('Histoire-Géographie', 'التاريخ والجغرافيا', 'ar', 2, 11, 9.5, 10.7),
		array('Langue anglaise', 'اللغة الإنجليزية', 'fr', 1, 8, 7.5, 10.9),
	);
	$matieres = array();
	$res = array('matieres' => array());
	$stats = array('matieres' => array());
	$points = 0.0;
	$coefs = 0.0;
	foreach ($exemple as $i => $x) {
		$mid = $i + 1;
		$matieres[$mid] = (object) array('fk_matiere' => $mid, 'label_fr' => $x[0], 'label_ar' => $x[1], 'langue' => $x[2], 'coefficient' => $x[3],
			'note_max' => !empty($modele->simplifie) ? $x[3] * 20 : 20, 'enseignants' => array());
		$moy = ($x[4] + 2 * $x[5]) / 3;
		$points += $moy * $x[3];
		$coefs += $x[3];
		$ligne = array('devoirs' => $x[4], 'compo' => $x[5], 'compo_code' => '', 'moyenne' => $moy, 'rang' => ($i % 5) + 2, 'exaequo' => false);
		if ((int) $periode === 0) {
			$ligne['t'] = array(1 => $moy - 0.9, 2 => $moy - 0.2, 3 => $moy);
			$ligne['moyenne'] = ($ligne['t'][1] + $ligne['t'][2] + $ligne['t'][3]) / 3;
		}
		$res['matieres'][$mid] = $ligne;
		$stats['matieres'][$mid] = array('nb' => 28, 'moy' => $x[6], 'min' => max(0, $x[6] - 6.4), 'max' => min(20, $x[6] + 5.9));
	}
	$moyenne = $points / $coefs;
	$res['moyenne'] = (int) $periode === 0 ? $moyenne - 0.37 : $moyenne;
	$res['rang'] = 3;
	$res['exaequo'] = false;
	if ((int) $periode === 0) {
		$res['trimestres'] = array(1 => $moyenne - 0.9, 2 => $moyenne - 0.2, 3 => $moyenne);
		$res['exclus'] = array(1 => false, 2 => false, 3 => false);
	}
	$stats['generale'] = array('nb' => 28, 'moy' => 11.42, 'min' => 5.18, 'max' => 16.87);
	$regle = notes_regle_defaut();
	$regle['decision'] = 'auto';
	return array(
		'regle' => $regle,
		'matieres' => $matieres,
		'eleves' => array(0 => (object) array('rowid' => 0, 'ref' => 'E00000', 'nom_fr' => 'Aïcha Mohamed Salem', 'nom_ar' => 'عائشة محمد سالم', 'numero_appel' => 3, 'naissance' => '12/03/2012 - Nouakchott')),
		'res' => array(0 => $res),
		'stats' => $stats,
		'nb_classes' => 28,
		'trimestre' => (int) $periode,
		'rappel' => array(1 => array('moyenne' => $moyenne - 0.9, 'rang' => 5, 'exaequo' => false), 2 => array('moyenne' => $moyenne - 0.2, 'rang' => 4, 'exaequo' => true)),
	);
}

/**
 * Aperçu PDF d'un modèle de bulletin avec des données d'exemple (bulletin du 3e trimestre ou relevé annuel).
 *
 * @param  DoliDB              $db     Handler base
 * @param  EcoleBulletinModele $modele Modèle
 * @param  string              $type   'bulletin' | 'releve'
 * @return void                        PDF envoyé au navigateur
 */
function notes_pdf_apercu($db, $modele, $type)
{
	$periode = ($type === 'releve') ? 0 : 3;
	$calc = notes_apercu_calc($modele, $periode);
	$d = notes_pdf_doc($modele->langue, $modele->style, $modele->couleur, $modele->entete);
	$obs = array('fr' => 'Bon trimestre, travail régulier. Continuez vos efforts en anglais.', 'ar' => 'فصل جيد وعمل منتظم. واصلي مجهودك في اللغة الإنجليزية.');
	$conseil = array(0 => (object) array('observation' => $d->mode === 'ar' ? $obs['ar'] : $obs['fr'], 'distinction_forcee' => 0, 'fk_distinction' => null, 'decision' => ''));
	$classe = (object) array('rowid' => 0, 'ref' => '4AS', 'label_fr' => '4ème année secondaire', 'label_ar' => 'السنة الرابعة إعدادية');
	if ($periode === 0) {
		notes_pdf_page_releve($db, $d, $modele, $classe, $calc, 0, $conseil);
	} else {
		notes_pdf_page_bulletin($db, $d, $modele, $classe, $calc, 0, $conseil);
	}
	// Filigrane « exemple »
	$pdf = $d->pdf;
	$pdf->SetTextColor(200, 40, 40);
	ecole_pdf_font($pdf, 'B', 46, false);
	$pdf->StartTransform();
	$cx = $pdf->getPageWidth() / 2;
	$cy = $pdf->getPageHeight() / 2;
	$pdf->Rotate(35, $cx, $cy);
	$pdf->SetAlpha(0.12);
	$pdf->Text($cx - 55, $cy - 12, ecole_pdf_trans($d->fr, 'Exemple').' / '.ecole_pdf_trans($d->ar, 'Exemple'));
	$pdf->SetAlpha(1);
	$pdf->StopTransform();
	$pdf->Output(ecole_export_filename('apercu_'.$modele->ref.'_'.$type, 'pdf'), 'I');
}
