<?php
/**
 * Onglet « Absences et discipline » d'un élève : compteurs de l'année, absences et retards (justification),
 * sanctions (nouvelle sanction, annulation avec motif). Outil de suivi seulement.
 *
 * Fichier : custom/eleves/eleve/discipline.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
$canabs = $user->hasRight('eleves', 'absence', 'lire');
$cansan = $user->hasRight('eleves', 'sanction', 'lire');
if (!$user->hasRight('eleves', 'eleve', 'lire') || (!$canabs && !$cansan)) {
	accessforbidden();
}
$object = new EcoleEleve($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);

/*
 * Actions
 */
if (eleves_absence_actions($db, $user, $action)) {
	header('Location: '.$self);
	exit;
}
if ($action === 'addsanction' && GETPOST('cancel', 'alpha')) {
	header('Location: '.$self.'#sanctions');
	exit;
}
if ($action === 'addsanction') {
	if (!$user->hasRight('eleves', 'sanction', 'creer')) {
		accessforbidden();
	}
	$error = '';
	$info = '';
	$res = eleves_sanction_creer($db, $user, $object, GETPOSTINT('fk_type'), eleves_date_ok(GETPOST('date_sanction', 'alpha')), eleves_date_ok(GETPOST('date_fin', 'alpha')), GETPOST('motif', 'restricthtml'), $error, $info);
	if ($res > 0) {
		setEventMessages($langs->trans('SanctionEnregistree'), $info !== '' ? array($info) : null, 'mesgs');
		header('Location: '.$self.'#sanctions');
		exit;
	}
	setEventMessages($error, null, 'errors');
	$action = 'newsanction';
}
if ($action === 'confirm_annulersanction' && GETPOST('confirm', 'alpha') === 'yes') {
	if (!$user->hasRight('eleves', 'sanction', 'annuler')) {
		accessforbidden();
	}
	$error = '';
	if (eleves_sanction_annuler($db, $user, GETPOSTINT('sid'), GETPOST('motif_annulation', 'alphanohtml'), $error) > 0) {
		setEventMessages($langs->trans('SanctionAnnuleeMsg'), null, 'mesgs');
	} else {
		setEventMessages($error, null, 'errors');
	}
	header('Location: '.$self.'#sanctions');
	exit;
}

/*
 * Affichage
 */
llxHeader('', $object->ref.' - '.$langs->trans('AbsencesDiscipline'), '', '', 0, 0, '', '', '', 'mod-eleves page-discipline');

if ($action === 'annulersanction') {
	$q = array(array('type' => 'text', 'name' => 'motif_annulation', 'label' => $langs->trans('MotifAnnulation'), 'value' => '', 'morecss' => 'minwidth300'));
	print $form->formconfirm($self.'&sid='.GETPOSTINT('sid'), $langs->trans('AnnulerSanction'), $langs->trans('ConfirmAnnulerSanction'), 'confirm_annulersanction', $q, 'yes', 1, 200, 500);
}

eleve_print_banner($object, 'discipline');
print '<div class="underbanner clearboth"></div>';

// Compteurs de l'année
$c = eleves_compteurs_eleve($db, (int) $object->id);
$raisons = eleves_raisons_signalement($c);
print '<div class="fichecenter"><br>';
print '<table class="noborder centpercent"><tr class="liste_titre">';
if ($canabs) {
	print '<td class="center">'.$langs->trans('Absences').'</td><td class="center">'.$langs->trans('AbsencesNonJustifiees').'</td><td class="center">'.$langs->trans('DontRenvois').'</td><td class="center">'.$langs->trans('Retards').'</td><td class="center">'.$langs->trans('RetardsNonJustifies').'</td>';
}
if ($cansan) {
	print '<td class="center">'.$langs->trans('Sanctions').'</td>';
}
print '</tr><tr class="oddeven">';
if ($canabs) {
	print '<td class="center"><b>'.$c['absences'].'</b></td><td class="center"><b>'.$c['absences_nj'].'</b></td><td class="center"><b>'.$c['renvois'].'</b></td><td class="center"><b>'.$c['retards'].'</b></td><td class="center"><b>'.$c['retards_nj'].'</b></td>';
}
if ($cansan) {
	print '<td class="center"><b>'.$c['sanctions'].'</b></td>';
}
print '</tr></table>';
if ($canabs && !empty($raisons)) {
	print '<div class="warning">'.img_picto('', 'fa-flag', 'class="pictofixedwidth"').$langs->trans('EleveSignale').' : '.dol_escape_htmltag(implode(' ; ', $raisons)).'</div>';
}
print '<span class="opacitymedium small">'.$langs->trans('AideCompteursEleve').'</span>';
print '</div>';

print dol_get_fiche_end();

// Absences et retards
if ($canabs) {
	$f = array('du' => '', 'au' => '', 'classe' => 0, 'eleve' => '', 'type' => 0, 'etat' => '', 'creneau' => 0, 'fk_eleve' => (int) $object->id);
	$total = 0;
	$lignes = eleves_absences_liste($db, $f, 0, 0, $total);
	$motifs = EcoleMotifAbsence::choix($db);
	print '<br>';
	$lien = dolGetButtonTitle($langs->trans('VoirListe'), '', 'fa fa-list', dol_buildpath('/eleves/absence/list.php', 1).'?f_eleve='.urlencode($object->ref));
	print load_fiche_titre($langs->trans('AbsencesEtRetards').' ('.$total.')', $lien, 'fa-user-clock');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Creneau').'</td><td>'.$langs->trans('Matiere').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Presence').'</td><td>'.$langs->trans('Justification').'</td><td class="center">'.$langs->trans('WhatsApp').'</td></tr>';
	foreach ($lignes as $a) {
		$date = dol_print_date($db->jdate($a->date_appel), '%Y-%m-%d');
		print '<tr class="oddeven"><td class="nowraponall">'.dol_escape_htmltag(eleves_date_label($date)).'</td>';
		print '<td class="nowraponall">'.dol_escape_htmltag($a->crref).' <span class="opacitymedium small">'.$a->heure_debut.'</span></td>';
		print '<td>'.dol_escape_htmltag(eleves_absence_matiere($a)).'</td>';
		print '<td>'.dol_escape_htmltag($a->cref).'</td>';
		print '<td>'.eleves_presence_badge($a).'</td>';
		print '<td>'.eleves_justification_cell($a, $self, $motifs).'</td>';
		print '<td class="center">'.eleves_whatsapp_button(eleves_whatsapp_url($db, (int) $object->id, $date, (int) $a->type), false).'</td></tr>';
	}
	if (empty($lignes)) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AucuneAbsence').'</span></td></tr>';
	}
	print '</table></div>';
	eleves_justifier_forms_flush();
}

// Sanctions
if ($cansan) {
	$f = array('du' => '', 'au' => '', 'classe' => 0, 'eleve' => '', 'type' => 0, 'etat' => '', 'creneau' => 0, 'fk_eleve' => (int) $object->id);
	$total = 0;
	$sanctions = eleves_sanctions_liste($db, $f, 0, 0, $total);
	$cancreer = $user->hasRight('eleves', 'sanction', 'creer');
	$canannuler = $user->hasRight('eleves', 'sanction', 'annuler');
	print '<br><a id="sanctions"></a>';
	$bouton = dolGetButtonTitle($langs->trans('NouvelleSanction'), '', 'fa fa-plus-circle', $self.'&action=newsanction#sanctions', '', $cancreer);
	print load_fiche_titre($langs->trans('Sanctions').' ('.$total.')', $bouton, 'fa-gavel');

	if ($action === 'newsanction' && $cancreer) {
		$types = EcoleSanctionType::actifs($db);
		$choix = array();
		$temporaires = array();
		foreach ($types as $tid => $t) {
			$choix[$tid] = ecole_label($t);
			if ((int) $t->exclusion === EcoleSanctionType::EXCLUSION_TEMPORAIRE) {
				$temporaires[] = $tid;
			}
		}
		print '<form method="POST" action="'.dol_escape_htmltag($self).'#sanctions">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addsanction">';
		print '<table class="border centpercent tableforfield marginbottomonly">';
		print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('TypeSanction').'</td><td>'.$form->selectarray('fk_type', $choix, GETPOSTINT('fk_type'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('Date').'</td><td><input type="date" name="date_sanction" class="flat" max="'.eleves_aujourdhui().'" value="'.dol_escape_htmltag(eleves_date_ok(GETPOST('date_sanction', 'alpha')) ?: eleves_aujourdhui()).'"></td></tr>';
		print '<tr id="ligne_date_fin" style="display:none"><td class="fieldrequired">'.$langs->trans('ExclusionJusquau').'</td><td><input type="date" name="date_fin" class="flat" value="'.dol_escape_htmltag(eleves_date_ok(GETPOST('date_fin', 'alpha'))).'"> <span class="opacitymedium small">'.$langs->trans('AideExclusionTemporaire').'</span></td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('MotifSanction').'</td><td><textarea name="motif" class="flat quatrevingtpercent" rows="3">'.dol_escape_htmltag(GETPOST('motif', 'restricthtml')).'</textarea></td></tr>';
		print '</table>';
		print '<div class="center">'.$form->buttonsSaveCancel('Save', 'Cancel', array(), 0, '', '').'</div>';
		print '</form>';
		print '<script>
$(function () {
	var temp = '.json_encode(array_map('strval', $temporaires)).';
	function maj() { $("#ligne_date_fin").toggle(temp.indexOf(String($("#fk_type").val())) >= 0); }
	$("#fk_type").on("change", maj);
	maj();
	$("input[name=cancel]").on("click", function (e) { e.preventDefault(); window.location = "'.dol_escape_js($self, 2).'#sanctions"; });
});
</script><br>';
	}

	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('TypeSanction').'</td><td>'.$langs->trans('MotifSanction').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('EnregistrePar').'</td><td class="center"></td></tr>';
	foreach ($sanctions as $s) {
		$barre = (int) $s->status ? '' : ' style="text-decoration:line-through"';
		print '<tr class="oddeven"><td class="nowraponall"'.$barre.'>'.dol_escape_htmltag(eleves_sanction_periode($s, $db)).'</td>';
		print '<td'.$barre.'>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $s->type_fr, 'label_ar' => $s->type_ar))).'</td>';
		print '<td><span'.$barre.'>'.dol_nl2br(dol_escape_htmltag($s->motif)).'</span>';
		if (!(int) $s->status) {
			print '<br><span class="badge badge-status8">'.$langs->trans('SanctionAnnulee').'</span> <span class="opacitymedium small">'.dol_escape_htmltag($s->motif_annulation).' — '.dol_escape_htmltag(ecole_user_label($db, $s->fk_user_annul)).', '.dol_print_date($db->jdate($s->date_annulation), 'dayhour', 'tzuserrel').'</span>';
		}
		print '</td><td>'.dol_escape_htmltag($s->cref).'</td>';
		print '<td class="nowraponall">'.dol_escape_htmltag(ecole_user_label($db, $s->fk_user_creat)).'</td><td class="center">';
		if ((int) $s->status && $canannuler) {
			print '<a class="reposition" href="'.dol_escape_htmltag($self.'&action=annulersanction&sid='.((int) $s->rowid).'&token='.newToken()).'" title="'.dol_escape_htmltag($langs->trans('AnnulerSanction')).'">'.img_picto($langs->trans('AnnulerSanction'), 'fa-ban').'</a>';
		}
		print '</td></tr>';
	}
	if (empty($sanctions)) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('AucuneSanction').'</span></td></tr>';
	}
	print '</table></div>';
}

llxFooter();
$db->close();
