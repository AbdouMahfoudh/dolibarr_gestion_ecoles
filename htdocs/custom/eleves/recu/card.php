<?php
/**
 * Fiche d'un reçu de paiement : payeur, mode, détail par élève, impression, annulation (avec motif).
 *
 * Fichier : custom/eleves/recu/card.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/class/ecole_recu.class.php');
dol_include_once('/eleves/class/ecole_responsable.class.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'bills', 'banks', 'other'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('eleves', 'paiement', 'lire')) {
	accessforbidden();
}
$canannuler = $user->hasRight('eleves', 'paiement', 'annuler');
$canrecu = $user->hasRight('eleves', 'paiement', 'recu');

$object = new EcoleRecu($db);
if (($id <= 0 && $ref === '') || $object->fetch($id, $ref !== '' ? $ref : null) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);

/*
 * Actions
 */
if ($action == 'confirm_annuler' && $confirm === 'yes' && $canannuler) {
	if ($object->annuler($user, GETPOST('motif', 'alphanohtml')) > 0) {
		setEventMessages($langs->trans('RecuAnnuleOk', $object->ref), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$self);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('Recu').' '.$object->ref, '', '', 0, 0, '', '', '', 'mod-eleves page-recu');

if ($action == 'annuler' && $canannuler) {
	print $form->formconfirm($self, $langs->trans('AnnulerRecu'), $langs->trans('ConfirmAnnulerRecu', $object->ref), 'confirm_annuler',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('MotifAnnulation'), 'morecss' => 'minwidth300')), 'no', 1, 0, 550);
}

$head = array(array($self, $langs->trans('Recu'), 'card'));
print dol_get_fiche_head($head, 'card', $langs->trans('Recu'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/eleves/recu/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">'.dol_print_date($object->date_recu, 'day').' — <b>'.eleves_montant($object->montant).'</b></div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', '');

print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
if ($object->fk_responsable > 0) {
	$resp = new EcoleResponsable($db);
	if ($resp->fetch((int) $object->fk_responsable) > 0) {
		print '<tr><td class="titlefield">'.$langs->trans('PayePar').'</td><td>'.$resp->getNomUrl(1, 'label').($resp->telephone ? ' — '.dol_print_phone($resp->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone') : '').'</td></tr>';
	}
}
print '<tr><td class="titlefield">'.$langs->trans('ModePaiement').'</td><td>'.dol_escape_htmltag(eleves_mode_label($db, $object->fk_mode)).'</td></tr>';
if ($object->reference_paiement) {
	print '<tr><td>'.$langs->trans('ReferencePaiement').'</td><td>'.dol_escape_htmltag($object->reference_paiement).'</td></tr>';
}
if ($object->fk_bank_account > 0 && $user->hasRight('banque', 'lire')) {
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$acc = new Account($db);
	if ($acc->fetch((int) $object->fk_bank_account) > 0) {
		print '<tr><td>'.$langs->trans('CompteTresorerie').'</td><td>'.$acc->getNomUrl(1).'</td></tr>';
	}
}
if ($object->note) {
	print '<tr><td>'.$langs->trans('Note').'</td><td>'.dol_escape_htmltag($object->note).'</td></tr>';
}
print '<tr><td>'.$langs->trans('EnregistrePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_creat)).' <span class="opacitymedium">'.dol_print_date($object->date_creation, 'dayhour').'</span></td></tr>';
if ((int) $object->status === EcoleRecu::STATUS_ANNULE) {
	print '<tr><td class="error">'.$langs->trans('RecuAnnule').'</td><td>'.dol_escape_htmltag((string) $object->motif_annulation).' <span class="opacitymedium">— '.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_annulation)).' '.dol_print_date($object->date_annulation, 'dayhour').'</span></td></tr>';
}
print '</table></div>';
print dol_get_fiche_end();

// Détail par élève
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Eleve').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Libelle').'</td><td class="right">'.$langs->trans('Montant').'</td></tr>';
$prev = 0;
$barre = ((int) $object->status === EcoleRecu::STATUS_ANNULE) ? ' style="text-decoration:line-through"' : '';
foreach ($object->lignes as $l) {
	print '<tr class="oddeven">';
	if ((int) $l->fk_eleve !== $prev) {
		print '<td><a href="'.dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $l->fk_eleve).'">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').dol_escape_htmltag($l->eleve_ref).'</a> '.dol_escape_htmltag(ecole_label($l)).'</td>';
		print '<td>'.ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $l->fk_classe).'</td>';
	} else {
		print '<td></td><td></td>';
	}
	$prev = (int) $l->fk_eleve;
	print '<td'.$barre.'>'.dol_escape_htmltag(eleves_ligne_libelle($l)).'</td><td class="right"'.$barre.'>'.price($l->montant).'</td></tr>';
}
print '<tr class="liste_total"><td colspan="3" class="right">'.$langs->trans('Total').'</td><td class="right"'.$barre.'>'.eleves_montant($object->montant).'</td></tr>';
print '</table></div>';

print '<div class="tabsAction">';
print dolGetButtonAction('', $langs->trans('ImprimerRecu'), 'default', dol_buildpath('/eleves/recu/pdf.php', 1).'?id='.((int) $object->id), '', $canrecu, array('attr' => array('target' => '_blank')));
if ($canrecu) {
	print ecole_pdf_modele_choix_html('recu', dol_buildpath('/eleves/recu/pdf.php', 1).'?id='.((int) $object->id));
}
if ((int) $object->status === EcoleRecu::STATUS_VALIDE) {
	print ecole_bouton_supprimer($langs->trans('AnnulerRecu'), $self.'&action=annuler&token='.newToken(), $canannuler, $object->getCancelBlockers());
}
print '</div>';

llxFooter();
$db->close();
