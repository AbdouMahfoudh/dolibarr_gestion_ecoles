<?php
/**
 * Réglages du module Personnel : format du matricule automatique des employés (préfixe, longueur du compteur,
 * premier numéro) avec aperçu, et rappel du fonctionnement du compte utilisateur automatique.
 * Cible de $this->config_page_url ("setup.php@personnel").
 *
 * Fichier : custom/personnel/admin/setup.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/personnel/class/ecole_employe.class.php');

$langs->loadLangs(array('admin', 'personnel@personnel', 'classes@classes'));

if (!$user->hasRight('personnel', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$prefixe = getDolGlobalString('PERSONNEL_MATRICULE_PREFIXE', 'P');
$longueur = getDolGlobalInt('PERSONNEL_MATRICULE_LONGUEUR', 5);
$debut = getDolGlobalInt('PERSONNEL_MATRICULE_DEBUT', 1);

/*
 * Actions
 */
if ($action == 'save') {
	$prefixe = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', GETPOST('prefixe', 'alphanohtml')));
	$longueur = GETPOSTINT('longueur');
	$debut = GETPOSTINT('debut');
	$errors = array();
	if ($prefixe === '') {
		$errors[] = $langs->trans('ErrorPrefixeObligatoire');
	}
	if ($longueur < 3 || $longueur > 8) {
		$errors[] = $langs->trans('ErrorMatriculeLongueur');
	}
	if ($debut < 1) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('MatriculeDebut'));
	}
	$total = dol_strlen($prefixe) + $longueur;
	if ($total > EcoleObject::REF_MAX) {
		$errors[] = $langs->trans('ErrorMatriculeTropLong', $total, EcoleObject::REF_MAX);
	}
	if ($debut >= pow(10, max(1, $longueur))) {
		$errors[] = $langs->trans('ErrorMatriculeDebutTropGrand');
	}
	if ($errors) {
		setEventMessages(null, $errors, 'errors');
	} else {
		dolibarr_set_const($db, 'PERSONNEL_MATRICULE_PREFIXE', $prefixe, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'PERSONNEL_MATRICULE_LONGUEUR', $longueur, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'PERSONNEL_MATRICULE_DEBUT', $debut, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}
if ($action == 'importer' && $user->hasRight('personnel', 'employe', 'creer')) {
	$n = EcoleEmploye::importerUtilisateurs($db, $user);
	setEventMessages($langs->trans('FichesImportees', $n), null, $n ? 'mesgs' : 'warnings');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MenuReglages'), '', '', 0, 0, '', '', '', 'mod-personnel page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationPersonnel'), $linkback, 'title_setup');
print dol_get_fiche_head(personnel_admin_prepare_head(), 'setup', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

$emp = new EcoleEmploye($db);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('FormatMatriculeEmploye').' <span class="opacitymedium small">— '.$langs->trans('FormatMatriculeEmployeAide').'</span></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('MatriculePrefixe').'</td><td><input type="text" name="prefixe" class="flat maxwidth100" maxlength="6" value="'.dol_escape_htmltag($prefixe).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MatriculeLongueur').'</td><td><input type="number" name="longueur" class="flat maxwidth75" min="3" max="8" value="'.((int) $longueur).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MatriculeDebut').'</td><td><input type="number" name="debut" class="flat maxwidth100" min="1" value="'.((int) $debut).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProchainMatricule').'</td><td><b>'.dol_escape_htmltag($emp->getNextMatricule()).'</b>';
print ' &nbsp; <span class="opacitymedium">'.$langs->trans('ExempleMatricules', EcoleEmploye::formatMatricule(1, $prefixe, $longueur), EcoleEmploye::formatMatricule(125, $prefixe, $longueur)).'</span></td></tr>';
print '</table>';
print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form><br>';

// Espace du personnel : ce que les employés peuvent modifier eux-mêmes (réglage général, exception sur la fiche)
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('BlocEspacePersonnel').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('ModifProfilEspace').'</td><td>'.ajax_constantonoff('PERSONNEL_ESPACE_MODIF_PROFIL');
print '<br><span class="opacitymedium">'.$langs->trans('ModifProfilGeneralAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('SaisieNotesEspace').'</td><td><span class="opacitymedium">'.$langs->trans('SaisieNotesReglageAide').'</span></td></tr>';
print '</table><br>';

// Compte utilisateur automatique
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('CompteAutomatique').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('Description').'</td><td>'.$langs->trans('CompteAutomatiqueAide').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('UtilisateursSansFiche').'</td><td>';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?action=importer&token='.newToken()).'">'.$langs->trans('ImporterUtilisateurs').'</a>';
print ' <span class="opacitymedium">'.$langs->trans('ImporterUtilisateursAide').'</span></td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
