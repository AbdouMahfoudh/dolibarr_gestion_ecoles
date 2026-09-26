<?php
/**
 * Configuration des paiements : année scolaire, mois payants, jour limite, numérotation et format
 * des reçus, compte de trésorerie Dolibarr utilisé pour chaque mode de paiement.
 *
 * Fichier : custom/eleves/admin/paiements.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('admin', 'banks', 'bills', 'eleves@eleves', 'classes@classes'));

if (!$user->hasRight('eleves', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$modes = eleves_modes_paiement($db);
$formats = array('A5' => $langs->trans('FormatA5'), 'A4' => $langs->trans('FormatA4'), 'TICKET' => $langs->trans('FormatTicket'));

/*
 * Actions
 */
if ($action == 'save') {
	$errors = array();
	$annee = GETPOSTISSET('annee') ? GETPOSTINT('annee') : eleves_annee_scolaire();
	$mois = array();
	foreach (eleves_mois_ordre() as $m) {
		if (GETPOST('mois_'.$m, 'alpha')) {
			$mois[] = $m;
		}
	}
	$jour = GETPOSTINT('jour_limite');
	$prefixe = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', GETPOST('recu_prefixe', 'alphanohtml')));
	$longueur = GETPOSTINT('recu_longueur');
	$format = GETPOST('recu_format', 'aZ09');
	if ($annee < 2000 || $annee > 2100) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('AnneeScolaire'));
	}
	if (empty($mois)) {
		$errors[] = $langs->trans('ErrorAucunMoisPayant');
	}
	if ($jour < 1 || $jour > 28) {
		$errors[] = $langs->trans('ErrorJourLimite');
	}
	if ($longueur < 3 || $longueur > 8 || dol_strlen($prefixe) + $longueur > EcoleObject::REF_MAX) {
		$errors[] = $langs->trans('ErrorNumerotationRecu', EcoleObject::REF_MAX);
	}
	if (!isset($formats[$format])) {
		$format = 'A5';
	}
	if ($errors) {
		setEventMessages(null, $errors, 'errors');
	} else {
		dolibarr_set_const($db, 'ELEVES_ANNEE_SCOLAIRE', $annee, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_MOIS_PAYANTS', implode(',', $mois), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_JOUR_LIMITE', $jour, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_RECU_PREFIXE', $prefixe, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_RECU_LONGUEUR', $longueur, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_RECU_FORMAT', $format, 'chaine', 0, '', $conf->entity);
		foreach (array_keys($modes) as $mid) {
			$acc = GETPOSTINT('compte_'.$mid);
			if ($acc > 0) {
				dolibarr_set_const($db, 'ELEVES_COMPTE_MODE_'.$mid, $acc, 'chaine', 0, '', $conf->entity);
			} else {
				dolibarr_del_const($db, 'ELEVES_COMPTE_MODE_'.$mid, $conf->entity);
			}
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MenuConfigPaiements'), '', '', 0, 0, '', '', '', 'mod-eleves page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationEleves'), $linkback, 'title_setup');
print dol_get_fiche_head(eleves_admin_prepare_head(), 'paiements', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

// Année scolaire, mois payants, jour limite
$annee = eleves_annee_scolaire();
$annees = array();
for ($a = (int) date('Y') - 3; $a <= (int) date('Y') + 2; $a++) {
	$annees[$a] = eleves_annee_label($a);
}
$actifs = array_map('intval', explode(',', getDolGlobalString('ELEVES_MOIS_PAYANTS', '10,11,12,1,2,3,4,5,6')));
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('MensualitesEtImpayes').'</td></tr>';
// Après un premier passage d'année, l'année ne change plus qu'avec l'assistant de passage
$verrou = ecole_table_exists($db, 'ecole_eleve_annee') && ($r = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_eleve_annee")) && ($o = $db->fetch_object($r)) && (int) $o->nb > 0;
if ($verrou) {
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('AnneeScolaire').'</td><td><b>'.eleves_annee_label($annee).'</b> <span class="opacitymedium">'.$langs->trans('AnneeScolaireVerrouillee').'</span>';
	print ' <a href="'.dol_buildpath('/eleves/passage.php', 1).'">'.$langs->trans('MenuPassageAnnee').'</a></td></tr>';
} else {
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('AnneeScolaire').'</td><td>'.$form->selectarray('annee', $annees, GETPOSTISSET('annee') ? GETPOSTINT('annee') : $annee, 0).' <span class="opacitymedium">'.$langs->trans('AnneeScolaireAide').'</span></td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('MoisPayants').'</td><td>';
foreach (eleves_mois_ordre() as $m) {
	print '<label class="marginrightonly nowraponall"><input type="checkbox" name="mois_'.$m.'" value="1"'.(in_array($m, $actifs, true) ? ' checked' : '').'> '.$langs->trans('Month'.sprintf('%02d', $m)).'</label> ';
}
print '<br><span class="opacitymedium">'.$langs->trans('MoisPayantsAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('JourLimite').'</td><td><input type="number" name="jour_limite" min="1" max="28" class="flat maxwidth75" value="'.min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10))).'"> <span class="opacitymedium">'.$langs->trans('JourLimiteAide').'</span></td></tr>';
print '</table><br>';

// Reçus
dol_include_once('/eleves/class/ecole_recu.class.php');
$recu = new EcoleRecu($db);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Recus').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('NumerotationRecu').'</td><td>'.$langs->trans('MatriculePrefixe').' <input type="text" name="recu_prefixe" class="flat maxwidth75" maxlength="4" value="'.dol_escape_htmltag(getDolGlobalString('ELEVES_RECU_PREFIXE', 'R')).'"> ';
print $langs->trans('MatriculeLongueur').' <input type="number" name="recu_longueur" min="3" max="8" class="flat maxwidth75" value="'.min(8, max(3, getDolGlobalInt('ELEVES_RECU_LONGUEUR', 6))).'">';
print ' &nbsp; <span class="opacitymedium">'.$langs->trans('ProchainRecu').' : <b>'.dol_escape_htmltag($recu->getNextRef()).'</b></span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('FormatRecu').'</td><td>'.$form->selectarray('recu_format', $formats, getDolGlobalString('ELEVES_RECU_FORMAT', 'A5'), 0).'</td></tr>';
print '</table><br>';

// Comptes de trésorerie par mode de paiement
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('ModePaiement').'</td><td>'.$langs->trans('CompteTresorerie').' <span class="opacitymedium small">— '.$langs->trans('CompteTresorerieAide').'</span></td></tr>';
if (!isModEnabled('banque')) {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('ModuleBanqueInactif').'</span></td></tr>';
} else {
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$comptes = array();
	$resql = $db->query("SELECT rowid, ref, label, currency_code FROM ".$db->prefix()."bank_account WHERE clos = 0 AND entity IN (".getEntity('bank_account').") ORDER BY label");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$comptes[(int) $o->rowid] = $o->label.' ('.$o->currency_code.')';
	}
	foreach ($modes as $mid => $label) {
		print '<tr class="oddeven"><td class="titlefieldcreate">'.dol_escape_htmltag($label).'</td><td>'.$form->selectarray('compte_'.$mid, $comptes, eleves_compte_mode($mid), $langs->trans('ModeNonUtilise'), 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	}
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('ModesEtComptesAide').'</span> ';
	print '<a href="'.DOL_URL_ROOT.'/admin/dict.php?id=13">'.$langs->trans('GererModesPaiement').'</a> — <a href="'.DOL_URL_ROOT.'/compta/bank/list.php">'.$langs->trans('GererComptes').'</a></td></tr>';
}
print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
