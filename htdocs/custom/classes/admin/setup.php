<?php
/**
 * Réglages du module Classes : jours ouvrables de l'école et style d'en-tête des PDF.
 * Cible de $this->config_page_url ("setup.php@classes").
 *
 * Fichier : custom/classes/admin/setup.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');

$langs->loadLangs(array('admin', 'classes@classes'));

if (!$user->hasRight('classes', 'config') && !$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$registry = ecole_pdf_header_registry();

/*
 * Actions
 */
// Images des documents (signature et cachet de la direction...)
$images = ecole_pdf_images_config();
if ($action == 'delimage' && isset($images[GETPOST('const', 'aZ09')])) {
	ecole_pdf_image_delete($db, GETPOST('const', 'aZ09'));
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action == 'save') {
	foreach ($images as $const => $def) {
		$err = ecole_pdf_image_upload($db, 'img_'.strtolower($const), $const);
		if ($err !== '') {
			setEventMessages($langs->trans($def[0]).' : '.$err, null, 'errors');
		}
	}
}
if ($action == 'save') {
	$jours = array();
	foreach (array_keys(ecole_jours()) as $j) {
		if (GETPOST('jour_'.$j, 'alpha')) {
			$jours[] = $j;
		}
	}
	$style = GETPOST('pdf_header_style', 'aZ09');
	$effmax = GETPOSTINT('effectif_max_defaut');
	if (empty($jours)) {
		setEventMessages($langs->trans('ErrorEcoleAucunJour'), null, 'errors');
	} elseif ($effmax < 1) {
		setEventMessages($langs->trans('ErrorEcoleBadValue', $langs->transnoentities('EffectifMaxDefaut')), null, 'errors');
	} else {
		dolibarr_set_const($db, 'ECOLE_EFFECTIF_MAX_DEFAUT', $effmax, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ECOLE_JOURS_OUVRABLES', implode(',', $jours), 'chaine', 0, '', $conf->entity);
		if (isset($registry[$style])) {
			dolibarr_set_const($db, 'ECOLE_PDF_HEADER_STYLE', $style, 'chaine', 0, '', $conf->entity);
		}
		// Police des PDF : une pour les documents en français, une pour les documents en arabe
		$fonts = ecole_pdf_fonts();
		foreach (array('ECOLE_PDF_FONT_FR' => 'pdf_font_fr', 'ECOLE_PDF_FONT_AR' => 'pdf_font_ar') as $const => $field) {
			$font = GETPOST($field, 'aZ09');
			if (isset($fonts[$font])) {
				dolibarr_set_const($db, $const, $font, 'chaine', 0, '', $conf->entity);
			}
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MenuReglages'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('MenuReglages'), $linkback, 'title_setup');

$actifs = ecole_jours_ouvrables();
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

// Effectif maximum des classes
dol_include_once('/classes/class/ecole_classe.class.php');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('EffectifMaxClasses').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('EffectifMaxDefaut').' <span class="opacitymedium small">— '.$langs->trans('EffectifMaxDefautAide').'</span></td>';
print '<td><input type="number" min="1" class="flat maxwidth75" name="effectif_max_defaut" value="'.((int) EcoleClasse::effectifMaxDefaut()).'"></td></tr>';
print '</table><br>';

// Jours ouvrables
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('JoursOuvrables').' <span class="opacitymedium small">— '.$langs->trans('JoursOuvrablesAide').'</span></td></tr>';
foreach (ecole_jours() as $j => $key) {
	print '<tr class="oddeven"><td><label><input type="checkbox" name="jour_'.$j.'" value="1"'.(in_array($j, $actifs) ? ' checked' : '').'> '.$langs->trans($key).'</label></td></tr>';
}
print '</table><br>';

// En-tête des PDF : un choix par style, avec aperçu en français et en arabe
$current = ecole_pdf_header_style();
$preview = dol_buildpath('/classes/admin/header_preview.php', 1);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('PdfHeaderStyle').' <span class="opacitymedium small">— '.$langs->trans('PdfHeaderStyleAide').'</span></td></tr>';
foreach ($registry as $key => $def) {
	print '<tr class="oddeven">';
	print '<td class="nowraponall"><label><input type="radio" name="pdf_header_style" value="'.$key.'"'.($key === $current ? ' checked' : '').'> <b>'.$langs->trans($def['label']).'</b></label></td>';
	print '<td class="opacitymedium">'.$langs->trans($def['desc']).'</td>';
	print '<td class="right nowraponall">';
	print '<a href="'.$preview.'?pdf_header='.$key.'&lang=fr" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$langs->trans('ApercuFr').'</a> &nbsp; ';
	print '<a href="'.$preview.'?pdf_header='.$key.'&lang=ar" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$langs->trans('ApercuAr').'</a>';
	print '</td></tr>';
}
print '</table><br>';

// Police des PDF : documents en français / en arabe, avec aperçu
$fontFr = ecole_pdf_font_choice(false);
$fontAr = ecole_pdf_font_choice(true);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="5">'.$langs->trans('PdfFont').' <span class="opacitymedium small">— '.$langs->trans('PdfFontAide').'</span></td></tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('PdfFontNom').'</td><td></td><td class="center">'.$langs->trans('PdfFontDocsFr').'</td><td class="center">'.$langs->trans('PdfFontDocsAr').'</td><td></td></tr>';
foreach (ecole_pdf_fonts() as $key => $def) {
	print '<tr class="oddeven">';
	print '<td class="nowraponall"><b>'.$langs->trans($def['label']).'</b></td>';
	print '<td class="opacitymedium">'.$langs->trans($def['desc']).'</td>';
	print '<td class="center"><input type="radio" name="pdf_font_fr" value="'.$key.'"'.($key === $fontFr ? ' checked' : '').'></td>';
	print '<td class="center"><input type="radio" name="pdf_font_ar" value="'.$key.'"'.($key === $fontAr ? ' checked' : '').'></td>';
	print '<td class="right nowraponall">';
	print '<a href="'.$preview.'?pdf_font='.$key.'&lang=fr" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$langs->trans('ApercuFr').'</a> &nbsp; ';
	print '<a href="'.$preview.'?pdf_font='.$key.'&lang=ar" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$langs->trans('ApercuAr').'</a>';
	print '</td></tr>';
}
print '</table>';

print '<br>';

// Images des documents : signature et cachet de la direction
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('PdfImages').' <span class="opacitymedium small">— '.$langs->trans('PdfImagesAide').'</span></td></tr>';
foreach ($images as $const => $def) {
	$path = ecole_pdf_image_path($const);
	print '<tr class="oddeven"><td class="titlefield"><b>'.$langs->trans($def[0]).'</b><br><span class="opacitymedium small">'.$langs->trans($def[1]).'</span></td>';
	print '<td>';
	if ($path !== '') {
		print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('ecole/'.basename($path)).'" style="max-height:70px;max-width:220px;border:1px solid #ddd;background:#fff" alt=""> ';
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=delimage&const='.$const.'&token='.newToken().'">'.img_delete().'</a>';
	} else {
		print '<span class="opacitymedium">'.$langs->trans('AucuneImage').'</span>';
	}
	print '</td><td class="right"><input type="file" name="img_'.strtolower($const).'" accept="image/png,image/jpeg"></td></tr>';
}
print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

llxFooter();
$db->close();
