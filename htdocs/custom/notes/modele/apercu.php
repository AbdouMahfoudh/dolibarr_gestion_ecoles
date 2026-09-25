<?php
/**
 * Aperçu des modèles de bulletin : à gauche la liste des modèles, la présentation (mise en page, couleur,
 * en-tête) qu'on peut essayer puis enregistrer, et ce que le modèle affiche ; à droite le PDF rempli avec un
 * élève d'exemple (bulletin du 3e trimestre ou relevé annuel).
 *
 *   apercu.php[?id=<modèle>][&type=bulletin|releve][&style=...][&couleur=...][&entete=...]   page d'aperçu
 *   apercu.php?id=<modèle>&type=...&pdf=1[&style=...]                                       le PDF seul
 *
 * Fichier : custom/notes/modele/apercu.php
 */

require '../init.php';
dol_include_once('/notes/core/lib/bulletin_pdf.lib.php');

$langs->loadLangs(array('admin', 'notes@notes', 'classes@classes'));

$peutConfigurer = $user->hasRight('notes', 'config', 'gerer');
if (!$peutConfigurer && !$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}
$type = GETPOST('type', 'aZ09') === 'releve' ? 'releve' : 'bulletin';
$modeles = EcoleBulletinModele::choix($db);
$id = GETPOSTINT('id');
if (!isset($modeles[$id])) {
	$id = (int) key($modeles);
}
$modele = new EcoleBulletinModele($db);
if ($id <= 0 || $modele->fetch($id) <= 0) {
	$modele = null;
}

// Présentation essayée (sans être enregistrée) : mise en page, couleur, en-tête
$essai = array();
if ($modele) {
	foreach (array('style', 'couleur', 'entete') as $k) {
		$v = GETPOST($k, 'aZ09');
		if ($v !== '' && ($v === 'defaut' ? $k === 'entete' : isset($modele->fields[$k]['arrayofkeyval'][$v]))) {
			$essai[$k] = $v;
			$modele->$k = ($v === 'defaut') ? null : $v;
		}
	}
}

// Enregistrer la présentation essayée dans le modèle
if ($modele && $peutConfigurer && GETPOST('action', 'aZ09') === 'savestyle') {
	if ($modele->update($user) > 0) {
		setEventMessages($langs->trans('PresentationEnregistree', $modele->ref), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&type='.$type);
		exit;
	}
	setEventMessages(null, $modele->errors, 'errors');
}

// Le PDF seul (affiché dans le cadre de la page)
if (GETPOSTINT('pdf') && $modele) {
	notes_pdf_apercu($db, $modele, $type);
	$db->close();
	exit;
}

llxHeader('', $langs->trans('ApercuModeles'), '', '', 0, 0, '', array('/notes/css/notes.css'), '', 'mod-notes page-apercu');
if ($peutConfigurer) {
	print load_fiche_titre($langs->trans('ConfigurationNotes'), '', 'title_setup');
	print dol_get_fiche_head(notes_admin_prepare_head(), 'apercu', '', -1, '');
} else {
	print load_fiche_titre($langs->trans('ApercuModeles'), '', 'fa-file-alt');
}

if (!$modele) {
	print '<div class="warning">'.$langs->trans('ErrorAucunModele').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$self = $_SERVER['PHP_SELF'];
$qEssai = '';
foreach ($essai as $k => $v) {
	$qEssai .= '&'.$k.'='.urlencode($v);
}
$urlPdf = $self.'?id='.$id.'&type='.$type.$qEssai.'&pdf=1';
// Adresse de la page avec une valeur de présentation changée
$lien = function ($k, $v) use ($self, $id, $type, $essai) {
	$e = $essai;
	$e[$k] = $v;
	$q = '';
	foreach ($e as $kk => $vv) {
		$q .= '&'.$kk.'='.urlencode($vv);
	}
	return $self.'?id='.$id.'&type='.$type.$q;
};

print '<div class="nt-preview">';
print '<div class="nt-preview-side">';

// Type de document
print '<div class="nt-seg">';
foreach (array('bulletin' => 'BulletinTrimestriel', 'releve' => 'ReleveAnnuel') as $k => $l) {
	print '<a class="'.($k === $type ? 'on' : '').'" href="'.dol_escape_htmltag($self.'?id='.$id.'&type='.$k.$qEssai).'">'.$langs->trans($l).'</a>';
}
print '</div>';

// Modèles
print '<div class="nt-card-sub">'.$langs->trans('ChoisirModele').'</div><div class="nt-model-list">';
foreach ($modeles as $mid => $label) {
	print '<a class="nt-model'.($mid === $id ? ' on' : '').'" href="'.dol_escape_htmltag($self.'?id='.$mid.'&type='.$type).'"><span>'.img_picto('', 'fa-file-alt', 'class="pictofixedwidth"').dol_escape_htmltag($label).'</span>';
	print $mid === $id ? img_picto('', 'fa-eye') : '';
	print '</a>';
}
print '</div>';

// Présentation : mise en page, couleur, en-tête (essai immédiat)
print '<div class="nt-card-sub"><b>'.$langs->trans('Presentation').'</b></div>';
print '<div class="nt-card-sub">'.$langs->trans('StyleBulletin').'</div><div class="nt-seg">';
foreach ($modele->fields['style']['arrayofkeyval'] as $k => $l) {
	print '<a class="'.($modele->style === $k ? 'on' : '').'" href="'.dol_escape_htmltag($lien('style', $k)).'">'.$langs->trans($l).'</a>';
}
print '</div>';
print '<div class="nt-card-sub">'.$langs->trans('CouleurBulletin').'</div><div class="nt-swatches">';
foreach (notes_pdf_couleurs_liste() as $k => $cl) {
	$rgb = $cl[1];
	$fond = ($k === 'aucune') ? 'background:repeating-linear-gradient(45deg,#fff,#fff 4px,#ddd 4px,#ddd 6px);border-color:#000' : 'background:rgb('.$rgb[0].','.$rgb[1].','.$rgb[2].')';
	print '<a class="nt-swatch'.($modele->couleur === $k ? ' on' : '').'" style="'.$fond.'" href="'.dol_escape_htmltag($lien('couleur', $k)).'" title="'.dol_escape_htmltag($langs->trans($cl[0])).'"></a>';
}
print '</div><div class="small opacitymedium">'.dol_escape_htmltag($langs->trans(notes_pdf_couleurs_liste()[$modele->couleur][0] ?? 'CouleurBleu')).'</div>';
print '<div class="nt-card-sub">'.$langs->trans('EnteteBulletin').'</div>';
print '<select class="flat minwidth200" onchange="window.location.href = this.value">';
$entetes = array('defaut' => $langs->trans('EnteteParDefaut')) + array_map(function ($l) use ($langs) {
	return $langs->trans($l);
}, $modele->fields['entete']['arrayofkeyval']);
foreach ($entetes as $k => $l) {
	$sel = ($k === 'defaut') ? empty($modele->entete) : ($modele->entete === $k);
	print '<option value="'.dol_escape_htmltag($lien('entete', $k)).'"'.($sel ? ' selected' : '').'>'.dol_escape_htmltag($l).'</option>';
}
print '</select>';
if (!empty($essai) && $peutConfigurer) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="nt-essai">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savestyle">';
	print '<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="type" value="'.$type.'">';
	foreach ($essai as $k => $v) {
		print '<input type="hidden" name="'.$k.'" value="'.dol_escape_htmltag($v).'">';
	}
	print '<span class="small">'.img_picto('', 'fa-info-circle', 'class="pictofixedwidth"').$langs->trans('EssaiNonEnregistre').'</span>';
	print '<input type="submit" class="button button-save smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('EnregistrerPresentation', $modele->ref)).'">';
	print ' <a href="'.dol_escape_htmltag($self.'?id='.$id.'&type='.$type).'">'.$langs->trans('Cancel').'</a>';
	print '</form>';
}

// Ce que le modèle affiche
$langues = array('mixte' => 'LangueMixte', 'fr' => 'LangueToutFrancais', 'ar' => 'LangueToutArabe');
print '<div class="nt-card-meta"><span class="nt-tag nt-accent">'.$langs->trans($langues[$modele->langue] ?? 'LangueMixte').'</span>';
if (!empty($modele->simplifie)) {
	print '<span class="nt-tag nt-warn">'.$langs->trans('ModeleSimplifie').'</span>';
}
print '</div>';
print '<table class="noborder centpercent">';
foreach (array('aff_detail' => 'AffDetail', 'aff_coef' => 'AffCoef', 'aff_rang_matiere' => 'AffRangMatiere', 'aff_moy_classe' => 'AffMoyClasse', 'aff_min_max' => 'AffMinMax', 'aff_rang' => 'AffRang', 'aff_rappel' => 'AffRappel', 'aff_distinction' => 'AffDistinction', 'aff_signature_parent' => 'AffSignatureParent') as $k => $l) {
	$sansEffet = !empty($modele->simplifie) && in_array($k, array('aff_detail', 'aff_coef'), true);
	print '<tr class="oddeven"><td>'.$langs->trans($l).'</td><td class="center">'.($sansEffet ? '<span class="opacitymedium" title="'.dol_escape_htmltag($langs->trans('ModeleSimplifieHelp')).'">—</span>' : (!empty($modele->$k) ? img_picto($langs->trans('Yes'), 'fa-check', 'style="color:#2e9e4f"') : img_picto($langs->trans('No'), 'fa-times', 'style="color:#b3312c"'))).'</td></tr>';
}
print '</table>';

print '<div>';
if ($peutConfigurer) {
	print '<a class="button smallpaddingimp" href="'.dol_buildpath('/notes/modele/card.php', 1).'?id='.$id.'&action=edit&token='.newToken().'">'.img_picto('', 'fa-pen', 'class="pictofixedwidth"').$langs->trans('ModifierModele').'</a> ';
}
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($urlPdf).'" target="_blank">'.img_picto('', 'fa-external-link-alt', 'class="pictofixedwidth"').$langs->trans('OuvrirPdf').'</a>';
print '</div>';
print '<span class="opacitymedium small">'.$langs->trans('AideApercu').'</span>';
print '</div>';

// Le PDF
print '<iframe class="nt-preview-frame" src="'.dol_escape_htmltag($urlPdf).'#view=FitH" title="'.dol_escape_htmltag($langs->trans('ApercuModeles')).'"></iframe>';
print '</div>';

if ($peutConfigurer) {
	print dol_get_fiche_end();
}
llxFooter();
$db->close();
