<?php
/**
 * Liste générale des absences et retards : filtres (période, classe, élève, créneau, absent / retard,
 * justifiée ou non), justification directe, exports PDF / Excel avec les mêmes filtres.
 *
 * Fichier : custom/eleves/absence/list.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('eleves', 'absence', 'lire')) {
	accessforbidden();
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$fl = eleves_disc_filtres('absences');
$f = $fl['f'];
$param = '&limit='.((int) $limit).$fl['param'];
$self = $_SERVER['PHP_SELF'].'?page='.((int) $page).$param;

if (eleves_absence_actions($db, $user, $action)) {
	header('Location: '.$self);
	exit;
}

$total = 0;
$lignes = eleves_absences_liste($db, $f, $limit, $limit * $page, $total);
$motifs = EcoleMotifAbsence::choix($db);

llxHeader('', $langs->trans('AbsencesEtRetards'), '', '', 0, 0, '', '', '', 'mod-eleves page-list');

$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons .= ecole_export_buttons('absences', $fl['param'], 'eleves');
}
if ($user->hasRight('eleves', 'absence', 'appel')) {
	$buttons .= dolGetButtonTitle($langs->trans('FaireAppel'), '', 'fa fa-clipboard-check', dol_buildpath('/eleves/appel.php', 1));
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($langs->trans('AbsencesEtRetards'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($lignes), $total, 'fa-user-clock', 0, $buttons, '', $limit, 0, 0, 1);

$creneaux = array();
foreach (ecole_creneaux_actifs($db) as $cid => $c) {
	$creneaux[$cid] = $c->ref;
}
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMin').'</span> <input type="date" class="flat maxwidth125" name="f_du" value="'.dol_escape_htmltag($f['du']).'"></div>';
print '<div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMax').'</span> <input type="date" class="flat maxwidth125" name="f_au" value="'.dol_escape_htmltag($f['au']).'"></div></td>';
print '<td class="liste_titre">'.$form->selectarray('f_creneau', $creneaux, $f['creneau'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth75').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_classe', eleves_classes_filtre($db), $f['classe'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="f_eleve" value="'.dol_escape_htmltag($f['eleve']).'" placeholder="'.dol_escape_htmltag($langs->trans('MatriculeOuNom')).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_type', array(ELEVES_ABSENT => $langs->trans('Absent'), ELEVES_RETARD => $langs->trans('EnRetard'), ELEVES_RENVOYE => $langs->trans('RenvoyeCours')), $f['type'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_etat', array('oui' => $langs->trans('Justifiee'), 'non' => $langs->trans('NonJustifiee')), $f['etat'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';

print '<tr class="liste_titre">';
foreach (array('Date', 'Creneau', 'Matiere', 'Classe', 'Eleve', 'ParentTelephone', 'Presence', 'Justification', 'WhatsApp', '') as $t) {
	print '<td class="liste_titre">'.($t !== '' ? $langs->trans($t) : '').'</td>';
}
print '</tr>';

foreach ($lignes as $a) {
	$date = dol_print_date($db->jdate($a->date_appel), '%Y-%m-%d');
	print '<tr class="oddeven"><td class="nowraponall">'.dol_escape_htmltag(eleves_date_label($date)).'</td>';
	print '<td class="nowraponall">'.dol_escape_htmltag($a->crref).' <span class="opacitymedium small">'.$a->heure_debut.'</span></td>';
	print '<td>'.dol_escape_htmltag(eleves_absence_matiere($a)).'</td>';
	print '<td>'.dol_escape_htmltag($a->cref).'</td>';
	print '<td><a href="'.dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $a->fk_eleve).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($a->ref).'</a> '.dol_escape_htmltag(ecole_label($a)).'</td>';
	print '<td>'.dol_escape_htmltag(eleves_absence_responsable($a)).'<br><span class="nowraponall">'.dol_print_phone((string) $a->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone').'</span></td>';
	print '<td>'.eleves_presence_badge($a).'</td>';
	print '<td>'.eleves_justification_cell($a, $self, $motifs).'</td>';
	print '<td class="center">'.eleves_whatsapp_button(eleves_whatsapp_url($db, (int) $a->fk_eleve, $date, (int) $a->type), false).'</td>';
	print '<td class="center"><a href="'.dol_buildpath('/eleves/appel.php', 1).'?date='.$date.'&fk_classe='.((int) $a->fk_classe).'&fk_creneau='.((int) $a->fk_creneau).'" title="'.dol_escape_htmltag($langs->trans('VoirAppel')).'">'.img_picto($langs->trans('VoirAppel'), 'fa-clipboard-check').'</a></td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
print '</form>';
eleves_justifier_forms_flush();

llxFooter();
$db->close();
