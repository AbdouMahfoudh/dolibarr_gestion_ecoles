<?php
/**
 * Liste générale des sanctions : filtres (période, classe, élève, type, en vigueur / annulée),
 * exports PDF / Excel avec les mêmes filtres. Une sanction s'enregistre et s'annule depuis la fiche
 * de l'élève (onglet « Absences et discipline »).
 *
 * Fichier : custom/eleves/sanction/list.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('eleves', 'sanction', 'lire')) {
	accessforbidden();
}
$form = new Form($db);

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$fl = eleves_disc_filtres('sanctions');
$f = $fl['f'];
$param = '&limit='.((int) $limit).$fl['param'];

$total = 0;
$lignes = eleves_sanctions_liste($db, $f, $limit, $limit * $page, $total);

llxHeader('', $langs->trans('Sanctions'), '', '', 0, 0, '', '', '', 'mod-eleves page-list');

$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons .= ecole_export_buttons('sanctions', $fl['param'], 'eleves');
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($langs->trans('Sanctions'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($lignes), $total, 'fa-gavel', 0, $buttons, '', $limit, 0, 0, 1);

$types = array();
foreach (EcoleSanctionType::actifs($db) as $tid => $t) {
	$types[$tid] = ecole_label($t);
}
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMin').'</span> <input type="date" class="flat maxwidth125" name="f_du" value="'.dol_escape_htmltag($f['du']).'"></div>';
print '<div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMax').'</span> <input type="date" class="flat maxwidth125" name="f_au" value="'.dol_escape_htmltag($f['au']).'"></div></td>';
print '<td class="liste_titre">'.$form->selectarray('f_classe', eleves_classes_filtre($db), $f['classe'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="f_eleve" value="'.dol_escape_htmltag($f['eleve']).'" placeholder="'.dol_escape_htmltag($langs->trans('MatriculeOuNom')).'"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_type', $types, $f['type'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td><td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_etat', array('valide' => $langs->trans('SanctionEnVigueur'), 'annulee' => $langs->trans('SanctionAnnulee')), $f['etat'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';

print '<tr class="liste_titre">';
foreach (array('Date', 'Classe', 'Eleve', 'TypeSanction', 'MotifSanction', 'EnregistrePar', 'Etat', '') as $t) {
	print '<td class="liste_titre">'.($t !== '' ? $langs->trans($t) : '').'</td>';
}
print '</tr>';

foreach ($lignes as $s) {
	$barre = (int) $s->status ? '' : ' style="text-decoration:line-through"';
	print '<tr class="oddeven"><td class="nowraponall"'.$barre.'>'.dol_escape_htmltag(eleves_sanction_periode($s, $db)).'</td>';
	print '<td>'.dol_escape_htmltag($s->cref).'</td>';
	print '<td><a href="'.dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $s->fk_eleve).'#sanctions">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($s->ref).'</a> '.dol_escape_htmltag(ecole_label($s)).'</td>';
	print '<td'.$barre.'>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $s->type_fr, 'label_ar' => $s->type_ar))).'</td>';
	print '<td class="tdoverflowmax300"'.$barre.' title="'.dol_escape_htmltag($s->motif).'">'.dol_escape_htmltag($s->motif).'</td>';
	print '<td class="nowraponall">'.dol_escape_htmltag(ecole_user_label($db, $s->fk_user_creat)).'</td>';
	print '<td>'.((int) $s->status ? '<span class="badge badge-status4">'.$langs->trans('SanctionEnVigueur').'</span>' : '<span class="badge badge-status8" title="'.dol_escape_htmltag($s->motif_annulation).'">'.$langs->trans('SanctionAnnulee').'</span>').'</td>';
	print '<td></td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
print '</form>';
print '<span class="opacitymedium small">'.$langs->trans('AideListeSanctions').'</span>';

llxFooter();
$db->close();
