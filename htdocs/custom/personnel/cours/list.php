<?php
/**
 * Liste des cours signalés : absences, retards, déclarations et remplacements des enseignants
 * (les cours faits sans rien à signaler n'ont pas de ligne). Un filtre sur chaque colonne ;
 * exports PDF / Excel avec les mêmes filtres.
 *
 * Fichier : custom/personnel/cours/list.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

if (!$user->hasRight('personnel', 'presence', 'lire')) {
	accessforbidden();
}
$form = new Form($db);

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$fl = personnel_filtres(personnel_cours_filtres_cles());
$f = $fl['f'];
$param = '&limit='.((int) $limit).$fl['param'];

$total = 0;
$lignes = personnel_cours_liste($db, $f, $limit, $limit * $page, $total);

llxHeader('', $langs->trans('CoursSignales'), '', '', 0, 0, '', '', '', 'mod-personnel page-list');

$buttons = '';
if ($user->hasRight('personnel', 'employe', 'exporter')) {
	$buttons .= ecole_export_buttons('cours', $fl['param'], 'personnel');
}
$buttons .= dolGetButtonTitle($langs->trans('PresenceEnseignants'), '', 'fa fa-chalkboard-teacher', dol_buildpath('/personnel/presence.php', 1));

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($langs->trans('CoursSignales'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($lignes), $total, 'fa-chalkboard-teacher', 0, $buttons, '', $limit, 0, 0, 1);

$creneaux = array();
foreach (ecole_creneaux_actifs($db) as $cid => $c) {
	$creneaux[$cid] = $c->ref;
}
$matieres = personnel_matieres_choix($db, $f['matiere'] ? array($f['matiere']) : array());
$types = array('absent' => $langs->trans('Absent'), 'nonremplace' => $langs->trans('AbsentNonRemplace'), 'remplacement' => $langs->trans('Remplace'),
	'retard' => $langs->trans('EnRetard'), 'declare' => $langs->trans('CoursDeclare'));
$sources = array('appel' => $langs->trans('Source_appel'), 'declaration' => $langs->trans('Source_declaration'), 'direction' => $langs->trans('Source_direction'));

print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMin').'</span> <input type="date" class="flat maxwidth125" name="f_du" value="'.dol_escape_htmltag($f['du']).'"></div>';
print '<div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMax').'</span> <input type="date" class="flat maxwidth125" name="f_au" value="'.dol_escape_htmltag($f['au']).'"></div></td>';
print '<td class="liste_titre">'.$form->selectarray('f_creneau', $creneaux, $f['creneau'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth75').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_classe', personnel_classes_filtre($db), $f['classe'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_matiere', $matieres, $f['matiere'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="f_employe" value="'.dol_escape_htmltag($f['employe']).'" placeholder="'.dol_escape_htmltag($langs->trans('MatriculeOuNom')).'"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_type', $types, $f['type'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_etat', array('oui' => $langs->trans('Justifiee'), 'non' => $langs->trans('NonJustifiee')), $f['etat'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_source', $sources, $f['source'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';

print '<tr class="liste_titre">';
foreach (array('Date', 'Creneau', 'Classe', 'Matiere', 'Enseignant', 'Presence', 'Justification', 'SujetOuRemarque', 'EnregistrePar', '') as $t) {
	print '<td class="liste_titre">'.($t !== '' ? $langs->trans($t) : '').'</td>';
}
print '</tr>';

foreach ($lignes as $r) {
	$d = substr($r->date_cours, 0, 10);
	$etat = personnel_etat_cours($r, null, false, false);
	$info = ($etat === 'retard') ? '('.$langs->trans('NbMinutes', (int) $r->minutes_retard).')' : '';
	print '<tr class="oddeven"><td class="nowraponall">'.dol_escape_htmltag(personnel_date_label($d)).'</td>';
	print '<td class="nowraponall">'.dol_escape_htmltag($r->crref).' <span class="opacitymedium small">'.$r->heure_debut.'</span></td>';
	print '<td>'.dol_escape_htmltag($r->cref).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $r->m_fr, 'label_ar' => $r->m_ar))).'</td>';
	print '<td>'.((int) $r->fk_employe > 0 ? '<a href="'.dol_buildpath('/personnel/employe/presence.php', 1).'?id='.((int) $r->fk_employe).'&mois='.substr($d, 0, 7).'">'.dol_escape_htmltag($r->eref).'</a> '.dol_escape_htmltag(ecole_label($r)) : '').'</td>';
	print '<td class="nowraponall">'.personnel_etat_badge($etat, $info);
	if ((int) $r->fk_remplacant > 0) {
		print '<br><span class="small">'.img_picto('', 'fa-exchange-alt', 'class="pictofixedwidth"').$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $r->fk_remplacant))).'</span>';
	}
	print '</td>';
	print '<td>';
	if (in_array((int) $r->etat, array(PERSONNEL_ABSENT, PERSONNEL_RETARD), true)) {
		print ((int) $r->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="opacitymedium">'.$langs->trans('NonJustifiee').'</span>');
		print ($r->motif_fr ? ' '.dol_escape_htmltag(ecole_label((object) array('label_fr' => $r->motif_fr, 'label_ar' => $r->motif_ar))) : '');
	}
	print '</td>';
	print '<td>'.dol_escape_htmltag(trim((string) $r->sujet.((string) $r->note !== '' ? ' — '.$r->note : ''))).'</td>';
	print '<td class="small">'.dol_escape_htmltag($langs->trans('Source_'.$r->source)).'<br><span class="opacitymedium">'.dol_escape_htmltag(ecole_user_label($db, $r->fk_user_modif ? $r->fk_user_modif : $r->fk_user_creat)).'</span></td>';
	print '<td class="center"><a href="'.dol_buildpath('/personnel/presence.php', 1).'?date='.$d.'&fk_classe='.((int) $r->fk_classe).'&fk_creneau='.((int) $r->fk_creneau).'#cours" title="'.dol_escape_htmltag($langs->trans('VoirCours')).'">'.img_picto($langs->trans('VoirCours'), 'fa-external-link-alt').'</a></td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
print '</form>';
print '<span class="opacitymedium small">'.$langs->trans('AideCoursSignales').'</span>';

llxFooter();
$db->close();
