<?php
/**
 * Onglet « Paiements » d'un élève : situation (frais d'inscription, mensualités mois par mois),
 * réglages (premier mois dû, réduction, frais d'inscription particuliers), encaissement,
 * historique des paiements avec les reçus.
 *
 * Fichier : custom/eleves/eleve/paiements.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/class/ecole_recu.class.php');
dol_include_once('/eleves/class/ecole_motif_exoneration.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'bills', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

if (!$user->hasRight('eleves', 'eleve', 'lire') || !$user->hasRight('eleves', 'paiement', 'lire')) {
	accessforbidden();
}
$canencaisser = $user->hasRight('eleves', 'paiement', 'encaisser');
$canreduction = $user->hasRight('eleves', 'paiement', 'reduction');
$canrecu = $user->hasRight('eleves', 'paiement', 'recu');
$canexonerer = $user->hasRight('eleves', 'paiement', 'exonerer');

$object = new EcoleEleve($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);

/*
 * Actions
 */
if ($cancel) {
	header('Location: '.$self);
	exit;
}
if ($action == 'encaisser' && $canencaisser) {
	$recuid = eleves_encaissement_process($db, $user, array($object), 0);
	if ($recuid > 0) {
		setEventMessages($langs->trans('PaiementEnregistre'), null, 'mesgs');
		header('Location: '.dol_buildpath('/eleves/recu/card.php', 1).'?id='.$recuid);
		exit;
	}
	$action = 'encaissement';
}
if ($action == 'setreglages' && $canreduction) {
	$type = GETPOST('reduction_type', 'aZ09');
	$res = $object->setReglagesPaiement($user, GETPOST('mois_debut', 'alpha') === '-1' ? '' : (string) GETPOST('mois_debut', 'alpha'), $type === '-1' ? '' : (string) $type,
		GETPOST('reduction_valeur', 'alpha'), GETPOST('reduction_motif', 'alphanohtml'), GETPOST('frais_inscription_du', 'alpha'));
	if ($res > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'reglages';
}
if ($action == 'setexo' && $canexonerer) {
	$type = GETPOST('exo_type', 'aZ09');
	$res = $object->setExoneration($user, $type === '-1' ? '' : (string) $type, GETPOST('exo_valeur', 'alpha'), GETPOSTINT('fk_motif_exo'), GETPOST('exo_note', 'alphanohtml'));
	if ($res > 0) {
		setEventMessages($langs->trans('ExonerationEnregistree'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'exoneration';
}
if ($action == 'confirm_delexo' && GETPOST('confirm', 'alpha') == 'yes' && $canexonerer) {
	if ($object->setExoneration($user, '', null, 0) > 0) {
		setEventMessages($langs->trans('ExonerationRetiree'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

/*
 * Affichage
 */
llxHeader('', $object->ref.' - '.$langs->trans('Paiements'), '', '', 0, 0, '', '', '', 'mod-eleves page-paiements');

eleve_print_banner($object, 'paiements');
print '<div class="underbanner clearboth"></div>';

if ($action == 'encaissement' && $canencaisser) {
	print load_fiche_titre($langs->trans('EncaisserPour', ecole_label($object)), '', 'fa-cash-register');
	eleves_encaissement_form(array($object), $self, array('id' => $object->id));
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

$s = eleves_situation($db, $object);

print '<div class="fichecenter"><div class="fichehalfleft">';

// Mensualités mois par mois
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Libelle').'</td><td class="right">'.$langs->trans('MontantDu').'</td><td class="right">'.$langs->trans('DejaPaye').'</td><td class="right">'.$langs->trans('Reste').'</td><td class="center">'.$langs->trans('Etat').'</td></tr>';
foreach ($s['arrieres'] as $a) {
	print '<tr class="oddeven"><td>'.$langs->trans('ArrieresDe', $a['label']).'</td><td class="right">'.price($a['du']).'</td><td class="right">'.price($a['paye']).'</td><td class="right">'.price($a['reste']).'</td>';
	print '<td class="center">'.eleves_etat_badge($a['reste'] > 0 ? 'impaye' : 'paye', $a['reste']).'</td></tr>';
}
$ins = $s['inscription'];
print '<tr class="oddeven"><td>'.$langs->trans('FraisInscription').'</td><td class="right">'.price($ins['du']).'</td><td class="right">'.price($ins['paye']).'</td><td class="right">'.price($ins['reste']).'</td>';
print '<td class="center">'.eleves_etat_badge(eleves_etat_inscription($s), $ins['reste']).'</td></tr>';
foreach ($s['mois'] as $m) {
	print '<tr class="oddeven"><td>'.$langs->trans('MensualiteDe', $m['label']).($m['dans'] ? '' : ' <span class="opacitymedium">('.$langs->trans('HorsPeriode').')</span>').'</td>';
	print '<td class="right">'.price($m['du']).'</td><td class="right">'.price($m['paye']).'</td><td class="right">'.price($m['reste']).'</td>';
	print '<td class="center">'.eleves_etat_badge($m['etat'], $m['reste']).'</td></tr>';
}
print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.price($s['total_du']).'</td><td class="right">'.price($s['total_paye']).'</td><td class="right">'.price($s['reste']).'</td><td></td></tr>';
print '</table>';
print '<span class="opacitymedium small">'.$langs->trans('AideSituation', min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10))), eleves_annee_label(eleves_annee_scolaire())).'</span>';

print '</div><div class="fichehalfright">';

// Résumé
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-coins', 'class="pictofixedwidth"').$langs->trans('SituationFinanciere').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TotalPaye').'</td><td>'.eleves_montant($s['total_paye']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Impaye').'</td><td>'.($s['impaye'] > 0 ? '<span class="badge badge-danger">'.eleves_montant($s['impaye']).'</span>' : '<span class="badge badge-status4">'.$langs->trans('AJour').'</span>').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ResteAnnee').'</td><td>'.eleves_montant($s['reste']).'</td></tr>';
if (!$s['actif']) {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('PasEncoreInscritAide').'</span></td></tr>';
}
print '</table><br>';

// Réglages de paiement de l'élève
$periodes = eleves_periodes();
$choixMois = array();
foreach ($periodes as $per) {
	$choixMois[$per] = eleves_periode_label($per);
}
$editreglages = ($action == 'reglages' && $canreduction);
if ($editreglages) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setreglages">';
}
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-sliders-h', 'class="pictofixedwidth"').$langs->trans('ReglagesPaiement').'</td></tr>';
if ($editreglages) {
	$moisdef = eleves_premier_mois_payant($object->date_inscription ? eleves_periode_of($object->date_inscription) : '');
	$moisdef = ($moisdef !== '') ? eleves_periode_label($moisdef) : $langs->transnoentities('AucunMoisDu');
	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans('PremierMoisDu'), $langs->trans('PremierMoisDuAide')).'</td><td>';
	print $form->selectarray('mois_debut', $choixMois, GETPOSTISSET('mois_debut') ? GETPOST('mois_debut', 'alpha') : $object->mois_debut, $langs->trans('ParDefautMoisInscription', $moisdef), 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('TypeReduction').'</td><td>'.$form->selectarray('reduction_type', array('montant' => $langs->trans('ReductionMontant'), 'pourcent' => $langs->trans('ReductionPourcent')), GETPOSTISSET('reduction_type') ? GETPOST('reduction_type', 'aZ09') : $object->reduction_type, $langs->trans('AucuneReduction'), 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ValeurReduction').'</td><td><input type="text" class="flat maxwidth100 right" name="reduction_valeur" value="'.dol_escape_htmltag(GETPOSTISSET('reduction_valeur') ? GETPOST('reduction_valeur', 'alpha') : ($object->reduction_valeur !== null ? price2num($object->reduction_valeur) : '')).'"> <span class="opacitymedium">'.$langs->trans('ValeurReductionAide', $conf->currency).'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('MotifReduction').'</td><td><input type="text" class="flat minwidth300" name="reduction_motif" maxlength="255" value="'.dol_escape_htmltag(GETPOSTISSET('reduction_motif') ? GETPOST('reduction_motif', 'alphanohtml') : (string) $object->reduction_motif).'" placeholder="'.dol_escape_htmltag($langs->trans('MotifReductionAide')).'"></td></tr>';
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('FraisInscriptionDu'), $langs->trans('FraisInscriptionDuAide')).'</td><td><input type="text" class="flat maxwidth100 right" name="frais_inscription_du" value="'.dol_escape_htmltag(GETPOSTISSET('frais_inscription_du') ? GETPOST('frais_inscription_du', 'alpha') : ($object->frais_inscription_du !== null ? price2num($object->frais_inscription_du) : '')).'"> '.$conf->currency.'</td></tr>';
	print '<tr><td colspan="2" class="center">'.$form->buttonsSaveCancel('Save', 'Cancel', array(), 1).'</td></tr>';
} else {
	// Le premier mois dû est toujours un mois payant : inscrit en septembre, il commence en octobre (premier mois de la configuration)
	$premier = eleves_premier_mois_du($object);
	$moisdebut = ($premier !== '') ? eleves_periode_label($premier) : '<span class="opacitymedium">'.$langs->trans('AucunMoisDu').'</span>';
	if (empty($object->mois_debut) && $object->date_inscription) {
		$insc = eleves_periode_of($object->date_inscription);
		$moisdebut .= ' <span class="opacitymedium">('.($premier === $insc ? $langs->trans('MoisInscription') : $langs->trans('PremierMoisApresInscription', eleves_periode_label($insc))).')</span>';
	}
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PremierMoisDu').'</td><td>'.$moisdebut.'</td></tr>';
	$red = '<span class="opacitymedium">'.$langs->trans('AucuneReduction').'</span>';
	if ($object->reduction_type === 'pourcent') {
		$red = price2num($object->reduction_valeur).' % <span class="opacitymedium">— '.dol_escape_htmltag((string) $object->reduction_motif).'</span>';
	} elseif ($object->reduction_type === 'montant') {
		$red = $langs->trans('MoinsParMois', eleves_montant($object->reduction_valeur)).' <span class="opacitymedium">— '.dol_escape_htmltag((string) $object->reduction_motif).'</span>';
	}
	print '<tr class="oddeven"><td>'.$langs->trans('Reduction').'</td><td>'.$red.'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('FraisInscriptionDu').'</td><td>'.($object->frais_inscription_du !== null ? eleves_montant($object->frais_inscription_du) : '<span class="opacitymedium">'.$langs->trans('TarifDeLaClasse').'</span>').'</td></tr>';
}
print '</table>';
if ($editreglages) {
	print '</form>';
}

// Exonération des frais d'inscription (réinscription, bourse...)
if ($action == 'delexo' && $canexonerer) {
	print $form->formconfirm($self, $langs->trans('RetirerExoneration'), $langs->trans('ConfirmRetirerExoneration'), 'confirm_delexo', '', 0, 1);
}
$editexo = ($action == 'exoneration' && $canexonerer);
print '<br>';
if ($editexo) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setexo">';
}
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-hand-holding-heart', 'class="pictofixedwidth"').$langs->trans('ExonerationFraisInscription').'</td></tr>';
if ($editexo) {
	$exotype = GETPOSTISSET('exo_type') ? GETPOST('exo_type', 'aZ09') : ($object->exo_type ? $object->exo_type : 'pourcent');
	$exoval = GETPOSTISSET('exo_valeur') ? GETPOST('exo_valeur', 'alpha') : ($object->exo_valeur !== null && $object->exo_valeur !== '' ? price2num($object->exo_valeur) : '100');
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TypeExoneration').'</td><td>'.$form->selectarray('exo_type', array('pourcent' => $langs->trans('ReductionPourcent'), 'montant' => $langs->trans('ReductionMontant')), $exotype, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ValeurExoneration').'</td><td><input type="text" class="flat maxwidth100 right" name="exo_valeur" value="'.dol_escape_htmltag($exoval).'"> <span class="opacitymedium">'.$langs->trans('ValeurExonerationAide', $conf->currency).'</span></td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('MotifExoneration').'</td><td>'.$form->selectarray('fk_motif_exo', EcoleMotifExoneration::choix($db, (int) $object->fk_motif_exo), GETPOSTISSET('fk_motif_exo') ? GETPOSTINT('fk_motif_exo') : (int) $object->fk_motif_exo, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	if ($user->hasRight('eleves', 'config', 'gerer')) {
		print ' <a href="'.dol_buildpath('/eleves/motif_exoneration/list.php', 1).'" target="_blank" title="'.dol_escape_htmltag($langs->trans('MenuMotifsExoneration')).'">'.img_picto('', 'setup').'</a>';
	}
	print '</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('NoteExoneration').'</td><td><input type="text" class="flat minwidth300" name="exo_note" maxlength="255" value="'.dol_escape_htmltag(GETPOSTISSET('exo_note') ? GETPOST('exo_note', 'alphanohtml') : (string) $object->exo_note).'"></td></tr>';
	print '<tr><td colspan="2" class="center">'.$form->buttonsSaveCancel('Save', 'Cancel', array(), 1).'</td></tr>';
} elseif (empty($object->exo_type)) {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('AucuneExoneration').'</span></td></tr>';
} else {
	$exo = ($object->exo_type === 'pourcent') ? price2num($object->exo_valeur).' %' : eleves_montant($object->exo_valeur);
	if (!empty($ins['exonere'])) {
		$exo .= ' <span class="opacitymedium">('.$langs->trans('ExonereDe', eleves_montant($ins['exonere'])).')</span>';
	}
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Exoneration').'</td><td>'.$exo.'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('MotifExoneration').'</td><td>'.dol_escape_htmltag(eleves_motif_exo_label($db, $object->fk_motif_exo)).($object->exo_note ? ' <span class="opacitymedium">— '.dol_escape_htmltag((string) $object->exo_note).'</span>' : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ExonerePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->exo_fk_user)).($object->exo_date ? ' <span class="opacitymedium">'.dol_print_date($object->exo_date, 'dayhour').'</span>' : '').'</td></tr>';
}
print '</table>';
if ($editexo) {
	print '</form>';
}

print '</div></div><div class="clearboth"></div>';

// Historique des paiements
print '<br>'.load_fiche_titre($langs->trans('HistoriquePaiements'), '', 'fa-history');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('DatePaiement').'</td><td>'.$langs->trans('Libelle').'</td><td class="right">'.$langs->trans('Montant').'</td><td>'.$langs->trans('ModePaiement').'</td><td>'.$langs->trans('Recu').'</td><td>'.$langs->trans('EnregistrePar').'</td></tr>';
$sql = "SELECT l.type, l.periode, l.fk_frais_type, l.libelle, l.montant, l.status, r.rowid as recuid, r.ref, r.date_recu, r.fk_mode, r.reference_paiement, r.fk_user_creat, r.status as recu_status, r.motif_annulation";
$sql .= " FROM ".$db->prefix()."ecole_paiement l INNER JOIN ".$db->prefix()."ecole_recu r ON r.rowid = l.fk_recu";
$sql .= " WHERE l.fk_eleve = ".((int) $object->id)." ORDER BY r.date_recu DESC, r.rowid DESC, l.rowid";
$resql = $db->query($sql);
$nb = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	$nb++;
	$barre = ((int) $o->recu_status === EcoleRecu::STATUS_ANNULE) ? ' style="text-decoration:line-through" class="opacitymedium"' : '';
	print '<tr class="oddeven">';
	print '<td'.$barre.'>'.dol_print_date($db->jdate($o->date_recu), 'day').'</td>';
	print '<td><span'.$barre.'>'.dol_escape_htmltag(eleves_ligne_libelle($o)).'</span>'.($barre ? ' <span class="badge badge-status8" title="'.dol_escape_htmltag((string) $o->motif_annulation).'">'.$langs->trans('RecuAnnule').'</span>' : '').'</td>';
	print '<td class="right"'.$barre.'>'.price($o->montant).'</td>';
	print '<td'.$barre.'>'.dol_escape_htmltag(eleves_mode_label($db, $o->fk_mode)).($o->reference_paiement ? ' <span class="opacitymedium">'.dol_escape_htmltag($o->reference_paiement).'</span>' : '').'</td>';
	print '<td><a href="'.dol_buildpath('/eleves/recu/card.php', 1).'?id='.((int) $o->recuid).'"'.$barre.'>'.img_picto('', 'fa-receipt', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $o->fk_user_creat)).'</td>';
	print '</tr>';
}
if (!$nb) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('AucunPaiement').'</span></td></tr>';
}
print '</table></div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
print dolGetButtonAction('', $langs->trans('Encaisser'), 'default', $self.'&action=encaissement&token='.newToken(), '', $canencaisser);
print dolGetButtonAction('', $langs->trans('ModifierReglagesPaiement'), 'default', $self.'&action=reglages&token='.newToken(), '', $canreduction);
print dolGetButtonAction('', $langs->trans(empty($object->exo_type) ? 'Exonerer' : 'ModifierExoneration'), 'default', $self.'&action=exoneration&token='.newToken(), '', $canexonerer);
if (!empty($object->exo_type)) {
	print dolGetButtonAction('', $langs->trans('RetirerExoneration'), 'delete', $self.'&action=delexo&token='.newToken(), '', $canexonerer);
}
print dolGetButtonAction('', $langs->trans('AttestationSolde'), 'default', dol_buildpath('/eleves/eleve/attestation.php', 1).'?id='.((int) $object->id), '', $canrecu, array('attr' => array('target' => '_blank')));
print '</div>';

llxFooter();
$db->close();
