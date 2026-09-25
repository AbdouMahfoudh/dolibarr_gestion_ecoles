<?php
/**
 * Absences du personnel (tous les employés : présents par défaut, seules les absences sont enregistrées).
 * Saisie (une date, une demi-journée ou une période), justification avec motif, annulation d'une erreur
 * (la ligne reste, barrée, avec le motif). Un filtre sur chaque colonne ; exports PDF / Excel.
 * Pour un enseignant, ses cours pendant l'absence comptent comme non faits.
 *
 * Fichier : custom/personnel/absence/list.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

if (!$user->hasRight('personnel', 'absence', 'lire')) {
	accessforbidden();
}
$cangerer = $user->hasRight('personnel', 'absence', 'gerer');
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$fl = personnel_filtres(personnel_absences_filtres_cles());
$f = $fl['f'];
$param = '&limit='.((int) $limit).$fl['param'];
$self = $_SERVER['PHP_SELF'].'?page='.((int) $page).$param;

/*
 * Actions
 */
if ($cangerer && $action === 'add') {
	$error = '';
	$a = array('fk_employe' => GETPOSTINT('fk_employe'), 'date_debut' => GETPOST('date_debut', 'alpha'), 'date_fin' => GETPOST('date_fin', 'alpha'),
		'duree' => GETPOST('duree', 'aZ09'), 'justifiee' => GETPOST('justifiee', 'alpha') ? 1 : 0, 'fk_motif' => GETPOSTINT('fk_motif'), 'note' => GETPOST('note', 'alphanohtml'));
	if (personnel_absence_creer($db, $user, $a, $error) > 0) {
		setEventMessages($langs->trans('AbsenceEnregistree'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	setEventMessages($error, null, 'errors');
	$action = 'create';
}
if ($cangerer && $action === 'justifier' && $id > 0) {
	if (personnel_absence_justifier($db, $user, $id, GETPOST('justifiee', 'alpha') ? true : false, GETPOSTINT('fk_motif'), GETPOST('note', 'alphanohtml')) > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: '.$self);
	exit;
}
if ($cangerer && $action === 'confirm_annuler' && $id > 0 && GETPOST('confirm', 'alpha') === 'yes') {
	$error = '';
	if (personnel_absence_annuler($db, $user, $id, GETPOST('motif', 'alphanohtml'), $error) > 0) {
		setEventMessages($langs->trans('AbsenceAnnulee'), null, 'mesgs');
	} else {
		setEventMessages($error, null, 'errors');
	}
	header('Location: '.$self);
	exit;
}

$total = 0;
$lignes = personnel_absences_liste($db, $f, $limit, $limit * $page, $total);
$motifs = EcoleEmployeMotif::choix($db, 'absence');

/*
 * Affichage
 */
llxHeader('', $langs->trans('AbsencesPersonnel'), '', '', 0, 0, '', '', '', 'mod-personnel page-list');

if ($cangerer && $action === 'annuler' && $id > 0) {
	print $form->formconfirm($self.'&id='.$id, $langs->trans('AnnulerAbsence'), $langs->trans('ConfirmAnnulerAbsence'), 'confirm_annuler',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('MotifAnnulation'), 'morecss' => 'minwidth300')), 'yes', 1, 0, 550);
}

$buttons = '';
if ($user->hasRight('personnel', 'employe', 'exporter')) {
	$buttons .= ecole_export_buttons('absences', $fl['param'], 'personnel');
}
$buttons .= dolGetButtonTitle($langs->trans('NouvelleAbsence'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create'.$fl['param'], '', $cangerer);

// Formulaire de saisie
if ($cangerer && $action === 'create') {
	print load_fiche_titre($langs->trans('NouvelleAbsence'), '', 'fa-user-clock');
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
	print '<table class="border centpercent tableforfield">';
	$fk = GETPOSTINT('fk_employe');
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Employe').'</td><td>'.$form->selectarray('fk_employe', personnel_employes_choix($db, '', $fk), $fk, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	$du = personnel_date_ok(GETPOST('date_debut', 'alpha'));
	$au = personnel_date_ok(GETPOST('date_fin', 'alpha'));
	print '<tr><td class="fieldrequired">'.$langs->trans('Du').'</td><td><input type="date" name="date_debut" class="flat" value="'.dol_escape_htmltag($du !== '' ? $du : personnel_aujourdhui()).'"> ';
	print $langs->trans('Au').' <input type="date" name="date_fin" class="flat" value="'.dol_escape_htmltag($au).'"> <span class="opacitymedium small">'.$langs->trans('AuVideUneDate').'</span></td></tr>';
	$durees = array();
	foreach (personnel_durees() as $k => $l) {
		$durees[$k] = $langs->trans($l);
	}
	print '<tr><td>'.$langs->trans('Duree').'</td><td>'.$form->selectarray('duree', $durees, GETPOST('duree', 'aZ09') ? GETPOST('duree', 'aZ09') : 'jour', 0).' <span class="opacitymedium small">'.$langs->trans('DemiJourneeAide').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('Justification').'</td><td><label class="marginrightonly"><input type="checkbox" name="justifiee" value="1"'.(GETPOST('justifiee', 'alpha') ? ' checked' : '').'> '.$langs->trans('Justifiee').'</label> ';
	print $form->selectarray('fk_motif', $motifs, GETPOSTINT('fk_motif'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr><td>'.$langs->trans('Remarque').'</td><td><input type="text" name="note" class="flat minwidth300" maxlength="250" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"> <a class="button button-cancel" href="'.dol_escape_htmltag($self).'">'.$langs->trans('Cancel').'</a></div>';
	print '</form><br>';
}

// Justification d'une absence choisie
if ($cangerer && $action === 'editjust' && $id > 0) {
	foreach ($lignes as $a) {
		if ((int) $a->rowid !== $id || !(int) $a->status) {
			continue;
		}
		print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="justifier"><input type="hidden" name="id" value="'.$id.'">';
		print '<table class="border centpercent tableforfield"><tr class="liste_titre"><td colspan="2">'.$langs->trans('JustifierAbsence').' — '.dol_escape_htmltag($a->ref.' '.ecole_label($a).' — '.personnel_absence_periode_label($a)).'</td></tr>';
		print '<tr><td class="titlefield">'.$langs->trans('Justification').'</td><td><label class="marginrightonly"><input type="checkbox" name="justifiee" value="1"'.((int) $a->justifiee ? ' checked' : '').'> '.$langs->trans('Justifiee').'</label> ';
		print $form->selectarray('fk_motif', EcoleEmployeMotif::choix($db, 'absence', (int) $a->fk_motif), (int) $a->fk_motif, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
		print '<tr><td>'.$langs->trans('Remarque').'</td><td><input type="text" name="note" class="flat minwidth300" maxlength="250" value="'.dol_escape_htmltag((string) $a->note).'"></td></tr>';
		print '</table><div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"> <a class="button button-cancel" href="'.dol_escape_htmltag($self).'">'.$langs->trans('Cancel').'</a></div></form>';
	}
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($langs->trans('AbsencesPersonnel'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($lignes), $total, 'fa-user-clock', 0, $buttons, '', $limit, 0, 0, 1);

$durees = array();
foreach (personnel_durees() as $k => $l) {
	$durees[$k] = $langs->trans($l);
}
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="f_employe" value="'.dol_escape_htmltag($f['employe']).'" placeholder="'.dol_escape_htmltag($langs->trans('MatriculeOuNom')).'"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_categorie', personnel_categories_choix(), $f['categorie'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre"><div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMin').'</span> <input type="date" class="flat maxwidth125" name="f_du" value="'.dol_escape_htmltag($f['du']).'"></div>';
print '<div class="nowraponall"><span class="opacitymedium small">'.$langs->trans('FiltreMax').'</span> <input type="date" class="flat maxwidth125" name="f_au" value="'.dol_escape_htmltag($f['au']).'"></div></td>';
print '<td class="liste_titre">'.$form->selectarray('f_duree', $durees, $f['duree'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_etat', array('oui' => $langs->trans('Justifiee'), 'non' => $langs->trans('NonJustifiee')), $f['etat'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="liste_titre">'.$form->selectarray('f_motif', EcoleEmployeMotif::choix($db, 'absence', $f['motif']), $f['motif'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('f_statut', array('annulee' => $langs->trans('Annulees'), 'toutes' => $langs->trans('Toutes')), $f['statut'], $langs->trans('Valides'), 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';

print '<tr class="liste_titre">';
foreach (array('Employe', 'CategoriesEmploye', 'Periode', 'Duree', 'NombreJours', 'Justification', 'Motif', 'Remarque', 'EnregistrePar', '') as $t) {
	print '<td class="liste_titre">'.($t !== '' ? $langs->trans($t) : '').'</td>';
}
print '</tr>';

foreach ($lignes as $a) {
	$annulee = !(int) $a->status;
	$st = $annulee ? ' style="text-decoration:line-through;opacity:0.6"' : '';
	print '<tr class="oddeven">';
	print '<td'.$st.'><a href="'.dol_buildpath('/personnel/employe/presence.php', 1).'?id='.((int) $a->fk_employe).'&mois='.substr($a->date_debut, 0, 7).'">'.img_picto('', 'fa-id-badge', 'class="pictofixedwidth"').dol_escape_htmltag($a->ref).'</a> '.dol_escape_htmltag(ecole_label($a)).'</td>';
	print '<td>'.personnel_categories_badges((string) $a->categories).'</td>';
	print '<td class="nowraponall"'.$st.'>'.dol_escape_htmltag(personnel_absence_periode_label($a)).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans(personnel_durees()[$a->duree])).'</td>';
	print '<td class="right">'.price2num(personnel_absence_nb_jours($a)).'</td>';
	print '<td>'.($annulee ? '<span class="badge badge-status5" title="'.dol_escape_htmltag((string) $a->motif_annulation).'">'.$langs->trans('Annulee').'</span>' : ((int) $a->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="badge badge-danger">'.$langs->trans('NonJustifiee').'</span>')).'</td>';
	print '<td>'.dol_escape_htmltag($a->motif_fr ? ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar)) : '').'</td>';
	print '<td>'.dol_escape_htmltag($annulee ? $langs->trans('MotifAnnulation').' : '.$a->motif_annulation : (string) $a->note).'</td>';
	print '<td class="small">'.dol_escape_htmltag(ecole_user_label($db, $a->fk_user_creat)).'<br><span class="opacitymedium">'.dol_print_date($db->jdate($a->date_creation), 'dayhour', 'tzuserrel').'</span></td>';
	print '<td class="center nowraponall">';
	if ($cangerer && !$annulee) {
		print '<a class="editfielda marginrightonly" href="'.dol_escape_htmltag($self.'&action=editjust&id='.((int) $a->rowid)).'" title="'.dol_escape_htmltag($langs->trans('JustifierAbsence')).'">'.img_edit($langs->trans('JustifierAbsence')).'</a>';
		print '<a href="'.dol_escape_htmltag($self.'&action=annuler&id='.((int) $a->rowid).'&token='.newToken()).'" title="'.dol_escape_htmltag($langs->trans('AnnulerAbsence')).'">'.img_picto($langs->trans('AnnulerAbsence'), 'fa-ban').'</a>';
	}
	print '</td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
print '</form>';
print '<span class="opacitymedium small">'.$langs->trans('AideAbsencesPersonnel').'</span>';

llxFooter();
$db->close();
