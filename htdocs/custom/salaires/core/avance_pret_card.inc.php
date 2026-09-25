<?php
/**
 * Fiche commune d'une avance sur salaire ou d'un prêt au personnel (incluse par avance/card.php et pret/card.php,
 * qui définissent $type = 'avance' | 'pret').
 *  - création : employé, date, montant, mode de paiement, référence, note ; prêt : nombre de mensualités, mensualité
 *    (calculée, modifiable), premier mois de retenue ; l'argent sort tout de suite du compte du mode de paiement ;
 *  - fiche : versement, retenues déjà faites sur les bulletins, reste, échéancier prévu (prêt), reçu à signer (PDF),
 *    annulation avec motif tant que rien n'a été retenu.
 *
 * Fichier : custom/salaires/core/avance_pret_card.inc.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_avance.class.php');
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'banks', 'bills', 'other'));

$pret = ($type === 'pret');
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('salaires', 'avance', 'lire')) {
	accessforbidden();
}
$candonner = $user->hasRight('salaires', 'avance', 'donner');
$canannuler = $user->hasRight('salaires', 'avance', 'annuler');
$canexport = $user->hasRight('salaires', 'bulletin', 'exporter');

$form = new Form($db);
$object = $pret ? new EcoleSalairePret($db) : new EcoleSalaireAvance($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
$titre = $langs->trans($pret ? 'PretPersonnel' : 'AvanceSurSalaire');

/*
 * Actions
 */
if ($action == 'add' && GETPOST('cancel', 'alpha')) {
	header('Location: '.dol_buildpath('/salaires/'.$type.'/list.php', 1));
	exit;
}
if ($action == 'add' && $candonner) {
	$ds = personnel_date_ok(GETPOST('date', 'alphanohtml'));
	$data = array(
		'fk_employe' => GETPOSTINT('fk_employe'),
		'date' => $ds !== '' ? personnel_date_ts($ds) : 0,
		'montant' => GETPOST('montant', 'alphanohtml'),
		'fk_mode' => GETPOSTINT('fk_mode'),
		'reference' => GETPOST('reference', 'alphanohtml'),
		'numero_compte' => GETPOST('numero_compte', 'alphanohtml'),
		'note' => GETPOST('note', 'alphanohtml'),
		'nb_echeances' => GETPOSTINT('nb_echeances'),
		'montant_echeance' => GETPOST('montant_echeance', 'alphanohtml'),
		'premier_mois' => GETPOST('premier_mois', 'alphanohtml'),
	);
	$res = $object->donner($user, $data);
	if ($res > 0) {
		setEventMessages($langs->trans($pret ? 'PretDonneOk' : 'AvanceDonneeOk', $object->ref), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$res);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'create';
}
if ($object->id > 0 && $action == 'confirm_annuler' && $confirm === 'yes' && $canannuler) {
	if ($object->annuler($user, GETPOST('motif', 'alphanohtml')) > 0) {
		setEventMessages($langs->trans('AnnulationOk', $object->ref), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$self);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $object->id > 0 ? $object->ref.' - '.$titre : $titre, '', '', 0, 0, '', '', '', 'mod-salaires page-'.$type);

// Création
if ($object->id <= 0) {
	if (!$candonner) {
		accessforbidden();
	}
	$fk_employe = GETPOSTINT('fk_employe');
	$auj = personnel_aujourdhui();
	$date = personnel_date_ok(GETPOST('date', 'alphanohtml'));
	$date = $date !== '' ? $date : personnel_aujourdhui();
	$modes = salaires_modes_paiement($db);
	$defmode = GETPOSTISSET('fk_mode') ? GETPOSTINT('fk_mode') : getDolGlobalInt('SALAIRES_MODE_DEFAUT');
	print load_fiche_titre($langs->trans($pret ? 'NouveauPret' : 'NouvelleAvance'), '', $object->picto);
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans($pret ? 'NouveauPretAide' : 'NouvelleAvanceAide').'</div>';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Employe').'</td><td>'.$form->selectarray('fk_employe', personnel_employes_choix($db), $fk_employe, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Date').'</td><td><input type="date" name="date" class="flat" max="'.$auj.'" value="'.dol_escape_htmltag($date).'" required></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Montant').'</td><td><input type="text" name="montant" id="montant" class="flat maxwidth150 right" value="'.dol_escape_htmltag(GETPOST('montant', 'alphanohtml')).'" required> '.dol_escape_htmltag($conf->currency).'</td></tr>';
	if ($pret) {
		$nb = GETPOSTINT('nb_echeances') > 0 ? GETPOSTINT('nb_echeances') : 6;
		print '<tr><td class="fieldrequired">'.$langs->trans('NbMensualites').'</td><td><input type="number" name="nb_echeances" id="nb_echeances" min="1" max="'.max(1, getDolGlobalInt('SALAIRES_PRET_ECHEANCES_MAX', 24)).'" class="flat maxwidth75" value="'.$nb.'"></td></tr>';
		print '<tr><td>'.$langs->trans('Mensualite').'</td><td><input type="text" name="montant_echeance" id="montant_echeance" class="flat maxwidth150 right" value="'.dol_escape_htmltag(GETPOST('montant_echeance', 'alphanohtml')).'"> '.dol_escape_htmltag($conf->currency);
		print ' <span class="opacitymedium">'.$langs->trans('MensualiteAide').'</span></td></tr>';
		$pm = GETPOST('premier_mois', 'alphanohtml');
		print '<tr><td class="fieldrequired">'.$langs->trans('PremierMoisRetenue').'</td><td><input type="month" name="premier_mois" class="flat" value="'.dol_escape_htmltag(preg_match('/^\d{4}-\d{2}$/', $pm) ? $pm : substr($date, 0, 7)).'" required>';
		print ' <span class="opacitymedium">'.$langs->trans('PremierMoisAide').'</span></td></tr>';
		print '<script>$(function(){ function m(){ var t=parseFloat(($("#montant").val()||"").replace(/\s/g,"").replace(",",".")), n=parseInt($("#nb_echeances").val()||"1"); if(t>0&&n>0){ $("#montant_echeance").attr("placeholder", Math.ceil(t/n)); } } $("#montant,#nb_echeances").on("input", m); m(); });</script>';
	}
	print '<tr><td class="fieldrequired">'.$langs->trans('ModePaiement').'</td><td>'.$form->selectarray('fk_mode', $modes, $defmode, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').' <span class="opacitymedium">'.$langs->trans('VersementAide').'</span></td></tr>';
	// Numéro du compte mobile (Bankily, Sedad...) : proposé depuis la fiche de l'employé choisi
	$numeros = array();
	$resql = $db->query("SELECT rowid, mobile_money FROM ".$db->prefix()."ecole_employe WHERE mobile_money IS NOT NULL AND mobile_money <> ''");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$numeros[(int) $o->rowid] = $o->mobile_money;
	}
	print '<tr><td>'.$langs->trans('NumeroCompte').'</td><td><input type="text" name="numero_compte" id="numero_compte" class="flat minwidth200" value="'.dol_escape_htmltag(GETPOSTISSET('numero_compte') ? GETPOST('numero_compte', 'alphanohtml') : (isset($numeros[$fk_employe]) ? $numeros[$fk_employe] : '')).'">';
	print ' <span class="opacitymedium">'.$langs->trans('NumeroCompteAide').'</span></td></tr>';
	print '<script>$(function(){ var n = '.json_encode($numeros).'; $("#fk_employe").on("change", function(){ $("#numero_compte").val(n[$(this).val()] || ""); }); });</script>';
	print salaires_js_numero($db, '#fk_mode', '#numero_compte');
	print '<tr><td>'.$langs->trans('ReferencePaiement').'</td><td><input type="text" name="reference" class="flat minwidth200" value="'.dol_escape_htmltag(GETPOST('reference', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Note').'</td><td><input type="text" name="note" class="flat minwidth300" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';
	print '</table>';
	print dol_get_fiche_end();
	// Rappel des règles de la configuration
	$regles = array();
	if (!$pret && (float) getDolGlobalString('SALAIRES_AVANCE_MAX_PCT', '0') > 0) {
		$regles[] = $langs->trans('RappelPlafondAvance', price2num(getDolGlobalString('SALAIRES_AVANCE_MAX_PCT')));
	}
	if ($pret) {
		$regles[] = $langs->trans('RappelMensualitesMax', max(1, getDolGlobalInt('SALAIRES_PRET_ECHEANCES_MAX', 24)));
		if (getDolGlobalString('SALAIRES_PRET_UN_SEUL', '1')) {
			$regles[] = $langs->trans('RappelUnSeulPret');
		}
		if ((float) getDolGlobalString('SALAIRES_PRET_MAX_PCT', '0') > 0) {
			$regles[] = $langs->trans('RappelPlafondMensualite', price2num(getDolGlobalString('SALAIRES_PRET_MAX_PCT')));
		}
	}
	if ($regles) {
		print '<div class="opacitymedium small marginbottomonly">'.implode(' — ', $regles).' <a href="'.dol_buildpath('/salaires/admin/setup.php', 1).'">'.$langs->trans('MenuConfigSalaires').'</a></div>';
	}
	print $form->buttonsSaveCancel($pret ? 'DonnerPret' : 'DonnerAvance');
	print '</form>';
	llxFooter();
	$db->close();
	exit;
}

// Fiche
if ($action == 'annuler' && $canannuler) {
	print $form->formconfirm($self, $langs->trans('Annuler').' '.$object->ref, $langs->trans('ConfirmAnnulerAvancePret', $object->ref), 'confirm_annuler',
		array(array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Motif'), 'morecss' => 'minwidth300')), 'no', 1, 0, 550);
}
$champDate = $object->champDate;
$head = array(array($self, $titre, 'card'));
print dol_get_fiche_head($head, 'card', $titre, -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/salaires/'.$type.'/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$reste = $object->reste();
$morehtmlref = '<div class="refidno">'.salaires_employe_lien($db, (int) $object->fk_employe).'<br>'.dol_print_date($object->$champDate, 'day').' — <b>'.salaires_montant($object->montant).'</b>';
$morehtmlref .= ' — '.$langs->trans('ResteARetenir').' : <b>'.salaires_montant($reste).'</b></div>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref, '', 0, '', ''); // l'état (en cours, retenu, annulé) est ajouté par Dolibarr

print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
if ($pret) {
	print '<tr><td class="titlefield">'.$langs->trans('Mensualites').'</td><td>'.((int) $object->nb_echeances).' × '.salaires_montant($object->montant_echeance).'</td></tr>';
	print '<tr><td>'.$langs->trans('PremierMoisRetenue').'</td><td>'.dol_escape_htmltag(personnel_mois_label((string) $object->premier_mois)).'</td></tr>';
}
print '<tr><td class="titlefield">'.$langs->trans('ModePaiement').'</td><td>'.dol_escape_htmltag(salaires_mode_numero_texte($db, (int) $object->fk_mode, (string) $object->numero_compte)).($object->reference_paiement ? ' ('.dol_escape_htmltag($object->reference_paiement).')' : '').'</td></tr>';
if ((int) $object->fk_bank_account > 0 && $user->hasRight('banque', 'lire')) {
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$acc = new Account($db);
	if ($acc->fetch((int) $object->fk_bank_account) > 0) {
		print '<tr><td>'.$langs->trans('CompteTresorerie').'</td><td>'.$acc->getNomUrl(1).'</td></tr>';
	}
}
if (!$pret && (int) $object->fk_salary > 0 && $user->hasRight('salaries', 'read')) {
	print '<tr><td>'.$langs->trans('SalaireDolibarr').'</td><td><a href="'.DOL_URL_ROOT.'/salaries/card.php?id='.((int) $object->fk_salary).'">'.img_picto('', 'salary', 'class="pictofixedwidth"').$langs->trans('VoirComptabilite').'</a></td></tr>';
}
if ($object->note) {
	print '<tr><td>'.$langs->trans('Note').'</td><td>'.dol_escape_htmltag($object->note).'</td></tr>';
}
print '<tr><td>'.$langs->trans('DonnePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_creat)).' <span class="opacitymedium">'.dol_print_date($object->date_creation, 'dayhour').'</span></td></tr>';
if ((int) $object->status === EcoleSalaireAvance::STATUS_ANNULE) {
	print '<tr><td class="error">'.$langs->trans('Annulation').'</td><td>'.dol_escape_htmltag((string) $object->motif_annulation).' <span class="opacitymedium">— '.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_annulation)).' '.dol_print_date($object->date_annulation, 'dayhour').'</span></td></tr>';
}
print '</table></div>';
print dol_get_fiche_end();

print '<div class="tabsAction">';
print dolGetButtonAction('', $langs->trans($pret ? 'ReconnaissancePdf' : 'RecuAvancePdf'), 'default', dol_buildpath('/salaires/'.$type.'/pdf.php', 1).'?id='.((int) $object->id), '', $canexport, array('attr' => array('target' => '_blank')));
if ((int) $object->status === EcoleSalaireAvance::STATUS_VERSE) {
	$retenu = $object->retenu();
	$brouillons = $object->brouillonsConcernes();
	$raison = $retenu > 0 ? $langs->trans('ErrorDejaRetenu') : ($brouillons ? $langs->trans('ErrorRetenueBrouillon', implode(', ', $brouillons)) : '');
	print ecole_bouton_supprimer($langs->trans('Annuler'), $self.'&action=annuler&token='.newToken(), $canannuler, $raison !== '' ? array($raison) : array());
}
print '</div>';

// Retenues sur les bulletins
$retenues = salaires_retenues_liste($db, $type, (int) $object->id);
print load_fiche_titre($langs->trans($pret ? 'RemboursementsBulletins' : 'RetenuesBulletins'), '', 'fa-money-check-alt');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('NumeroBulletin').'</td><td>'.$langs->trans('Periode').'</td><td class="right">'.$langs->trans('Montant').'</td><td class="right">'.$langs->trans('StatutBulletin').'</td></tr>';
$b = new EcoleSalaire($db);
foreach ($retenues as $r) {
	$b->id = (int) $r->rowid;
	$b->ref = $r->ref;
	$b->status = (int) $r->status;
	$annule = ((int) $r->status === EcoleSalaire::STATUS_ANNULE);
	$brouillon = ((int) $r->status === EcoleSalaire::STATUS_BROUILLON);
	print '<tr class="oddeven"'.($annule || $brouillon ? ' style="opacity:.6"' : '').'><td>'.$b->getNomUrl(1).($brouillon ? ' <span class="badge badge-warning small">'.$langs->trans('RetenuePrevue').'</span>' : '').'</td>';
	print '<td>'.($r->mois ? '<b>'.dol_escape_htmltag(personnel_mois_label($r->mois)).'</b> — ' : '').$langs->trans('PeriodeDuAu', dol_print_date($db->jdate($r->date_debut), 'day'), dol_print_date($db->jdate($r->date_fin), 'day')).'</td>';
	print '<td class="right nowraponall">'.salaires_montant($r->montant).'</td><td class="right">'.$b->getLibStatut(5).'</td></tr>';
}
if (empty($retenues)) {
	print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans($pret ? 'AucunRemboursement' : 'AucuneRetenue').'</span></td></tr>';
}
print '<tr class="liste_total"><td colspan="2" class="right">'.$langs->trans('DejaRetenuValides').'</td><td class="right nowraponall">'.salaires_montant($object->retenu()).'</td><td></td></tr>';
$prevu = $object->prevuBrouillon();
if ($prevu > 0) {
	print '<tr class="oddeven"><td colspan="2" class="right opacitymedium">'.$langs->trans('PrevuBrouillon').'</td><td class="right nowraponall opacitymedium">'.salaires_montant($prevu).'</td><td></td></tr>';
}
print '<tr class="liste_total"><td colspan="2" class="right">'.$langs->trans('ResteARetenir').'</td><td class="right nowraponall"><b>'.salaires_montant($reste).'</b></td><td></td></tr>';
print '</table></div>';
print '<div class="opacitymedium small margintoponly">'.$langs->trans($pret ? 'RetenuePretAide' : 'RetenueAvanceAide').'</div>';

// Échéancier prévu (prêt)
if ($pret && (int) $object->status === EcoleSalaireAvance::STATUS_VERSE && $reste > 0) {
	$ech = $object->echeancier();
	print '<br>'.load_fiche_titre($langs->trans('EcheancierPrevu'), '', 'fa-calendar-alt');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Mois').'</td><td class="right">'.$langs->trans('Mensualite').'</td></tr>';
	foreach ($ech['prevues'] as $e) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(personnel_mois_label($e['mois'])).'</td><td class="right nowraponall">'.salaires_montant($e['montant']).'</td></tr>';
	}
	print '</table></div>';
}

llxFooter();
$db->close();
