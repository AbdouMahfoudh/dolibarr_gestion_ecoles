<?php
/**
 * Configuration de l'espace parents et élèves : langue par défaut, accès des élèves, adresse donnée aux
 * parents, messages WhatsApp envoyant l'accès (responsable, élève).
 *
 * Fichier : custom/espace/admin/setup.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'espace@espace', 'eleves@eleves'));

if (!$user->hasRight('espace', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');

if ($action === 'sethtaccess') {
	$msg = '';
	$ok = espace_htaccess_installer($db, $msg);
	setEventMessages($langs->trans($msg), null, $ok ? 'mesgs' : 'warnings');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action === 'unsethtaccess') {
	dolibarr_set_const($db, 'ESPACE_REECRITURE', '0', 'chaine', 0, '', $conf->entity);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'save') {
	$langue = GETPOST('langue', 'aZ09');
	dolibarr_set_const($db, 'ESPACE_LANGUE', in_array($langue, array('ar_SA', 'fr_FR'), true) ? $langue : 'ar_SA', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'ESPACE_ACCES_ELEVES', GETPOST('acces_eleves', 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	$url = rtrim(trim(GETPOST('url', 'alphanohtml')), '/');
	$urlChangee = ($url !== rtrim(getDolGlobalString('ESPACE_URL'), '/'));
	if ($url === '') {
		dolibarr_del_const($db, 'ESPACE_URL', $conf->entity);
	} else {
		dolibarr_set_const($db, 'ESPACE_URL', $url, 'chaine', 0, '', $conf->entity);
	}
	$urlPerso = rtrim(trim(GETPOST('url_personnel', 'alphanohtml')), '/');
	if (isModEnabled('personnel') && $urlPerso !== rtrim(getDolGlobalString('ESPACE_URL_PERSONNEL'), '/')) {
		$urlChangee = true;
		if ($urlPerso === '') {
			dolibarr_del_const($db, 'ESPACE_URL_PERSONNEL', $conf->entity);
		} else {
			dolibarr_set_const($db, 'ESPACE_URL_PERSONNEL', $urlPerso, 'chaine', 0, '', $conf->entity);
		}
		$conf->global->ESPACE_URL_PERSONNEL = $urlPerso;
	}
	foreach (espace_types() as $type) {
		$msg = trim(GETPOST('msg_'.$type, 'restricthtml'));
		if ($msg === '' || $msg === espace_message_defaut($type)) {
			dolibarr_del_const($db, espace_message_const($type), $conf->entity); // message par défaut
		} else {
			dolibarr_set_const($db, espace_message_const($type), $msg, 'chaine', 0, '', $conf->entity);
		}
	}
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	// Nouvelle adresse de l'espace : la règle est remise au bon endroit et vérifiée
	if ($urlChangee) {
		$conf->global->ESPACE_URL = $url;
		$msg = '';
		$ok = espace_htaccess_installer($db, $msg);
		setEventMessages($langs->trans($msg), null, $ok ? 'mesgs' : 'warnings');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('ConfigurationEspace'), '', '', 0, 0, '', '', '', 'mod-espace page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationEspace'), $linkback, 'title_setup');
print dol_get_fiche_head(espace_admin_prepare_head(), 'setup', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Reglages').'</td></tr>';

$langue = getDolGlobalString('ESPACE_LANGUE', 'ar_SA');
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('LangueParDefaut').'</td><td>';
foreach (array('ar_SA' => 'العربية', 'fr_FR' => 'Français') as $code => $lib) {
	print '<label class="marginrightonly"><input type="radio" name="langue" value="'.$code.'"'.($langue === $code ? ' checked' : '').'> '.$lib.'</label> ';
}
print '<br><span class="opacitymedium">'.$langs->trans('LangueParDefautAide').'</span></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AccesDesEleves').'</td><td><label><input type="checkbox" name="acces_eleves" value="1"'.(espace_acces_eleves_actif() ? ' checked' : '').'> '.$langs->trans('AccesDesElevesOui').'</label>';
print '<br><span class="opacitymedium">'.$langs->trans('AccesDesElevesAide').'</span></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AdresseEspace').'</td><td><input type="text" name="url" class="flat minwidth500" value="'.dol_escape_htmltag(getDolGlobalString('ESPACE_URL')).'" placeholder="'.dol_escape_htmltag(DOL_MAIN_URL_ROOT.'/espace').'">';
print '<br><span class="opacitymedium">'.$langs->trans('AdresseEspaceAide').'</span></td></tr>';
if (isModEnabled('personnel')) {
	print '<tr class="oddeven"><td>'.$langs->trans('AdresseEspacePersonnel').'</td><td><input type="text" name="url_personnel" class="flat minwidth500" value="'.dol_escape_htmltag(getDolGlobalString('ESPACE_URL_PERSONNEL')).'" placeholder="'.dol_escape_htmltag(espace_base_url(ESPACE_ZONE_PERSONNEL)).'">';
	print '<br><span class="opacitymedium">'.$langs->trans('AdresseEspacePersonnelAide').'</span></td></tr>';
}

foreach (espace_types() as $type) {
	print '<tr class="oddeven"><td class="tdtop">'.$langs->trans($type === ESPACE_PARENT ? 'MessageAccesParent' : ($type === ESPACE_EMPLOYE ? 'MessageAccesEmploye' : 'MessageAccesEleve')).'</td><td><textarea name="msg_'.$type.'" class="flat quatrevingtpercent" rows="12" dir="auto">'.dol_escape_htmltag(getDolGlobalString(espace_message_const($type), espace_message_defaut($type))).'</textarea></td></tr>';
}
print '<tr class="oddeven"><td></td><td><span class="opacitymedium">'.$langs->trans('AideMessageAcces').'</span></td></tr>';
print '</table>';
print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

// Adresses propres de l'espace (sans nom de fichier ni numéro d'élève)
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AdressesPropres').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('Etat').'</td><td>';
if (espace_reecriture()) {
	print '<span class="badge badge-status4">'.$langs->trans('AdressesPropresActives').'</span>';
	print ' &nbsp; <a href="'.dol_escape_htmltag(espace_url()).'" target="_blank" rel="noopener">'.dol_escape_htmltag(espace_url()).'</a>';
	if (isModEnabled('personnel')) {
		print ' &nbsp; <a href="'.dol_escape_htmltag(espace_url(ESPACE_EMPLOYE)).'" target="_blank" rel="noopener">'.dol_escape_htmltag(espace_url(ESPACE_EMPLOYE)).'</a>';
	}
	print ' &nbsp; <a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=unsethtaccess&token='.newToken().'">'.$langs->trans('AdressesPropresDesactiver').'</a>';
} else {
	print '<span class="badge badge-status8">'.$langs->trans('AdressesPropresInactives').'</span>';
}
print ' &nbsp; <a class="button smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?action=sethtaccess&token='.newToken().'">'.$langs->trans('AdressesPropresInstaller').'</a>';
print '<br><span class="opacitymedium">'.$langs->trans('AdressesPropresAide').'</span></td></tr>';
$cibles = espace_htaccess_cibles();
print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('RegleServeur').'</td><td>';
if (empty($cibles)) {
	print '<pre style="margin:4px 0;white-space:pre-wrap">'.dol_escape_htmltag(espace_htaccess_bloc()).'</pre>';
}
foreach ($cibles as $dir => $liste) {
	print '<span class="opacitymedium">'.$langs->trans('RegleEmplacement').'</span> <code>'.dol_escape_htmltag($dir.'/.htaccess').'</code>';
	print '<pre style="margin:4px 0;white-space:pre-wrap">'.dol_escape_htmltag(espace_htaccess_bloc($liste)).'</pre>';
}
print '<span class="opacitymedium">'.$langs->trans('RegleServeurAide').'</span></td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
