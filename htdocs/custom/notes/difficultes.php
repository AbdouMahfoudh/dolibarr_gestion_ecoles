<?php
/**
 * Élèves en difficulté : moyenne générale de la période en dessous du seuil (réglable ici, valeur par défaut
 * dans la configuration), avec les matières sous 10, le responsable, son téléphone et un bouton WhatsApp
 * (message prêt en arabe et en français, envoi manuel). Filtre sur chaque colonne, exports PDF / Excel.
 *
 * Fichier : custom/notes/difficultes.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/notes/core/lib/resultats.lib.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$classes = notes_classes_resultats($db, $user);
$fk_classe = GETPOSTINT('fk_classe');
if (!isset($classes[$fk_classe])) {
	$fk_classe = 0;
}
$periode = notes_periode_requete($db, $fk_classe);
$seuil = GETPOST('seuil', 'alpha') !== '' ? (float) price2num(GETPOST('seuil', 'alpha')) : (float) getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10');
if ($seuil <= 0 || $seuil > 20) {
	$seuil = 10;
}
$param = '&fk_classe='.$fk_classe.'&seuil='.urlencode(notes_code($seuil, ''));

llxHeader('', $langs->trans('ElevesEnDifficulte'), '', '', 0, 0, '', '', '', 'mod-notes page-difficultes');
print load_fiche_titre($langs->trans('ElevesEnDifficulte'), ecole_export_buttons('difficultes', $param.'&periode='.$periode, 'notes'), 'fa-life-ring');

$choix = array();
foreach ($classes as $cid => $c) {
	$choix[$cid] = $c->ref.' - '.ecole_label($c);
}
print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
print '<input type="hidden" name="periode" value="'.$periode.'">';
print img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').$form->selectarray('fk_classe', $choix, $fk_classe, $langs->trans('ToutesLesClasses'), 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1).' ';
print '<span class="marginleftonly">'.$langs->trans('MoyenneInferieureA', '').'</span> <input type="number" name="seuil" min="1" max="20" step="0.25" class="flat maxwidth75" value="'.dol_escape_htmltag(notes_code($seuil, '')).'"> / 20 ';
print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'">';
print '</form>';
print notes_periode_tabs($db, $self.'?'.ltrim($param, '&'), $periode, $fk_classe);

$lignes = notes_difficultes($db, $user, $periode, $seuil, $fk_classe);
$choixClasses = array();
foreach ($lignes as $l) {
	$choixClasses[$l['classe']->ref] = $l['classe']->ref;
}

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent notes-filtrable">';
print notes_filtres_ligne(array($choixClasses, 'text', 'range', 'range', 'text', 'text', 'text', null));
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Eleve').'</td><td class="center">'.$langs->trans('MoyenneGenerale').'</td><td class="center">'.$langs->trans('Rang').'</td>';
print '<td>'.$langs->trans('MatieresSous10').'</td><td>'.$langs->trans('Responsable').'</td><td>'.$langs->trans('TelephoneResponsable').'</td><td class="center">'.$langs->trans('WhatsApp').'</td></tr>';
foreach ($lignes as $l) {
	$e = $l['eleve'];
	$r = $l['res'];
	print '<tr class="oddeven">';
	print '<td data-f="'.dol_escape_htmltag($l['classe']->ref).'"><a href="'.dol_buildpath('/notes/resultats.php', 1).'?fk_classe='.((int) $l['classe']->rowid).'&periode='.$periode.'">'.dol_escape_htmltag($l['classe']->ref).'</a></td>';
	print '<td class="nowraponall"><a href="'.dol_buildpath('/notes/eleve.php', 1).'?id='.((int) $e->rowid).'&periode='.$periode.'" dir="auto">'.dol_escape_htmltag(ecole_label($e)).'</a></td>';
	print '<td class="center" data-f="'.round($r['moyenne'], 2).'"><b class="error">'.notes_moy($r['moyenne']).'</b></td>';
	print '<td class="center" data-f="'.($r['rang'] !== null ? (int) $r['rang'] : '').'">'.dol_escape_htmltag(notes_rang_label($r['rang'], $r['exaequo'])).' / '.$l['nb'].'</td>';
	print '<td>'.dol_escape_htmltag(implode(', ', $l['faibles'])).'</td>';
	print '<td>'.($l['resp'] ? dol_escape_htmltag(ecole_label((object) array('nom_fr' => (string) $l['resp']->nom_fr, 'nom_ar' => (string) $l['resp']->nom_ar))) : '').'</td>';
	print '<td class="nowraponall">'.($l['resp'] ? dol_print_phone((string) $l['resp']->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone') : '').'</td>';
	print '<td class="center">'.eleves_whatsapp_button(notes_whatsapp_difficulte($l, $periode)).'</td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunEleveEnDifficulte').'</span></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideDifficultes').'</span>';

llxFooter();
$db->close();
