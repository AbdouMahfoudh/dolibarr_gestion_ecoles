<?php
/**
 * Onglet « Matières et coefficients » d'une classe : lier une matière à la classe avec
 * son coefficient et son barème (sur 20, ou sur coefficient x 20).
 *
 * Fichier : custom/classes/classe/matieres.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/classes/class/ecole_matiere.class.php');
dol_include_once('/classes/class/ecole_classe_matiere.class.php');

$langs->loadLangs(array('classes@classes', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$lineid = GETPOSTINT('lineid');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$canwrite = $user->hasRight('classes', 'ecrire');

$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.$id;

$baremes = array(
	EcoleClasseMatiere::BAREME_20 => $langs->trans('BaremeSur20'),
	EcoleClasseMatiere::BAREME_COEF20 => $langs->trans('BaremeSurCoef20'),
);

/*
 * Actions
 */
if ($canwrite) {
	if ($action == 'addmatiere') {
		$l = new EcoleClasseMatiere($db);
		$l->fk_classe = $id;
		$l->fk_matiere = GETPOSTINT('fk_matiere');
		$l->coefficient = GETPOST('coefficient', 'alphanohtml');
		$l->bareme = GETPOST('bareme', 'aZ09');
		$l->langue = GETPOST('langue', 'aZ09');
		if ($l->create($user) > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$self);
			exit;
		}
		setEventMessages(null, $l->errors, 'errors');
	}

	if ($action == 'savematiere' && $lineid > 0) {
		$l = new EcoleClasseMatiere($db);
		if ($l->fetch($lineid) > 0 && (int) $l->fk_classe === $id) {
			$l->coefficient = GETPOST('coefficient_'.$lineid, 'alphanohtml');
			$l->bareme = GETPOST('bareme_'.$lineid, 'aZ09');
			$l->langue = GETPOST('langue_'.$lineid, 'aZ09');
			if ($l->update($user) > 0) {
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				header('Location: '.$self);
				exit;
			}
			setEventMessages(null, $l->errors, 'errors');
		}
	}

	if ($action == 'confirm_deletematiere' && $confirm == 'yes' && $lineid > 0) {
		$l = new EcoleClasseMatiere($db);
		if ($l->fetch($lineid) > 0 && (int) $l->fk_classe === $id) {
			if ($l->delete($user) > 0) {
				setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
				header('Location: '.$self);
				exit;
			}
			setEventMessages(null, $l->errors, 'errors');
		}
		$action = '';
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MatieresCoefficients'), '', '', 0, 0, '', '', '', 'mod-classes page-matieres');

if ($action == 'deletematiere' && $lineid > 0) {
	print $form->formconfirm($self.'&lineid='.$lineid, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_deletematiere', '', 0, 1);
}

classe_print_header($object, 'matieres');

// Lignes existantes
$sql = "SELECT cm.rowid, cm.fk_matiere, cm.coefficient, cm.bareme, cm.note_max, cm.langue, m.langue as mlangue, m.ref, m.code, m.label_fr, m.label_ar, m.status";
$sql .= " FROM ".MAIN_DB_PREFIX."ecole_classe_matiere as cm";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."ecole_matiere as m ON m.rowid = cm.fk_matiere";
$sql .= " WHERE cm.fk_classe = ".((int) $id)." ORDER BY m.label_fr";
$resql = $db->query($sql);
$lines = array();
if ($resql) {
	while ($o = $db->fetch_object($resql)) {
		$lines[] = $o;
	}
}

print '<div class="fichecenter"><br>';
print '<div class="right" style="margin-bottom:6px">'.ecole_export_buttons('classe_matieres', '&id='.$id).'</div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Ref').'</td>';
print '<td>'.$langs->trans('Matiere').'</td>';
print '<td class="right">'.$langs->trans('Coefficient').'</td>';
print '<td>'.$langs->trans('Bareme').'</td>';
print '<td class="right">'.$langs->trans('NoteMax').'</td>';
print '<td>'.$langs->trans('LangueEnseignement').'</td>';
print '<td class="center"></td>';
print '</tr>';

$totcoef = 0;
$totmax = 0;
$cm = new EcoleClasseMatiere($db);
foreach ($lines as $l) {
	$totcoef += $l->coefficient;
	$totmax += $l->note_max;
	$fid = 'frm'.((int) $l->rowid);
	print '<tr class="oddeven">';
	print '<td><a href="'.dol_buildpath('/classes/matiere/card.php', 1).'?id='.((int) $l->fk_matiere).'">'.img_picto('', 'fa-book', 'class="pictofixedwidth"').dol_escape_htmltag($l->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($l)).'</td>';
	if ($canwrite) {
		print '<td class="right"><form id="'.$fid.'" method="POST" action="'.$self.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savematiere"><input type="hidden" name="lineid" value="'.((int) $l->rowid).'"></form>';
		print '<input form="'.$fid.'" type="text" class="flat maxwidth75 right" name="coefficient_'.((int) $l->rowid).'" value="'.dol_escape_htmltag(price2num($l->coefficient)).'"></td>';
		print '<td>'.$form->selectarray('bareme_'.((int) $l->rowid), $baremes, $l->bareme, 0, 0, 0, 'form="'.$fid.'"', 0, 0, 0, '', 'minwidth200').'</td>';
		print '<td class="right">'.price2num($l->note_max).'</td>';
		print '<td>'.$form->selectarray('langue_'.((int) $l->rowid), classe_matiere_langues($l->mlangue), (string) $l->langue, 0, 0, 0, 'form="'.$fid.'"', 0, 0, 0, '', 'minwidth150').'</td>';
		print '<td class="center nowraponall"><input form="'.$fid.'" type="submit" class="button smallpaddingimp" value="'.$langs->trans('Save').'"> ';
		$cm->fk_classe = $id;
		$cm->fk_matiere = (int) $l->fk_matiere;
		print ecole_icone_supprimer($self.'&lineid='.((int) $l->rowid).'&action=deletematiere&token='.newToken(), $cm->getDeleteBlockers()).'</td>';
	} else {
		print '<td class="right">'.price2num($l->coefficient).'</td>';
		print '<td>'.dol_escape_htmltag($baremes[$l->bareme] ?? $l->bareme).'</td>';
		print '<td class="right">'.price2num($l->note_max).'</td>';
		$lg = classe_matiere_langues($l->mlangue);
		print '<td>'.dol_escape_htmltag($lg[(string) $l->langue] ?? '').'</td>';
		print '<td></td>';
	}
	print '</tr>';
}
if (empty($lines)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AucuneMatiereLiee').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="2">'.$langs->trans('Total').'</td><td class="right">'.price2num($totcoef).'</td><td></td><td class="right">'.price2num($totmax).'</td><td></td><td></td></tr>';
}

// Ajout d'une matière
if ($canwrite) {
	$linked = array();
	foreach ($lines as $l) {
		$linked[] = (int) $l->fk_matiere;
	}
	$sql = "SELECT rowid, ref, label_fr, label_ar FROM ".MAIN_DB_PREFIX."ecole_matiere WHERE entity IN (".getEntity('ecole_matiere').") AND status = 1";
	if (!empty($linked)) {
		$sql .= " AND rowid NOT IN (".implode(',', $linked).")";
	}
	$sql .= " ORDER BY label_fr";
	$resql = $db->query($sql);
	$available = array();
	if ($resql) {
		while ($o = $db->fetch_object($resql)) {
			$available[(int) $o->rowid] = $o->ref.' - '.ecole_label($o);
		}
	}

	print '<tr class="liste_titre"><td colspan="7">'.$langs->trans('AjouterMatiere').'</td></tr>';
	print '<tr class="oddeven">';
	if (empty($available)) {
		print '<td colspan="7"><span class="opacitymedium">'.$langs->trans('AucuneMatiereDisponible').' <a href="'.dol_buildpath('/classes/matiere/card.php', 1).'?action=create">'.$langs->trans('NouvelleMatiere').'</a></span></td>';
	} else {
		print '<td colspan="2"><form id="frmadd" method="POST" action="'.$self.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addmatiere"></form>';
		print $form->selectarray('fk_matiere', $available, GETPOSTINT('fk_matiere'), 1, 0, 0, 'form="frmadd"', 0, 0, 0, '', 'minwidth300');
		print '</td>';
		print '<td class="right"><input form="frmadd" type="text" class="flat maxwidth75 right" name="coefficient" value="'.dol_escape_htmltag(GETPOST('coefficient', 'alphanohtml') !== '' ? GETPOST('coefficient', 'alphanohtml') : '1').'"></td>';
		print '<td>'.$form->selectarray('bareme', $baremes, (GETPOST('bareme', 'aZ09') !== '' ? GETPOST('bareme', 'aZ09') : $object->getBaremeDefaut()), 0, 0, 0, 'form="frmadd"', 0, 0, 0, '', 'minwidth200').'</td>';
		print '<td></td>';
		print '<td>'.$form->selectarray('langue', classe_matiere_langues(''), GETPOST('langue', 'aZ09'), 0, 0, 0, 'form="frmadd"', 0, 0, 0, '', 'minwidth150').'</td>';
		print '<td class="center"><input form="frmadd" type="submit" class="button" value="'.$langs->trans('Add').'"></td>';
	}
	print '</tr>';
}

print '</table>';
print '</div>';
print '<br><span class="opacitymedium">'.$langs->trans('BaremeAide').'</span>';
print '<br><span class="opacitymedium">'.$langs->trans('LangueClasseAide').'</span>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
