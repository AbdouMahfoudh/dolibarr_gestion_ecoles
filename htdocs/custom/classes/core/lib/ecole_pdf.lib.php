<?php
/**
 * PDF du module Classes : langue détectée automatiquement (arabe / français), en-tête choisi
 * dans la configuration (5 styles), pied de page, tableaux et emplois du temps.
 * Inspiré du module Pointage (même principe de langue et d'en-têtes).
 *
 * Fichier : custom/classes/core/lib/ecole_pdf.lib.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once __DIR__.'/pdf_headers/header_bandeau.php';
require_once __DIR__.'/pdf_headers/header_minimal.php';
require_once __DIR__.'/pdf_headers/header_compact.php';
require_once __DIR__.'/pdf_headers/header_classique.php';
require_once __DIR__.'/pdf_headers/header_image.php';

/* ----------------------------------------------------------------------
 * Langue
 * -------------------------------------------------------------------- */

/**
 * Langue du document : arabe si la langue de l'utilisateur (ou de Dolibarr) est l'arabe,
 * sinon français. Surcharge possible par ?lang=ar ou ?lang=fr.
 *
 * @param  Translate $langs Langue de l'interface
 * @return string           'ar' ou 'fr'
 */
function ecole_pdf_resolve_lang($langs)
{
	$forced = GETPOST('lang', 'aZ09');
	if ($forced === 'ar' || $forced === 'fr') {
		return $forced;
	}
	// Export PDF d'une liste : langue imposée par le modèle de liste choisi (ou par défaut)
	if (GETPOST('format', 'aZ09') === 'pdf' && GETPOST('obj', 'aZ09') !== '') {
		global $db;
		dol_include_once('/classes/class/ecole_pdf_modele.class.php');
		if (is_object($db) && class_exists('EcolePdfModele')) {
			$m = EcolePdfModele::charger($db, 'liste', GETPOSTINT('modele'));
			if ($m && in_array($m->langue, array('fr', 'ar'), true)) {
				return $m->langue;
			}
		}
	}
	foreach (array($langs->defaultlang, getDolGlobalString('MAIN_LANG_DEFAULT')) as $code) {
		if ((string) $code !== '') {
			return (strpos(strtolower((string) $code), 'ar') === 0) ? 'ar' : 'fr';
		}
	}
	return 'fr';
}

/**
 * Traductions pour le document, dans la langue choisie. Elles remplacent aussi la langue
 * globale le temps de l'export, pour que libellés, jours et listes suivent la même langue.
 *
 * @param  string $lang 'ar' ou 'fr'
 * @return Translate
 */
function ecole_pdf_use_lang($lang)
{
	global $conf, $langs;

	$outputlangs = new Translate('', $conf);
	$outputlangs->setDefaultLang($lang === 'ar' ? 'ar_SA' : 'fr_FR');
	$outputlangs->loadLangs(array('main', 'other', 'users', 'classes@classes'));
	$langs = $outputlangs;
	$GLOBALS['langs'] = $outputlangs;
	return $outputlangs;
}

/**
 * Texte traduit et débarrassé des entités HTML (pour le PDF / Excel).
 *
 * @param  Translate $langs  Traductions
 * @param  string    $key    Clé
 * @param  mixed     ...$args Paramètres
 * @return string
 */
function ecole_pdf_trans($langs, $key, ...$args)
{
	return ecole_pdf_text($langs->transnoentities($key, ...$args));
}

/**
 * Texte brut : sans balises ni entités HTML.
 *
 * @param  mixed $s Texte
 * @return string
 */
function ecole_pdf_text($s)
{
	return trim(dol_html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * Texte prêt pour un PDF en arabe : quand un texte contient de l'arabe mais commence par des lettres
 * latines (ex. « E00007 - الحاجة منت جعفر »), TCPDF le traite comme un paragraphe « gauche à droite » et
 * affiche les mots arabes à l'envers (le nom avant le prénom). On l'encadre alors de marqueurs Unicode
 * « droite à gauche », invisibles, pour que l'ordre des mots reste celui de la saisie.
 * Sans effet hors arabe, ou si le texte n'a pas d'arabe ou commence déjà par de l'arabe.
 *
 * @param  string $s   Texte
 * @param  bool   $rtl Document en arabe
 * @return string
 */
function ecole_pdf_bidi($s, $rtl)
{
	$s = (string) $s;
	if (!$rtl || $s === '' || !preg_match('/\p{Arabic}/u', $s) || !preg_match('/^[^\p{L}]*\p{Latin}/u', $s)) {
		return $s;
	}
	return "\u{202B}".$s."\u{202C}";
}

/**
 * Alignement inversé en arabe (L <-> R).
 *
 * @param  string $align L, R ou C
 * @param  bool   $rtl   Arabe
 * @return string
 */
function ecole_pdf_align($align, $rtl)
{
	if (!$rtl) {
		return $align;
	}
	$map = array('L' => 'R', 'R' => 'L');
	return isset($map[$align]) ? $map[$align] : $align;
}

/**
 * Polices proposées pour les PDF : seulement celles qui contiennent à la fois les lettres françaises
 * (é, è, ç, à...) et arabes (formes liées comprises), car un document français peut contenir des noms
 * arabes et inversement. Times, Helvetica, DejaVu Serif ou FreeSans n'ont pas l'arabe : exclues.
 * 'subst' : symboles absents de la police, remplacés automatiquement (sinon ils seraient invisibles).
 *
 * @return array<string,array{label:string,desc:string,subst:array<string,string>}>
 */
function ecole_pdf_fonts()
{
	$ae = array("\u{2014}" => '-', "\u{2013}" => '-', "\u{2192}" => '-', "\u{20AC}" => 'EUR', "\u{06CC}" => "\u{064A}");
	return array(
		'dejavusans' => array('label' => 'PdfFontDejavuSans', 'desc' => 'PdfFontDejavuSansDesc', 'subst' => array()),
		'dejavusanscondensed' => array('label' => 'PdfFontDejavuSansCondensed', 'desc' => 'PdfFontDejavuSansCondensedDesc', 'subst' => array()),
		'freeserif' => array('label' => 'PdfFontFreeSerif', 'desc' => 'PdfFontFreeSerifDesc', 'subst' => array()),
		'aealarabiya' => array('label' => 'PdfFontAeAlArabiya', 'desc' => 'PdfFontAeAlArabiyaDesc', 'subst' => $ae),
		'aefurat' => array('label' => 'PdfFontAeFurat', 'desc' => 'PdfFontAeFuratDesc', 'subst' => $ae),
	);
}

/**
 * Police choisie pour les documents français ou arabes : ?pdf_font=... (aperçu), sinon réglage
 * ECOLE_PDF_FONT_FR / ECOLE_PDF_FONT_AR, sinon DejaVu Sans.
 *
 * @param  bool   $rtl true = documents en arabe
 * @return string
 */
function ecole_pdf_font_choice($rtl)
{
	$fonts = ecole_pdf_fonts();
	$forced = GETPOST('pdf_font', 'aZ09');
	if ($forced !== '' && isset($fonts[$forced])) {
		return $forced;
	}
	$font = getDolGlobalString($rtl ? 'ECOLE_PDF_FONT_AR' : 'ECOLE_PDF_FONT_FR', 'dejavusans');
	return isset($fonts[$font]) ? $font : 'dejavusans';
}

/**
 * Police du document (celle choisie dans la configuration ; pas d'italique en arabe).
 *
 * @param TCPDF  $pdf   PDF
 * @param string $style '', 'B', 'I'
 * @param float  $size  Taille
 * @param bool   $rtl   Arabe
 * @return void
 */
function ecole_pdf_font($pdf, $style, $size, $rtl = false)
{
	if ($rtl) {
		$style = str_replace('I', '', (string) $style);
	}
	$family = (isset($pdf->fontFamily) && $pdf->fontFamily !== '') ? $pdf->fontFamily : 'dejavusans';
	$pdf->SetFont($family, $style, $size);
}

/* ----------------------------------------------------------------------
 * Établissement et en-têtes
 * -------------------------------------------------------------------- */

/**
 * Informations de l'établissement (société Dolibarr) pour l'en-tête.
 *
 * @param  bool $rtl Document en arabe (nom arabe de l'établissement s'il est réglé)
 * @return array{name:string,address:string,phone:string,email:string,logo_path:string}
 */
function ecole_pdf_company($rtl = false)
{
	global $mysoc, $conf;

	$ctx = array('name' => '', 'address' => '', 'phone' => '', 'email' => '', 'logo_path' => '');
	if (!is_object($mysoc)) {
		return $ctx;
	}
	$ctx['name'] = ecole_pdf_text(function_exists('ecole_nom_etablissement') ? ecole_nom_etablissement((bool) $rtl) : $mysoc->name); // nom arabe dans un document en arabe
	$parts = array_filter(array(ecole_pdf_text($mysoc->address), trim(ecole_pdf_text($mysoc->zip).' '.ecole_pdf_text($mysoc->town))));
	$ctx['address'] = implode(' — ', $parts);
	$ctx['phone'] = ecole_pdf_text($mysoc->phone);
	$ctx['email'] = ecole_pdf_text($mysoc->email);
	if (!empty($mysoc->logo)) {
		$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		if (is_readable($logo)) {
			$ctx['logo_path'] = $logo;
		}
	}
	return $ctx;
}

/**
 * Dossier des images des documents de l'école (signature, cachet, en-tête image, filigrane).
 *
 * @return string
 */
function ecole_pdf_image_dir()
{
	global $conf;
	return $conf->mycompany->dir_output.'/ecole';
}

/**
 * Images réglables dans la configuration : constante => [clé du libellé, clé de l'aide].
 *
 * @return array<string,array{0:string,1:string}>
 */
function ecole_pdf_images_config()
{
	return array(
		'ECOLE_PDF_SIGNATURE' => array('PdfImageSignature', 'PdfImageSignatureAide'),
		'ECOLE_PDF_CACHET' => array('PdfImageCachet', 'PdfImageCachetAide'),
		'ECOLE_PDF_ENTETE_IMAGE' => array('PdfImageEntete', 'PdfImageEnteteAide'),
		'ECOLE_PDF_FILIGRANE_IMAGE' => array('PdfImageFiligrane', 'PdfImageFiligraneAide'),
	);
}

/**
 * Chemin d'une image réglée dans la configuration (constante = nom du fichier), '' si absente.
 *
 * @param  string $const Constante (ECOLE_PDF_SIGNATURE, ECOLE_PDF_CACHET...)
 * @return string
 */
function ecole_pdf_image_path($const)
{
	$file = getDolGlobalString($const);
	if ($file === '') {
		return '';
	}
	$path = ecole_pdf_image_dir().'/'.basename($file);
	return is_readable($path) ? $path : '';
}

/**
 * Enregistre une image envoyée par formulaire dans le dossier des images de l'école
 * et la règle dans la constante ; l'ancienne image est supprimée.
 *
 * @param  DoliDB $db    Handler base
 * @param  string $input Nom du champ <input type="file">
 * @param  string $const Constante
 * @return string        '' si OK ou aucun fichier, sinon message d'erreur
 */
function ecole_pdf_image_upload($db, $input, $const)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
	if (empty($_FILES[$input]) || empty($_FILES[$input]['name']) || (int) $_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ((int) $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
		return $langs->trans('ErrorFileNotUploaded');
	}
	$name = dol_sanitizeFileName($_FILES[$input]['name']);
	if (!image_format_supported($name) || !preg_match('/\.(png|jpe?g)$/i', $name)) {
		return $langs->trans('ErrorEcoleImagePngJpg');
	}
	$dir = ecole_pdf_image_dir();
	if (dol_mkdir($dir) < 0) {
		return $langs->trans('ErrorCanNotCreateDir', $dir);
	}
	$name = strtolower($const).'_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
	$res = dol_move_uploaded_file($_FILES[$input]['tmp_name'], $dir.'/'.$name, 1, 0, $_FILES[$input]['error']);
	if (!is_numeric($res) || $res <= 0) {
		return $langs->trans(is_string($res) ? $res : 'ErrorFileNotUploaded');
	}
	ecole_pdf_image_delete($db, $const);
	dolibarr_set_const($db, $const, $name, 'chaine', 0, '', $conf->entity);
	return '';
}

/**
 * Supprime l'image réglée dans une constante.
 *
 * @param  DoliDB $db    Handler base
 * @param  string $const Constante
 * @return void
 */
function ecole_pdf_image_delete($db, $const)
{
	global $conf;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	$old = ecole_pdf_image_path($const);
	if ($old !== '') {
		@unlink($old);
	}
	dolibarr_del_const($db, $const, $conf->entity);
}

/**
 * Signature et cachet de la direction (images de la configuration), côte à côte sous la position courante,
 * alignés à droite (à gauche en arabe). Ne dessine rien si aucune image n'est réglée.
 *
 * @param  EcolePDF $pdf     PDF
 * @param  float    $hauteur Hauteur maximale des images (mm)
 * @return void
 */
function ecole_pdf_signature_cachet($pdf, $hauteur = 28.0)
{
	$images = array_filter(array(ecole_pdf_image_path('ECOLE_PDF_SIGNATURE'), ecole_pdf_image_path('ECOLE_PDF_CACHET')));
	if (empty($images)) {
		return;
	}
	$m = $pdf->getMargins();
	$y = $pdf->GetY() + 1;
	if ($y + $hauteur > $pdf->getPageHeight() - 18) {
		$pdf->AddPage();
		$y = $pdf->GetY();
	}
	$largeurs = array();
	foreach ($images as $i => $img) {
		$size = @getimagesize($img);
		$largeurs[$i] = ($size && $size[1] > 0) ? min(55.0, $hauteur * $size[0] / $size[1]) : $hauteur;
	}
	$total = array_sum($largeurs) + 4 * (count($largeurs) - 1);
	$x = $pdf->isRtl ? $m['left'] : $pdf->getPageWidth() - $m['right'] - $total;
	foreach ($images as $i => $img) {
		$pdf->Image($img, $x, $y, $largeurs[$i], 0, '', '', '', true, 300);
		$x += $largeurs[$i] + 4;
	}
	$pdf->SetY($y + $hauteur + 2);
}

/**
 * Couleurs proposées aux modèles de PDF : clé => RVB.
 *
 * @return array<string,int[]>
 */
function ecole_pdf_couleurs()
{
	return array(
		'bleu' => array(66, 92, 199), 'vert' => array(38, 132, 84), 'bordeaux' => array(140, 32, 52), 'violet' => array(110, 64, 170),
		'orange' => array(214, 110, 20), 'gris' => array(96, 102, 114), 'noir' => array(20, 20, 24), 'rouge' => array(200, 30, 30),
	);
}

/**
 * Réglage du filigrane : celui d'un modèle (filigrane « texte » ou « image »), sinon celui de la configuration
 * (constantes ECOLE_PDF_FILIGRANE_*). null en retour de config = aucun filigrane.
 *
 * @param  object|null $modele Modèle de PDF (EcolePdfModele) ou null
 * @return array|null          [type, texte, image, angle, opacité 0-1, taille, couleur RVB]
 */
function ecole_pdf_filigrane_config($modele)
{
	$couleurs = ecole_pdf_couleurs();
	if ($modele && in_array($modele->filigrane, array('aucun', 'texte', 'image'), true)) {
		if ($modele->filigrane === 'aucun') {
			return array('type' => 'aucun');
		}
		$src = array('type' => $modele->filigrane, 'texte' => (string) $modele->fil_texte, 'angle' => (int) $modele->fil_angle,
			'opacite' => (int) $modele->fil_opacite, 'taille' => (int) $modele->fil_taille, 'couleur' => (string) $modele->fil_couleur);
	} else {
		$src = array('type' => getDolGlobalString('ECOLE_PDF_FILIGRANE_TYPE', 'aucun'), 'texte' => getDolGlobalString('ECOLE_PDF_FILIGRANE_TEXTE'),
			'angle' => getDolGlobalInt('ECOLE_PDF_FILIGRANE_ANGLE', 35), 'opacite' => getDolGlobalInt('ECOLE_PDF_FILIGRANE_OPACITE', 10),
			'taille' => getDolGlobalInt('ECOLE_PDF_FILIGRANE_TAILLE', 50), 'couleur' => getDolGlobalString('ECOLE_PDF_FILIGRANE_COULEUR', 'gris'));
	}
	$src['image'] = ecole_pdf_image_path('ECOLE_PDF_FILIGRANE_IMAGE');
	$src['rgb'] = isset($couleurs[$src['couleur']]) ? $couleurs[$src['couleur']] : $couleurs['gris'];
	$src['opacite'] = min(1, max(0.01, $src['opacite'] / 100));
	if (($src['type'] === 'texte' && trim($src['texte']) === '') || ($src['type'] === 'image' && $src['image'] === '') || !in_array($src['type'], array('texte', 'image'), true)) {
		return array('type' => 'aucun');
	}
	return $src;
}

/**
 * Dessine le filigrane (mot ou image) au centre de la page : inclinaison, transparence, taille, couleur.
 *
 * @param  EcolePDF   $pdf PDF
 * @param  array|null $f   Réglage (ecole_pdf_filigrane_config)
 * @return void
 */
function ecole_pdf_filigrane($pdf, $f)
{
	if (empty($f) || $f['type'] === 'aucun') {
		return;
	}
	$cx = $pdf->getPageWidth() / 2;
	$cy = $pdf->getPageHeight() / 2;
	$x0 = $pdf->GetX();
	$y0 = $pdf->GetY();
	$bm = $pdf->getBreakMargin();
	$abp = $pdf->getAutoPageBreak();
	$pdf->SetAutoPageBreak(false, 0);
	$pdf->StartTransform();
	$pdf->Rotate((float) $f['angle'], $cx, $cy);
	$pdf->SetAlpha($f['opacite']);
	if ($f['type'] === 'image') {
		// Taille : pourcentage de la largeur de la page
		$w = $pdf->getPageWidth() * min(150, max(10, (int) $f['taille'])) / 100;
		$size = @getimagesize($f['image']);
		$h = ($size && $size[0] > 0) ? $w * $size[1] / $size[0] : $w;
		$pdf->Image($f['image'], $cx - $w / 2, $cy - $h / 2, $w, $h, '', '', '', true, 150);
	} else {
		// Taille : corps du texte en points
		$pdf->SetTextColor($f['rgb'][0], $f['rgb'][1], $f['rgb'][2]);
		ecole_pdf_font($pdf, 'B', (float) $f['taille'], (bool) preg_match('/\p{Arabic}/u', $f['texte']));
		$texte = ecole_pdf_bidi(ecole_pdf_text($f['texte']), (bool) preg_match('/\p{Arabic}/u', $f['texte']));
		$tw = $pdf->GetStringWidth($texte);
		$pdf->Text($cx - $tw / 2, $cy - $f['taille'] * 0.35 / 2, $texte);
	}
	$pdf->SetAlpha(1);
	$pdf->StopTransform();
	$pdf->SetAutoPageBreak($abp, $bm);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->SetXY($x0, $y0);
}

/**
 * Applique un modèle de PDF : en-tête, couleur et taille des tableaux, filigrane. Sans modèle : réglages de la configuration.
 *
 * @param  EcolePDF    $pdf    PDF
 * @param  object|null $modele Modèle (EcolePdfModele)
 * @return void
 */
function ecole_pdf_modele_appliquer($pdf, $modele)
{
	$pdf->filigrane = ecole_pdf_filigrane_config($modele);
	if (!$modele) {
		return;
	}
	$registry = ecole_pdf_header_registry();
	if (!empty($modele->entete) && isset($registry[$modele->entete])) {
		$pdf->headerStyle = $modele->entete;
	}
	$pdf->modele = $modele;
	$couleurs = ecole_pdf_couleurs();
	if ($modele->couleur === 'aucune') {
		$pdf->tableColor = array(20, 20, 24); // noir et blanc (photocopie)
	} elseif (isset($couleurs[$modele->couleur])) {
		$pdf->tableColor = $couleurs[$modele->couleur];
	}
	if (in_array($modele->style, array('classique', 'lignes', 'encadre'), true)) {
		$pdf->tableStyle = $modele->style;
	}
	$pdf->tableFontSize = min(12, max(6, (float) $modele->taille_police));
}

/**
 * Option « afficher / masquer » du modèle appliqué (valeur par défaut sans modèle).
 *
 * @param  EcolePDF $pdf    PDF
 * @param  string   $option Champ du modèle (opt_...)
 * @param  bool     $defaut Valeur sans modèle
 * @return bool
 */
function ecole_pdf_option($pdf, $option, $defaut = true)
{
	if (empty($pdf->modele) || !property_exists($pdf->modele, $option) || $pdf->modele->$option === null || $pdf->modele->$option === '') {
		return $defaut;
	}
	return (int) $pdf->modele->$option === 1;
}

/**
 * Nombre entier en toutes lettres, en français ou en arabe (montants des reçus et des bulletins de paie).
 *
 * @param  int    $n    Nombre (0 à 999 999 999)
 * @param  string $lang 'fr' ou 'ar'
 * @return string
 */
function ecole_nombre_lettres($n, $lang = 'fr')
{
	$n = (int) abs($n);
	if ($lang === 'ar') {
		$u = array('', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر');
		$d = array(2 => 'عشرون', 3 => 'ثلاثون', 4 => 'أربعون', 5 => 'خمسون', 6 => 'ستون', 7 => 'سبعون', 8 => 'ثمانون', 9 => 'تسعون');
		$c = array(1 => 'مائة', 2 => 'مائتان', 3 => 'ثلاثمائة', 4 => 'أربعمائة', 5 => 'خمسمائة', 6 => 'ستمائة', 7 => 'سبعمائة', 8 => 'ثمانمائة', 9 => 'تسعمائة');
		$moins1000 = function ($x) use ($u, $d, $c) {
			$p = array();
			if ($x >= 100) {
				$p[] = $c[intdiv($x, 100)];
				$x %= 100;
			}
			if ($x > 0 && $x < 20) {
				$p[] = $u[$x];
			} elseif ($x >= 20) {
				$p[] = ($x % 10 ? $u[$x % 10].' و' : '').$d[intdiv($x, 10)];
			}
			return implode(' و', $p);
		};
		if ($n === 0) {
			return 'صفر';
		}
		$p = array();
		$mil = intdiv($n, 1000000);
		$th = intdiv($n % 1000000, 1000);
		$r = $n % 1000;
		if ($mil) {
			$p[] = $mil === 1 ? 'مليون' : ($mil === 2 ? 'مليونان' : $moins1000($mil).($mil <= 10 ? ' ملايين' : ' مليون'));
		}
		if ($th) {
			$p[] = $th === 1 ? 'ألف' : ($th === 2 ? 'ألفان' : $moins1000($th).($th <= 10 ? ' آلاف' : ' ألف'));
		}
		if ($r) {
			$p[] = $moins1000($r);
		}
		return implode(' و', $p);
	}
	$u = array('zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf');
	$d = array(2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante', 8 => 'quatre-vingt');
	$moins100 = function ($x) use ($u, $d) {
		if ($x < 20) {
			return $u[$x];
		}
		$dz = intdiv($x, 10);
		$un = $x % 10;
		if ($dz === 7 || $dz === 9) {
			return $d[$dz - 1].(($dz === 7 && $un === 1) ? '-et-' : '-').$u[10 + $un];
		}
		if ($un === 0) {
			return $d[$dz].($dz === 8 ? 's' : '');
		}
		return $d[$dz].(($un === 1 && $dz !== 8) ? '-et-' : '-').$u[$un];
	};
	$moins1000 = function ($x, $final = true) use ($u, $moins100) {
		$c = intdiv($x, 100);
		$r = $x % 100;
		$t = '';
		if ($c > 0) {
			$t = ($c > 1 ? $u[$c].' ' : '').'cent'.($c > 1 && $r === 0 && $final ? 's' : '');
		}
		if ($r > 0) {
			$t .= ($t !== '' ? ' ' : '').$moins100($r);
		}
		return $t;
	};
	if ($n === 0) {
		return 'zéro';
	}
	$p = array();
	$mil = intdiv($n, 1000000);
	$th = intdiv($n % 1000000, 1000);
	$r = $n % 1000;
	if ($mil) {
		$p[] = $moins1000($mil).' million'.($mil > 1 ? 's' : '');
	}
	if ($th) {
		$p[] = ($th === 1 ? '' : $moins1000($th, false).' ').'mille';
	}
	if ($r) {
		$p[] = $moins1000($r);
	}
	$t = implode(' ', $p);
	return str_replace('quatre-vingts mille', 'quatre-vingt mille', $t); // « quatre-vingt » invariable devant « mille »
}

/**
 * Montant en toutes lettres avec la devise (« Arrêté à la somme de ... »), partie entière seulement.
 *
 * @param  float    $montant Montant
 * @param  EcolePDF $pdf     PDF (langue du document)
 * @return string
 */
function ecole_montant_lettres($montant, $pdf)
{
	global $conf;
	$lang = $pdf->isRtl ? 'ar' : 'fr';
	$txt = ecole_nombre_lettres((int) floor(abs((float) $montant)), $lang);
	$devise = $conf->currency;
	if ($devise === 'MRU' || $devise === 'MRO') {
		$devise = $lang === 'ar' ? 'أوقية' : 'ouguiyas';
	}
	return ($lang === 'fr' ? ucfirst($txt) : $txt).' '.$devise;
}

/**
 * Teinte claire d'une couleur (fonds des blocs et des en-têtes « encadré »).
 *
 * @param  int[] $rgb   Couleur
 * @param  float $clair Part de blanc (0 à 1)
 * @return int[]
 */
function ecole_pdf_teinte($rgb, $clair = 0.88)
{
	return array((int) round($rgb[0] + (255 - $rgb[0]) * $clair), (int) round($rgb[1] + (255 - $rgb[1]) * $clair), (int) round($rgb[2] + (255 - $rgb[2]) * $clair));
}

/**
 * Titre de section dans la mise en page du modèle : classique (texte coloré), lignes (souligné), encadré (bandeau teinté).
 *
 * @param  EcolePDF $pdf   PDF
 * @param  string   $titre Titre
 * @return void
 */
function ecole_pdf_titre_section($pdf, $titre)
{
	$m = $pdf->getMargins();
	$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$c = $pdf->tableColor;
	$pdf->Ln(2);
	ecole_pdf_font($pdf, 'B', 10, $pdf->isRtl);
	$pdf->SetTextColor($c[0], $c[1], $c[2]);
	$texte = ecole_pdf_bidi(ecole_pdf_text($titre), $pdf->isRtl);
	$align = $pdf->isRtl ? 'R' : 'L';
	if ($pdf->tableStyle === 'encadre') {
		$t = ecole_pdf_teinte($c);
		$pdf->SetFillColor($t[0], $t[1], $t[2]);
		$y = $pdf->GetY();
		$pdf->RoundedRect($m['left'], $y, $w, 7, 1.5, '1111', 'F');
		$pdf->SetXY($m['left'] + 2, $y);
		$pdf->Cell($w - 4, 7, $texte, 0, 1, $align);
		$pdf->Ln(1);
	} elseif ($pdf->tableStyle === 'lignes') {
		$pdf->Cell($w, 6, $texte, 0, 1, $align);
		$pdf->SetDrawColor($c[0], $c[1], $c[2]);
		$pdf->SetLineWidth(0.4);
		$pdf->Line($m['left'], $pdf->GetY(), $m['left'] + $w, $pdf->GetY());
		$pdf->Ln(1.5);
	} else {
		$pdf->Cell($w, 6, $texte, 0, 1, $align);
	}
	$pdf->SetTextColor(40, 40, 50);
}

/**
 * Styles d'en-tête disponibles : clé => fonction de rendu, marge haute (mm), libellé, description.
 * Pour ajouter un style : un fichier dans core/lib/pdf_headers/ + une entrée ici + ses traductions.
 *
 * @return array<string,array{render:string,top:float,label:string,desc:string}>
 */
function ecole_pdf_header_registry()
{
	return array(
		'bandeau_bleu' => array('render' => 'ecole_pdf_header_bandeau_bleu', 'top' => 42.0, 'label' => 'PdfHeaderBandeauBleu', 'desc' => 'PdfHeaderBandeauBleuDesc'),
		'bandeau_sombre' => array('render' => 'ecole_pdf_header_bandeau_sombre', 'top' => 42.0, 'label' => 'PdfHeaderBandeauSombre', 'desc' => 'PdfHeaderBandeauSombreDesc'),
		'minimal' => array('render' => 'ecole_pdf_header_minimal', 'top' => 34.0, 'label' => 'PdfHeaderMinimal', 'desc' => 'PdfHeaderMinimalDesc'),
		'compact' => array('render' => 'ecole_pdf_header_compact', 'top' => 24.0, 'label' => 'PdfHeaderCompact', 'desc' => 'PdfHeaderCompactDesc'),
		'classique' => array('render' => 'ecole_pdf_header_classique', 'top' => 58.0, 'label' => 'PdfHeaderClassique', 'desc' => 'PdfHeaderClassiqueDesc'),
		'image' => array('render' => 'ecole_pdf_header_image', 'top' => 42.0, 'label' => 'PdfHeaderImage', 'desc' => 'PdfHeaderImageDesc'),
	);
}

/**
 * Marge haute de la première page pour un style d'en-tête (en-tête image : selon la hauteur de l'image).
 *
 * @param  EcolePDF $pdf   PDF
 * @param  string   $style Style
 * @return float
 */
function ecole_pdf_header_top($pdf, $style)
{
	$registry = ecole_pdf_header_registry();
	if ($style === 'image') {
		$h = ecole_pdf_header_image_hauteur($pdf->getPageWidth());
		return $h > 0 ? ECOLE_PDF_ENTETE_IMAGE_MARGE + $h + 14.0 : $registry['bandeau_bleu']['top'];
	}
	return isset($registry[$style]) ? $registry[$style]['top'] : $registry['bandeau_bleu']['top'];
}

/**
 * Style d'en-tête choisi : ?pdf_header=... (aperçu), sinon réglage ECOLE_PDF_HEADER_STYLE, sinon bandeau bleu.
 *
 * @return string
 */
function ecole_pdf_header_style()
{
	$registry = ecole_pdf_header_registry();
	$forced = GETPOST('pdf_header', 'aZ09');
	if ($forced !== '' && isset($registry[$forced])) {
		return $forced;
	}
	$style = getDolGlobalString('ECOLE_PDF_HEADER_STYLE', 'bandeau_bleu');
	return isset($registry[$style]) ? $style : 'bandeau_bleu';
}

/**
 * Données communes aux fonctions de rendu d'en-tête.
 *
 * @param  EcolePDF $pdf PDF
 * @return array{0:Translate,1:bool,2:string,3:string,4:string} langue, rtl, titre, sous-titre, réf
 */
function ecole_pdf_header_context($pdf)
{
	return array($pdf->outputlangs, $pdf->isRtl, ecole_pdf_text($pdf->docTitle), ecole_pdf_text($pdf->docSubtitle), ecole_pdf_text($pdf->docRef));
}

/**
 * Ligne « Réf. : XXX » dans le bon sens.
 *
 * @param  EcolePDF $pdf PDF
 * @param  string   $ref Référence
 * @return string
 */
function ecole_pdf_ref_line($pdf, $ref)
{
	$label = ecole_pdf_trans($pdf->outputlangs, 'Ref');
	return $pdf->isRtl ? ($ref.' : '.$label) : ($label.' : '.$ref);
}

/**
 * PDF du module : en-tête sur la 1re page (style de la configuration), pied de page sur toutes.
 */
class EcolePDF extends TCPDF
{
	/** @var string */
	public $docTitle = '';
	/** @var string */
	public $docSubtitle = '';
	/** @var string */
	public $docRef = '';
	/** @var array */
	public $company = array();
	/** @var Translate */
	public $outputlangs;
	/** @var bool */
	public $isRtl = false;
	/** @var string */
	public $headerStyle = 'bandeau_bleu';
	/** @var string Police du document (ecole_pdf_fonts) */
	public $fontFamily = 'dejavusans';
	/** @var array<string,string> Symboles absents de la police et leur remplacement */
	public $fontSubst = array();
	/** @var int[] Couleur des en-têtes de tableaux (modèle de PDF) */
	public $tableColor = array(66, 92, 199);
	/** @var float Taille du texte des tableaux (modèle de PDF) */
	public $tableFontSize = 8.0;
	/** @var array|null Filigrane de chaque page (ecole_pdf_filigrane_config), null = celui de la configuration */
	public $filigrane = null;
	/** @var string Mise en page des tableaux : classique (cases colorées), lignes (léger), encadre (blocs) */
	public $tableStyle = 'classique';
	/** @var object|null Modèle de PDF appliqué (options afficher / masquer) */
	public $modele = null;

	/**
	 * Filigrane de la page courante (transparent, dessiné par-dessus le contenu depuis le pied de page).
	 *
	 * @return void
	 */
	public function ecoleFiligrane()
	{
		if ($this->filigrane === null) {
			$this->filigrane = ecole_pdf_filigrane_config(null);
		}
		ecole_pdf_filigrane($this, $this->filigrane);
	}

	/**
	 * Texte adapté à la police : symboles absents remplacés (sinon invisibles).
	 *
	 * @param  mixed $txt Texte
	 * @return mixed
	 */
	protected function ecoleSubst($txt)
	{
		return (!empty($this->fontSubst) && is_string($txt)) ? strtr($txt, $this->fontSubst) : $txt;
	}

	/**
	 * Cellule (texte adapté à la police).
	 *
	 * @param float  $w       Largeur
	 * @param float  $h       Hauteur
	 * @param string $txt     Texte
	 * @param mixed  ...$rest Autres paramètres de TCPDF::Cell
	 * @return void
	 */
	public function Cell($w, $h = 0, $txt = '', ...$rest)
	{
		return parent::Cell($w, $h, $this->ecoleSubst($txt), ...$rest);
	}

	/**
	 * Cellule sur plusieurs lignes (texte adapté à la police).
	 *
	 * @param float  $w       Largeur
	 * @param float  $h       Hauteur
	 * @param string $txt     Texte
	 * @param mixed  ...$rest Autres paramètres de TCPDF::MultiCell
	 * @return int
	 */
	public function MultiCell($w, $h, $txt, ...$rest)
	{
		return parent::MultiCell($w, $h, $this->ecoleSubst($txt), ...$rest);
	}

	/**
	 * Hauteur d'un texte (même remplacement que l'affichage, pour des calculs justes).
	 *
	 * @param float  $w       Largeur
	 * @param string $txt     Texte
	 * @param mixed  ...$rest Autres paramètres de TCPDF::getStringHeight
	 * @return float
	 */
	public function getStringHeight($w, $txt, ...$rest)
	{
		return parent::getStringHeight($w, $this->ecoleSubst($txt), ...$rest);
	}

	/**
	 * Texte positionné (texte adapté à la police).
	 *
	 * @param float  $x       Abscisse
	 * @param float  $y       Ordonnée
	 * @param string $txt     Texte
	 * @param mixed  ...$rest Autres paramètres de TCPDF::Text
	 * @return void
	 */
	public function Text($x, $y, $txt, ...$rest)
	{
		return parent::Text($x, $y, $this->ecoleSubst($txt), ...$rest);
	}

	/**
	 * En-tête : style choisi sur la 1re page, simple marge sur les suivantes.
	 *
	 * @return void
	 */
	public function Header()
	{
		if ($this->getPage() > 1) {
			$this->SetTopMargin(12);
			$this->SetY(12);
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
	 * Pied de page : établissement et date à gauche, numéro de page à droite (inversé en arabe).
	 *
	 * @return void
	 */
	public function Footer()
	{
		$this->ecoleFiligrane(); // par-dessus le contenu (transparent), pour rester visible sur les tableaux
		$m = $this->getMargins();
		$w = $this->getPageWidth() - $m['left'] - $m['right'];
		$this->SetY(-14);
		$this->SetDrawColor(67, 142, 204);
		$this->SetLineWidth(0.3);
		$this->Line($m['left'], $this->GetY(), $m['left'] + $w, $this->GetY());
		$this->SetY(-12);
		ecole_pdf_font($this, '', 7, $this->isRtl);
		$this->SetTextColor(120, 120, 140);
		$left = ecole_pdf_trans($this->outputlangs, 'EcolePdfFooter', dol_print_date(dol_now(), 'dayhour', 'tzuser', $this->outputlangs));
		$right = ecole_pdf_trans($this->outputlangs, 'Page').' '.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
		$this->Cell($w / 2, 4, $this->isRtl ? $right : $left, 0, 0, 'L');
		$this->Cell($w / 2, 4, $this->isRtl ? $left : $right, 0, 0, 'R');
	}
}

/**
 * Crée un PDF prêt à l'emploi dans la langue détectée.
 *
 * @param  Translate    $langs       Langue de l'interface
 * @param  string       $orientation 'P' (portrait) ou 'L' (paysage)
 * @param  string|array $format      Format de page : 'A4', 'A5'... ou array(largeur, hauteur) en mm
 * @param  string       $langue      Langue imposée ('fr', 'ar') par un modèle de PDF, '' = langue détectée
 * @return array{0:EcolePDF,1:Translate,2:bool,3:string} pdf, traductions, arabe ?, langue
 */
function ecole_pdf_create($langs, $orientation = 'P', $format = 'A4', $langue = '')
{
	// Langue imposée par un modèle de PDF (fr / ar), sinon langue détectée
	$lang = in_array($langue, array('fr', 'ar'), true) ? $langue : ecole_pdf_resolve_lang($langs);
	$outputlangs = ecole_pdf_use_lang($lang);
	$rtl = ($lang === 'ar');

	$pdf = new EcolePDF($orientation, 'mm', $format, true, 'UTF-8', false);
	$pdf->outputlangs = $outputlangs;
	$pdf->isRtl = $rtl;
	$pdf->company = ecole_pdf_company($rtl);
	$pdf->headerStyle = ecole_pdf_header_style();
	$fonts = ecole_pdf_fonts();
	$pdf->fontFamily = ecole_pdf_font_choice($rtl);
	$pdf->fontSubst = $fonts[$pdf->fontFamily]['subst'];
	$pdf->setRTL(false);
	$pdf->SetCreator('Dolibarr - '.$outputlangs->transnoentities('Classes'));
	$pdf->SetAuthor($pdf->company['name']);
	$pdf->setFontSubsetting(true);
	return array($pdf, $outputlangs, $rtl, $lang);
}

/**
 * Démarre le document : titres, marges, première page.
 *
 * @param  EcolePDF $pdf      PDF
 * @param  string   $title    Titre
 * @param  string   $subtitle Sous-titre
 * @param  string   $ref      Référence du document
 * @return void
 */
function ecole_pdf_start($pdf, $title, $subtitle, $ref)
{
	$registry = ecole_pdf_header_registry();
	$pdf->docTitle = $title;
	$pdf->docSubtitle = $subtitle;
	$pdf->docRef = $ref;
	$pdf->SetTitle(ecole_pdf_text($title));
	$pdf->SetSubject(ecole_pdf_text($subtitle));
	$pdf->SetMargins(12, ecole_pdf_header_top($pdf, $pdf->headerStyle), 12);
	$pdf->SetAutoPageBreak(true, 18);
	$pdf->AddPage();
}

/* ----------------------------------------------------------------------
 * Tableaux
 * -------------------------------------------------------------------- */

/**
 * Tableau paginé : en-tête de colonnes répété à chaque page, lignes multi-lignes,
 * colonnes dans l'ordre inverse en arabe.
 *
 * @param EcolePDF $pdf     PDF
 * @param string[] $headers Titres des colonnes
 * @param float[]  $ratios  Largeur relative de chaque colonne
 * @param string[] $aligns  Alignement de chaque colonne (L, C, R)
 * @param array    $rows    Lignes (tableaux de textes)
 * @return void
 */
function ecole_pdf_table($pdf, $headers, $ratios, $aligns, $rows)
{
	$rtl = $pdf->isRtl;
	$m = $pdf->getMargins();
	$pageW = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$sum = array_sum($ratios);
	$widths = array();
	foreach ($ratios as $r) {
		$widths[] = $pageW * $r / $sum;
	}
	if ($rtl) {
		$widths = array_reverse($widths);
		$headers = array_reverse($headers);
		$aligns = array_reverse($aligns);
		foreach ($aligns as $i => $a) {
			$aligns[$i] = ecole_pdf_align($a, true);
		}
	}

	$printHeader = function () use ($pdf, $headers, $widths, $rtl) {
		ecole_pdf_font($pdf, 'B', isset($pdf->tableFontSize) ? $pdf->tableFontSize : 8, $rtl);
		$c = isset($pdf->tableColor) ? $pdf->tableColor : array(66, 92, 199);
		$style = isset($pdf->tableStyle) ? $pdf->tableStyle : 'classique';
		$pdf->SetLineWidth(0.2);
		if ($style === 'lignes') {
			// Léger : titres colorés, filet épais dessous, pas de fond ni de cadres
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetTextColor($c[0], $c[1], $c[2]);
			$pdf->SetDrawColor($c[0], $c[1], $c[2]);
			$pdf->SetLineWidth(0.5);
			$bordure = 'B';
		} elseif ($style === 'encadre') {
			// Encadré : titres sur fond teinté, cadre coloré autour du tableau
			$t = ecole_pdf_teinte($c, 0.82);
			$pdf->SetFillColor($t[0], $t[1], $t[2]);
			$pdf->SetTextColor($c[0], $c[1], $c[2]);
			$pdf->SetDrawColor($c[0], $c[1], $c[2]);
			$bordure = 1;
		} else {
			$pdf->SetFillColor($c[0], $c[1], $c[2]);
			$pdf->SetTextColor(255, 255, 255);
			$pdf->SetDrawColor(180, 185, 200);
			$bordure = 1;
		}
		$h = 8;
		foreach ($headers as $i => $label) {
			$h = max($h, $pdf->getStringHeight($widths[$i], $label) + 1);
		}
		$x = $pdf->GetX();
		$y = $pdf->GetY();
		foreach ($headers as $i => $label) {
			$pdf->MultiCell($widths[$i], $h, $label, $bordure, 'C', true, 0, $x, $y, true, 0, false, true, $h, 'M');
			$x += $widths[$i];
		}
		$pdf->SetLineWidth(0.2);
		$pdf->SetXY($pdf->getMargins()['left'], $y + $h);
	};
	$printHeader();

	$bottom = $pdf->getPageHeight() - 18;
	$alt = false;
	foreach ($rows as $cells) {
		$cells = array_map(function ($c) use ($rtl) {
			return ecole_pdf_bidi((string) $c, $rtl);
		}, array_values($cells));
		if ($rtl) {
			$cells = array_reverse($cells);
		}
		ecole_pdf_font($pdf, '', isset($pdf->tableFontSize) ? $pdf->tableFontSize : 8, $rtl);
		$h = 7;
		foreach ($cells as $i => $c) {
			$h = max($h, $pdf->getStringHeight($widths[$i], $c) + 1);
		}
		// Saut de page avant la ligne (jamais au milieu), puis en-tête de colonnes répété
		if ($pdf->GetY() + $h > $bottom) {
			$pdf->AddPage();
			$printHeader();
			ecole_pdf_font($pdf, '', isset($pdf->tableFontSize) ? $pdf->tableFontSize : 8, $rtl);
		}
		$alt = !$alt;
		$style = isset($pdf->tableStyle) ? $pdf->tableStyle : 'classique';
		$coul = isset($pdf->tableColor) ? $pdf->tableColor : array(66, 92, 199);
		if ($style === 'lignes') {
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetDrawColor(215, 218, 226);
		} elseif ($style === 'encadre') {
			$t = ecole_pdf_teinte($coul, 0.95);
			$pdf->SetFillColor($alt ? $t[0] : 255, $alt ? $t[1] : 255, $alt ? $t[2] : 255);
			$pdf->SetDrawColor($coul[0], $coul[1], $coul[2]);
		} else {
			$pdf->SetFillColor($alt ? 248 : 255, $alt ? 249 : 255, $alt ? 252 : 255);
			$pdf->SetDrawColor(180, 185, 200);
		}
		$pdf->SetTextColor(45, 45, 55);
		$pdf->SetAutoPageBreak(false, 0);
		$x = $pdf->getMargins()['left'];
		$y = $pdf->GetY();
		$last = count($cells) - 1;
		foreach ($cells as $i => $c) {
			// Lignes : filet fin sous chaque ligne ; encadré : cadre extérieur + filets ; classique : toutes les cases
			$bord = ($style === 'lignes') ? 'B' : (($style === 'encadre') ? 'B'.($i === 0 ? 'L' : '').($i === $last ? 'R' : '') : 1);
			if ($style === 'encadre') {
				$pdf->SetDrawColor($i === 0 || $i === $last ? $coul[0] : 220, $i === 0 || $i === $last ? $coul[1] : 222, $i === 0 || $i === $last ? $coul[2] : 230);
			}
			$pdf->MultiCell($widths[$i], $h, $c, $bord, $aligns[$i], true, 0, $x, $y, true, 0, false, true, $h, 'M');
			$x += $widths[$i];
		}
		$pdf->SetAutoPageBreak(true, 18);
		$pdf->SetXY($pdf->getMargins()['left'], $y + $h);
	}
	if (empty($rows)) {
		ecole_pdf_font($pdf, 'I', 9, $rtl);
		$pdf->SetTextColor(110, 110, 120);
		$pdf->Cell($pageW, 10, ecole_pdf_trans($pdf->outputlangs, 'NoRecordFound'), 0, 1, 'C');
	}
}

/* ----------------------------------------------------------------------
 * Emploi du temps
 * -------------------------------------------------------------------- */

/**
 * Couleurs des blocs (mêmes teintes que l'écran) : fond, trait.
 *
 * @param  int $i Indice de couleur
 * @return array{0:int[],1:int[]}
 */
function ecole_pdf_color($i)
{
	$palette = array(
		array(array(227, 240, 253), array(30, 120, 210)),
		array(array(230, 246, 234), array(46, 158, 79)),
		array(array(253, 240, 224), array(224, 137, 28)),
		array(array(243, 232, 251), array(138, 79, 196)),
		array(array(253, 232, 236), array(210, 63, 92)),
		array(array(226, 246, 246), array(27, 154, 154)),
		array(array(251, 246, 217), array(181, 154, 16)),
		array(array(235, 238, 250), array(79, 96, 196)),
		array(array(247, 235, 227), array(160, 96, 58)),
		array(array(238, 245, 224), array(107, 142, 35)),
	);
	return $palette[((int) $i) % 10];
}

/**
 * Dessine un emploi du temps (même structure de données que ecole_timetable_render()) :
 * jours/dates en colonnes, axe horaire, blocs colorés. Occupe la hauteur restante de la page.
 * En arabe, l'axe passe à droite et les colonnes se lisent de droite à gauche.
 *
 * @param EcolePDF $pdf     PDF
 * @param array    $columns clé => array('label', 'sublabel')
 * @param array    $events  liste de array('col', 'start', 'end', 'title', 'lines')
 * @param array    $opts    'bands' => créneaux fixes array('start', 'end', 'label') ; 'freelabel' => texte des cases libres
 * @return void
 */
function ecole_pdf_timetable($pdf, $columns, $events, $opts = array())
{
	$rtl = $pdf->isRtl;
	$bands = isset($opts['bands']) ? $opts['bands'] : array();
	usort($bands, function ($a, $b) {
		return strcmp($a['start'], $b['start']);
	});

	$m = $pdf->getMargins();
	$x0 = $m['left'];
	$pageW = $pdf->getPageWidth() - $m['left'] - $m['right'];
	$axisW = 22.0;
	$headH = 10.0;
	$top = $pdf->GetY() + 2;
	$availH = $pdf->getPageHeight() - 20 - $top - $headH;

	// Amplitude horaire
	$min = null;
	$max = null;
	foreach (array_merge($bands, $events) as $x) {
		$s = ecole_hhmm_to_min($x['start']);
		$e = ecole_hhmm_to_min($x['end']);
		$min = ($min === null) ? $s : min($min, $s);
		$max = ($max === null) ? $e : max($max, $e);
	}
	if ($min === null) {
		$min = 8 * 60;
		$max = 12 * 60;
	}
	if (empty($bands)) {
		$min = (int) floor($min / 60) * 60;
		$max = (int) ceil($max / 60) * 60;
	}

	// Minutes « utiles » (les longues pauses entre créneaux sont raccourcies)
	$gapMin = 12;
	$useful = 0;
	$prevEnd = null;
	foreach ($bands as $b) {
		$bs = ecole_hhmm_to_min($b['start']);
		$be = ecole_hhmm_to_min($b['end']);
		if ($prevEnd !== null && $bs > $prevEnd) {
			$useful += min($bs - $prevEnd, $gapMin);
		}
		$useful += $be - $bs;
		$prevEnd = $be;
	}
	if (empty($bands)) {
		$useful = $max - $min;
	}
	$scale = $availH / max(1, $useful);

	$ypos = function ($t) use ($bands, $min, $scale, $gapMin) {
		if (empty($bands)) {
			return ($t - $min) * $scale;
		}
		$y = 0;
		$prevEnd = null;
		foreach ($bands as $b) {
			$bs = ecole_hhmm_to_min($b['start']);
			$be = ecole_hhmm_to_min($b['end']);
			if ($prevEnd !== null && $bs > $prevEnd) {
				$gap = min($bs - $prevEnd, $gapMin);
				if ($t < $bs) {
					return $y + $gap * ($t - $prevEnd) / ($bs - $prevEnd) * $scale;
				}
				$y += $gap * $scale;
			}
			if ($t <= $be) {
				return $y + max(0, $t - $bs) * $scale;
			}
			$y += ($be - $bs) * $scale;
			$prevEnd = $be;
		}
		return $y;
	};
	$height = $ypos($max);

	$keys = array_keys($columns);
	$nb = max(1, count($keys));
	$colW = ($pageW - $axisW) / $nb;
	$axisX = $rtl ? ($x0 + $pageW - $axisW) : $x0;
	$colX = function ($idx) use ($rtl, $x0, $axisW, $colW, $pageW) {
		return $rtl ? ($x0 + $pageW - $axisW - ($idx + 1) * $colW) : ($x0 + $axisW + $idx * $colW);
	};
	$bodyY = $top + $headH;

	// Cadre + en-têtes
	$pdf->SetDrawColor(200, 200, 200);
	$pdf->SetLineWidth(0.2);
	$pdf->SetFillColor(241, 241, 241);
	$pdf->Rect($x0, $top, $pageW, $headH, 'DF');
	$pdf->SetFillColor(250, 250, 250);
	$pdf->Rect($axisX, $bodyY, $axisW, $height, 'DF');
	$pdf->SetTextColor(40, 40, 40);
	foreach ($keys as $idx => $k) {
		$cx = $colX($idx);
		$pdf->Rect($cx, $bodyY, $colW, $height, 'D');
		ecole_pdf_font($pdf, 'B', 8.5, $rtl);
		$label = ecole_pdf_text($columns[$k]['label']);
		$sub = isset($columns[$k]['sublabel']) ? ecole_pdf_text($columns[$k]['sublabel']) : '';
		$pdf->MultiCell($colW, $headH, $label.($sub !== '' ? "\n".$sub : ''), 'L', 'C', false, 0, $cx, $top, true, 0, false, true, $headH, 'M');
	}

	// Axe horaire et fond des créneaux
	if (!empty($bands)) {
		foreach ($bands as $b) {
			$by = $bodyY + $ypos(ecole_hhmm_to_min($b['start']));
			$bh = $ypos(ecole_hhmm_to_min($b['end'])) - $ypos(ecole_hhmm_to_min($b['start']));
			$pdf->SetDrawColor(215, 215, 215);
			$pdf->Line($x0, $by, $x0 + $pageW, $by);
			ecole_pdf_font($pdf, 'B', 7.5, $rtl);
			$pdf->SetTextColor(60, 60, 60);
			$txt = $b['start'].' - '.$b['end'];
			if (!empty($b['label'])) {
				$txt .= "\n".ecole_pdf_text($b['label']);
			}
			$pdf->MultiCell($axisW, $bh, $txt, 0, 'C', false, 0, $axisX, $by, true, 0, false, true, $bh, 'M');
			if (!empty($opts['freelabel'])) {
				foreach ($keys as $idx => $k) {
					$busy = false;
					foreach ($events as $ev) {
						if ($ev['col'] == $k && ecole_hhmm_to_min($ev['start']) < ecole_hhmm_to_min($b['end']) && ecole_hhmm_to_min($ev['end']) > ecole_hhmm_to_min($b['start'])) {
							$busy = true;
							break;
						}
					}
					if (!$busy) {
						ecole_pdf_font($pdf, '', 7, $rtl);
						$pdf->SetTextColor(46, 158, 79);
						$pdf->MultiCell($colW, $bh, ecole_pdf_text($opts['freelabel']), 0, 'C', false, 0, $colX($idx), $by, true, 0, false, true, $bh, 'M');
					}
				}
			}
		}
	} else {
		for ($t = $min; $t < $max; $t += 60) {
			$hy = $bodyY + $ypos($t);
			$pdf->SetDrawColor(225, 225, 225);
			$pdf->Line($x0, $hy, $x0 + $pageW, $hy);
			ecole_pdf_font($pdf, 'B', 7.5, $rtl);
			$pdf->SetTextColor(60, 60, 60);
			$pdf->MultiCell($axisW, 5, sprintf('%02d:00', $t / 60), 0, 'C', false, 0, $axisX, $hy + 0.5, true);
		}
	}

	// Blocs
	$pos = array_flip($keys);
	foreach ($events as $ev) {
		if (!isset($pos[$ev['col']])) {
			continue;
		}
		$ex = $colX($pos[$ev['col']]) + 0.8;
		$ey = $bodyY + $ypos(ecole_hhmm_to_min($ev['start'])) + 0.5;
		$eh = max(5, $ypos(ecole_hhmm_to_min($ev['end'])) - $ypos(ecole_hhmm_to_min($ev['start'])) - 1);
		$ew = $colW - 1.6;
		list($bg, $line) = ecole_pdf_color(isset($ev['color']) ? $ev['color'] : 0);
		$pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
		$pdf->RoundedRect($ex, $ey, $ew, $eh, 1.2, '1111', 'F');
		$pdf->SetFillColor($line[0], $line[1], $line[2]);
		$pdf->Rect($rtl ? ($ex + $ew - 1.2) : $ex, $ey, 1.2, $eh, 'F');

		$lines = array();
		foreach ($ev['lines'] as $l) {
			if ((string) $l !== '') {
				$lines[] = ecole_pdf_text($l);
			}
		}
		$tx = $rtl ? $ex + 0.5 : $ex + 2;
		$tw = $ew - 2.5;
		$pdf->SetTextColor(35, 35, 35);
		ecole_pdf_font($pdf, 'B', 7.5, $rtl);
		$pdf->MultiCell($tw, 3.6, ecole_pdf_text($ev['title']), 0, ecole_pdf_align('L', $rtl), false, 1, $tx, $ey + 0.8, true, 0, false, true, 3.8, 'T', true);
		ecole_pdf_font($pdf, '', 6.5, $rtl);
		$pdf->SetTextColor(80, 80, 90);
		$rest = $ev['start'].' - '.$ev['end'].(empty($lines) ? '' : "\n".implode("\n", $lines));
		$pdf->MultiCell($tw, 3.1, $rest, 0, ecole_pdf_align('L', $rtl), false, 1, $tx, $ey + 4.6, true, 0, false, true, max(3, $eh - 5), 'T', true);
	}

	$pdf->SetTextColor(0, 0, 0);
	$pdf->SetY($bodyY + $height + 2);
}
