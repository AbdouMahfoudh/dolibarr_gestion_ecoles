<?php
/**
 * Heures du mois : un tableau avec tous les employés (cours prévus et faits, absences, retards, remplacements,
 * heures supplémentaires, jours d'absence) et l'estimation de la paie (droit « Paie : voir »).
 * Filtres : nom ou matricule, catégorie, mode de paie ; exports PDF / Excel avec les mêmes filtres.
 * Le calcul définitif des salaires (primes, avances, prêts, charges) se fera dans le module Salaires.
 *
 * Fichier : custom/personnel/heures.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

if (!$user->hasRight('personnel', 'presence', 'lire')) {
	accessforbidden();
}
$form = new Form($db);
$paie = $user->hasRight('personnel', 'paie', 'lire');
$mois = personnel_mois_ok(GETPOST('mois', 'alpha'));
if ($mois === '') {
	$mois = dol_print_date(dol_now(), '%Y-%m', 'tzuserrel');
}
$fl = personnel_filtres(personnel_heures_filtres_cles());
$f = $fl['f'];
$lignes = personnel_heures_lignes($db, $f, $mois);

llxHeader('', $langs->trans('HeuresDuMois'), '', '', 0, 0, '', '', '', 'mod-personnel page-list');

$buttons = '';
if ($user->hasRight('personnel', 'employe', 'exporter')) {
	$buttons .= ecole_export_buttons('heures', '&mois='.$mois.$fl['param'], 'personnel');
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
$prec = date('Y-m', strtotime($mois.'-01 -1 month'));
$suiv = date('Y-m', strtotime($mois.'-01 +1 month'));
$titre = $langs->trans('HeuresDuMois').' — '.personnel_mois_label($mois);
print load_fiche_titre($titre, $buttons, 'fa-business-time');
print '<div class="marginbottomonly">';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?mois='.$prec.$fl['param']).'">&lsaquo;</a> ';
print '<input type="month" name="mois" class="flat" value="'.dol_escape_htmltag($mois).'" onchange="this.form.submit()"> ';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?mois='.$suiv.$fl['param']).'">&rsaquo;</a>';
print '</div>';

$modes = array('fixe' => $langs->trans('PaieFixe'), 'heure' => $langs->trans('PaieHeure'), 'aucun' => $langs->trans('NonRenseigne'));
$nbcols = $paie ? 12 : 11;
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="f_employe" value="'.dol_escape_htmltag($f['employe']).'" placeholder="'.dol_escape_htmltag($langs->trans('MatriculeOuNom')).'"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_categorie', personnel_categories_choix(), $f['categorie'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_mode', $modes, $f['mode'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print str_repeat('<td class="liste_titre"></td>', $nbcols - 4);
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';

print '<tr class="liste_titre">';
$titres = array('Employe', 'CategoriesEmploye', 'ModePaie', 'CoursPrevus', 'CoursFaits', 'AbsencesNjJ', 'Retards', 'RemplacementsFaits', 'HeuresSup', 'JoursAbsenceNjJ');
if ($paie) {
	$titres[] = 'EstimationMois';
}
foreach ($titres as $t) {
	print '<td class="liste_titre'.($t === 'EstimationMois' ? ' right' : ($t === 'Employe' || $t === 'CategoriesEmploye' ? '' : ' center')).'">'.$langs->trans($t).'</td>';
}
print '<td class="liste_titre"></td></tr>';

$tot = array('prevues' => 0, 'faites' => 0, 'est' => 0);
foreach ($lignes as $l) {
	$e = $l['emp'];
	$t = $l['h']['totaux'];
	$tot['prevues'] += $t['prevues'];
	$tot['faites'] += $t['faites'];
	$url = dol_buildpath('/personnel/employe/presence.php', 1).'?id='.((int) $e->id).'&mois='.$mois;
	print '<tr class="oddeven">';
	print '<td><a href="'.$url.'">'.img_picto('', 'fa-id-badge', 'class="pictofixedwidth"').dol_escape_htmltag($e->ref).'</a> '.dol_escape_htmltag(ecole_label($e)).((int) $e->status === EcoleEmploye::STATUS_PARTI ? ' '.$e->getLibStatut(3) : '').'</td>';
	print '<td>'.personnel_categories_badges((string) $e->categories).'</td>';
	print '<td class="center">'.(isset($modes[$e->mode_paie]) ? dol_escape_htmltag($modes[$e->mode_paie]) : '').'</td>';
	print '<td class="center">'.($t['prevues'] ? personnel_duree($t['prevues']) : '').'</td>';
	print '<td class="center"><b>'.($t['prevues'] || $t['faites'] ? personnel_duree($t['faites']) : '').'</b></td>';
	print '<td class="center">'.($t['nb_absences'] ? '<span class="badge badge-danger" title="'.dol_escape_htmltag($langs->trans('AbsJustNonJust', personnel_duree($t['absent_j']), personnel_duree($t['absent_nj']))).'">'.personnel_duree($t['absent_nj']).'</span> <span class="opacitymedium small">'.personnel_duree($t['absent_j']).'</span>' : '').'</td>';
	print '<td class="center">'.($t['nb_retards'] ? '<span class="badge badge-warning">'.$t['nb_retards'].'</span> <span class="opacitymedium small">'.$langs->trans('NbMinutes', $t['retard_min']).'</span>' : '').'</td>';
	print '<td class="center">'.($t['nb_remplacements'] ? personnel_duree($t['remplacement']) : '').'</td>';
	print '<td class="center">'.($t['heures_sup'] ? personnel_duree($t['heures_sup']) : '').'</td>';
	print '<td class="center">'.(($t['jours_absent_j'] + $t['jours_absent_nj']) ? '<span class="badge badge-danger">'.price2num($t['jours_absent_nj']).'</span> <span class="opacitymedium small">'.price2num($t['jours_absent_j']).'</span>' : '').'</td>';
	if ($paie) {
		$est = $l['h']['estimation'];
		$tot['est'] += $est['possible'] ? $est['total'] : 0;
		print '<td class="right nowraponall">'.($est['possible'] ? personnel_montant($est['total']) : '<span class="opacitymedium" title="'.dol_escape_htmltag($langs->trans('EstimationImpossible')).'">—</span>').'</td>';
	}
	print '<td class="center"><a href="'.$url.'" title="'.dol_escape_htmltag($langs->trans('VoirDetail')).'">'.img_picto($langs->trans('VoirDetail'), 'fa-search').'</a></td>';
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="'.($nbcols).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="3">'.$langs->trans('Total').' ('.count($lignes).')</td><td class="center">'.personnel_duree($tot['prevues']).'</td><td class="center">'.personnel_duree($tot['faites']).'</td>';
	print '<td colspan="5"></td>'.($paie ? '<td class="right nowraponall">'.personnel_montant($tot['est']).'</td>' : '').'<td></td></tr>';
}
print '</table></div>';
print '</form>';
print '<span class="opacitymedium small">'.$langs->trans('AideHeuresMois').'</span>';

llxFooter();
$db->close();
