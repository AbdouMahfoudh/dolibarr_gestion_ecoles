<?php
/**
 * Élèves signalés : élèves inscrits ou suspendus qui atteignent un seuil d'absences ou de retards
 * non justifiés (seuils réglés dans Configuration > Absences et discipline). Simple outil de suivi.
 *
 * Fichier : custom/eleves/signales.php
 */

require 'init.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('eleves', 'absence', 'lire')) {
	accessforbidden();
}

$lignes = eleves_signales($db);
$s = eleves_seuils();

llxHeader('', $langs->trans('ElevesSignales'), '', '', 0, 0, '', '', '', 'mod-eleves page-signales');

$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons .= ecole_export_buttons('signales', '', 'eleves');
}
print load_fiche_titre($langs->trans('ElevesSignales').' ('.count($lignes).')', $buttons, 'fa-flag');

print '<div class="opacitymedium marginbottomonly">'.$langs->trans('SeuilsResume', $s['absences'] ?: '-', $s['retards'] ?: '-');
if ($user->hasRight('eleves', 'config', 'gerer')) {
	print ' — <a href="'.dol_buildpath('/eleves/admin/discipline.php', 1).'">'.$langs->trans('ModifierSeuils').'</a>';
}
print '</div>';

if ($s['absences'] <= 0 && $s['retards'] <= 0) {
	print '<div class="info">'.$langs->trans('AucunSeuilRegle').'</div>';
}

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Responsable').'</td><td>'.$langs->trans('TelephoneResponsable').'</td>';
print '<td class="center">'.$langs->trans('AbsencesNonJustifiees').'</td><td class="center">'.$langs->trans('RetardsNonJustifies').'</td><td class="center">'.$langs->trans('Sanctions').'</td></tr>';
foreach ($lignes as $l) {
	$e = $l['eleve'];
	$c = $l['compteurs'];
	$tel = dol_print_phone($e->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone');
	$num = eleves_whatsapp_numero((string) $e->whatsapp, (string) $e->telephone);
	if ($num !== '') {
		$tel .= ' <a href="https://wa.me/'.$num.'" target="_blank" rel="noopener" title="WhatsApp">'.img_picto('WhatsApp', 'fa-whatsapp').'</a>';
	}
	$absclass = ($s['absences'] > 0 && $c['absences_nj'] >= $s['absences']) ? 'badge badge-danger' : '';
	$retclass = ($s['retards'] > 0 && $c['retards_nj'] >= $s['retards']) ? 'badge badge-danger' : '';
	print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $e->rowid).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($e->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($e)).'</td><td>'.dol_escape_htmltag($e->cref).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label((object) array('nom_fr' => $e->rnom_fr, 'nom_ar' => $e->rnom_ar))).'</td><td class="nowraponall">'.$tel.'</td>';
	print '<td class="center"><span class="'.$absclass.'">'.$c['absences_nj'].'</span></td><td class="center"><span class="'.$retclass.'">'.$c['retards_nj'].'</span></td><td class="center">'.$c['sanctions'].'</td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunEleveSignale').'</span></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideElevesSignales').'</span>';

llxFooter();
$db->close();
