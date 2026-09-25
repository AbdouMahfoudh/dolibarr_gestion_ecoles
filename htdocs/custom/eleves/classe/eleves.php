<?php
/**
 * Onglet « Élèves » d'une classe : liste de la classe par numéro d'appel (N°, matricule, nom, sexe,
 * responsable, téléphone, statut). Bouton « Renuméroter par ordre alphabétique » et exports PDF / Excel.
 *
 * Fichier : custom/eleves/classe/eleves.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('classes', 'lire') || !$user->hasRight('eleves', 'eleve', 'lire')) {
	accessforbidden();
}
$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
$canmodif = $user->hasRight('eleves', 'eleve', 'modifier');

/*
 * Actions
 */
if ($action === 'confirm_renumeroter' && GETPOST('confirm', 'alpha') === 'yes') {
	if (!$canmodif) {
		accessforbidden();
	}
	$n = EcoleEleve::renumeroterClasse($db, (int) $object->id);
	if ($n >= 0) {
		setEventMessages($langs->trans('ClasseRenumerotee', $n), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: '.$self);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $object->ref.' - '.$langs->trans('ElevesDeLaClasse'), '', '', 0, 0, '', '', '', 'mod-eleves page-classe-eleves');

if ($action === 'renumeroter') {
	print $form->formconfirm($self, $langs->trans('RenumeroterClasse'), $langs->trans('ConfirmRenumeroterClasse'), 'confirm_renumeroter', '', 'yes', 1);
}

classe_print_header($object, 'eleves');
print '<div class="fichecenter"><br>';

$lignes = eleves_liste_classe($db, (int) $object->id);
$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons .= ecole_export_buttons('classe_eleves', '&id='.((int) $object->id), 'eleves');
}
$buttons .= dolGetButtonTitle($langs->trans('RenumeroterClasse'), $langs->trans('RenumeroterClasseAide'), 'fa fa-sort-numeric-down', $self.'&action=renumeroter&token='.newToken(), '', $canmodif && !empty($lignes));
print load_fiche_titre($langs->trans('ElevesDeLaClasse').' ('.count($lignes).')', $buttons, 'fa-user-graduate');

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="center">'.$langs->trans('NumeroAppelCourt').'</td><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomCompletFr').'</td><td>'.$langs->trans('NomCompletAr').'</td>';
print '<td class="center">'.$langs->trans('Sexe').'</td><td>'.$langs->trans('Responsable').'</td><td>'.$langs->trans('TelephoneResponsable').'</td><td class="center">'.$langs->trans('StatutEleve').'</td></tr>';
$st = new EcoleEleve($db);
foreach ($lignes as $e) {
	print '<tr class="oddeven"><td class="center"><b>'.($e->numero_appel ? (int) $e->numero_appel : '<span class="opacitymedium">—</span>').'</b></td>';
	print '<td class="nowraponall"><a href="'.dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $e->rowid).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($e->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag($e->nom_fr).'</td><td dir="rtl">'.dol_escape_htmltag((string) $e->nom_ar).'</td>';
	print '<td class="center">'.($e->sexe ? $langs->trans($e->sexe === 'F' ? 'SexeF' : 'SexeM') : '').'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label((object) array('nom_fr' => (string) $e->rnom_fr, 'nom_ar' => (string) $e->rnom_ar))).'</td>';
	print '<td class="nowraponall">'.dol_print_phone((string) $e->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone').'</td>';
	print '<td class="center">'.$st->LibStatut((int) $e->status, 5).'</td></tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunEleve').'</span></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideNumeroAppel').'</span>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
