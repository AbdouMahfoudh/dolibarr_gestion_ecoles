<?php
/**
 * Historique des notes : chaque saisie, modification ou effacement (qui, quand, avant, après, motif).
 * Un enseignant ne voit que ses classes et matières.
 *
 * Fichier : custom/notes/historique.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!notes_voit_tout($user) && !$user->hasRight('notes', 'note', 'saisir')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');

$f = notes_historique_filtres($remove);
$param = '';
foreach ($f as $k => $v) {
	if (!empty($v)) {
		$param .= '&'.$k.'='.urlencode((string) $v);
	}
}

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) {
	$page = 0;
}
$total = 0;
$lignes = notes_historique($db, $user, $f, $limit, $limit * $page, $total);

/*
 * Affichage
 */
llxHeader('', $langs->trans('HistoriqueNotes'), '', '', 0, 0, '', '', '', 'mod-notes page-historique');

// Choix des filtres
$choixClasses = array();
foreach (notes_classes($db, $user) as $cid => $c) {
	$choixClasses[$cid] = $c->ref;
}
$choixMat = array();
$resql = $db->query("SELECT rowid, label_fr, label_ar FROM ".$db->prefix()."ecole_matiere WHERE entity IN (".getEntity('ecole_matiere').") ORDER BY label_fr");
while ($resql && ($o = $db->fetch_object($resql))) {
	$choixMat[(int) $o->rowid] = ecole_label($o);
}
$choixTrim = array(1 => notes_trimestre_label(1), 2 => notes_trimestre_label(2), 3 => notes_trimestre_label(3));
$choixUsers = array();
$resql = $db->query("SELECT DISTINCT fk_user FROM ".$db->prefix()."ecole_note_log WHERE fk_user IS NOT NULL");
while ($resql && ($o = $db->fetch_object($resql))) {
	$choixUsers[(int) $o->fk_user] = ecole_user_label($db, (int) $o->fk_user);
}

print '<form method="POST" action="'.dol_escape_htmltag($self).'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($f['fk_evaluation']) {
	print '<input type="hidden" name="fk_evaluation" value="'.((int) $f['fk_evaluation']).'">';
}
if ($f['fk_eleve']) {
	print '<input type="hidden" name="fk_eleve" value="'.((int) $f['fk_eleve']).'">';
}
print_barre_liste($langs->trans('HistoriqueNotes'), $page, $self, $param.'&limit='.$limit, '', '', '', count($lignes), $total, 'fa-history', 0, ecole_export_buttons('historique', $param, 'notes'), '', $limit, 0, 0, 1);

print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
// Un filtre par colonne
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre nowraponall"><div><span class="opacitymedium small">'.$langs->trans('FiltreMin').'</span> <input type="date" class="flat maxwidth125" name="du" value="'.dol_escape_htmltag($f['du']).'"></div>';
print '<div><span class="opacitymedium small">'.$langs->trans('FiltreMax').'</span> <input type="date" class="flat maxwidth125" name="au" value="'.dol_escape_htmltag($f['au']).'"></div></td>';
print '<td class="liste_titre">'.$form->selectarray('fk_user', $choixUsers, $f['fk_user'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre">'.$form->selectarray('fk_classe', $choixClasses, $f['fk_classe'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre">'.$form->selectarray('fk_matiere', $choixMat, $f['fk_matiere'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"><div>'.$form->selectarray('type', array(NOTES_DEVOIR => $langs->trans('Devoir'), NOTES_COMPO => $langs->trans('Composition')), $f['type'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</div>';
print '<div>'.$form->selectarray('trimestre', $choixTrim, $f['trimestre'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</div></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="eleve" value="'.dol_escape_htmltag($f['eleve']).'"></td>';
print '<td class="liste_titre center"><input type="text" class="flat width50" name="avant" value="'.dol_escape_htmltag($f['avant']).'" title="'.dol_escape_htmltag($langs->trans('AideFiltreNote')).'"></td>';
print '<td class="liste_titre center"><input type="text" class="flat width50" name="apres" value="'.dol_escape_htmltag($f['apres']).'" title="'.dol_escape_htmltag($langs->trans('AideFiltreNote')).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="motif" value="'.dol_escape_htmltag($f['motif'] === '__avec' ? '' : $f['motif']).'"> ';
print '<label class="small nowraponall"><input type="checkbox" name="motif_avec" value="1"'.($f['motif'] === '__avec' ? ' checked' : '').'> '.$langs->trans('AvecMotif').'</label></td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('User').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td>';
print '<td>'.$langs->trans('Evaluation').'</td><td>'.$langs->trans('Eleve').'</td><td class="center">'.$langs->trans('Avant').'</td><td class="center">'.$langs->trans('Apres').'</td><td>'.$langs->trans('Motif').'</td><td></td></tr>';

foreach ($lignes as $l) {
	$urlGrille = dol_buildpath('/notes/saisie.php', 1).'?fk_classe='.((int) $l->fk_classe).'&fk_matiere='.((int) $l->fk_matiere).'&trimestre='.((int) $l->trimestre);
	print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($l->date_creation), 'dayhour', 'tzuserrel').'</td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $l->fk_user)).'</td>';
	print '<td>'.dol_escape_htmltag((string) $l->cref).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $l->mlabel_fr, 'label_ar' => $l->mlabel_ar))).'</td>';
	print '<td class="nowraponall"><a href="'.dol_escape_htmltag($urlGrille).'">'.dol_escape_htmltag(notes_evaluation_nom($l)).'</a> <span class="opacitymedium small">'.dol_escape_htmltag(notes_trimestre_label($l->trimestre)).'</span>';
	if ((int) $l->vstatus === 0) {
		print ' <span class="badge badge-status8 small">'.$langs->trans('EvaluationSupprimeeCourt').'</span>';
	}
	print '</td>';
	print '<td><a href="'.dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $l->fk_eleve).'" title="'.dol_escape_htmltag($l->eref).'">'.dol_escape_htmltag(ecole_label($l)).'</a></td>';
	print '<td class="center">'.dol_escape_htmltag(notes_code_label((string) $l->avant)).'</td>';
	print '<td class="center"><b>'.dol_escape_htmltag(notes_code_label((string) $l->apres)).'</b> <span class="opacitymedium small">/'.notes_fmt($l->note_max).'</span></td>';
	print '<td>'.dol_escape_htmltag((string) $l->motif).'</td><td></td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
print '</form>';

llxFooter();
$db->close();
