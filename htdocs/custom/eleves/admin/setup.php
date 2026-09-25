<?php
/**
 * Réglages du module Élèves : format du matricule automatique (préfixe, année, longueur du compteur,
 * premier numéro), avec aperçu. Cible de $this->config_page_url ("setup.php@eleves").
 *
 * Fichier : custom/eleves/admin/setup.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');

$langs->loadLangs(array('admin', 'eleves@eleves', 'classes@classes'));

if (!$user->hasRight('eleves', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$prefixe = getDolGlobalString('ELEVES_MATRICULE_PREFIXE');
$annee = getDolGlobalString('ELEVES_MATRICULE_ANNEE');
$longueur = getDolGlobalInt('ELEVES_MATRICULE_LONGUEUR', 5);
$debut = getDolGlobalInt('ELEVES_MATRICULE_DEBUT', 1);
$annees = array('' => $langs->trans('MatriculeSansAnnee'), 'AA' => $langs->trans('MatriculeAnneeAA'), 'AAAA' => $langs->trans('MatriculeAnneeAAAA'));

/*
 * Actions
 */
if ($action == 'save') {
	$prefixe = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', GETPOST('prefixe', 'alphanohtml')));
	$annee = GETPOST('annee', 'aZ09');
	$longueur = GETPOSTINT('longueur');
	$debut = GETPOSTINT('debut');
	$errors = array();
	if (!isset($annees[$annee])) {
		$annee = '';
	}
	if ($longueur < 3 || $longueur > 8) {
		$errors[] = $langs->trans('ErrorMatriculeLongueur');
	}
	if ($debut < 1) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('MatriculeDebut'));
	}
	$total = dol_strlen($prefixe) + dol_strlen($annee) + $longueur;
	if ($total > EcoleObject::REF_MAX) {
		$errors[] = $langs->trans('ErrorMatriculeTropLong', $total, EcoleObject::REF_MAX);
	}
	if ($debut >= pow(10, max(1, $longueur))) {
		$errors[] = $langs->trans('ErrorMatriculeDebutTropGrand');
	}
	if ($errors) {
		setEventMessages(null, $errors, 'errors');
	} else {
		dolibarr_set_const($db, 'ELEVES_MATRICULE_PREFIXE', $prefixe, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_MATRICULE_ANNEE', $annee, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_MATRICULE_LONGUEUR', $longueur, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_MATRICULE_DEBUT', $debut, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MenuReglages'), '', '', 0, 0, '', '', '', 'mod-eleves page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationEleves'), $linkback, 'title_setup');
print dol_get_fiche_head(eleves_admin_prepare_head(), 'setup', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

$eleve = new EcoleEleve($db);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('FormatMatricule').' <span class="opacitymedium small">— '.$langs->trans('FormatMatriculeAide').'</span></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('MatriculePrefixe').'</td><td><input type="text" name="prefixe" class="flat maxwidth100" maxlength="6" value="'.dol_escape_htmltag($prefixe).'"> <span class="opacitymedium">'.$langs->trans('MatriculePrefixeAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MatriculeAnnee').'</td><td>'.$form->selectarray('annee', $annees, $annee, 0).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MatriculeLongueur').'</td><td><input type="number" name="longueur" class="flat maxwidth75" min="3" max="8" value="'.((int) $longueur).'"> <span class="opacitymedium">'.$langs->trans('MatriculeLongueurAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MatriculeDebut').'</td><td><input type="number" name="debut" class="flat maxwidth100" min="1" value="'.((int) $debut).'"> <span class="opacitymedium">'.$langs->trans('MatriculeDebutAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProchainMatricule').'</td><td><b>'.dol_escape_htmltag($eleve->getNextMatricule(dol_now())).'</b>';
print ' &nbsp; <span class="opacitymedium">'.$langs->trans('ExempleMatricules', EcoleEleve::formatMatricule(1, $prefixe, $annee, $longueur, dol_now()), EcoleEleve::formatMatricule(125, $prefixe, $annee, $longueur, dol_now())).'</span></td></tr>';
print '</table>';
print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form><br>';

// Informations : Tiers et étiquette
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('TiersAutomatique').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('Description').'</td><td>'.$langs->trans('TiersAutomatiqueAide').'</td></tr>';
$catid = getDolGlobalInt('ELEVES_CATEGORIE_TIERS');
$cat = '';
if ($catid > 0 && isModEnabled('categorie')) {
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
	$c = new Categorie($db);
	if ($c->fetch($catid) > 0) {
		$cat = $c->getNomUrl(1);
	}
}
print '<tr class="oddeven"><td>'.$langs->trans('EtiquetteTiers').'</td><td>'.($cat ? $cat : '<span class="opacitymedium">'.$langs->trans('EtiquetteCreeAuPremierEleve').'</span>').'</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
