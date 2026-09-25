<?php
/**
 * Configuration « Suivi des élèves » : seuil des élèves en difficulté et message WhatsApp envoyé aux parents.
 *
 * Fichier : custom/notes/admin/suivi.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/notes/core/lib/resultats.lib.php');

$langs->loadLangs(array('admin', 'notes@notes', 'eleves@eleves'));

if (!$user->hasRight('notes', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');

if ($action === 'save') {
	$seuil = (float) price2num(GETPOST('seuil', 'alpha'));
	if ($seuil <= 0 || $seuil > 20) {
		setEventMessages($langs->trans('ErrorSeuil020'), null, 'errors');
	} else {
		dolibarr_set_const($db, 'NOTES_SEUIL_DIFFICULTE', notes_code($seuil, ''), 'chaine', 0, '', $conf->entity);
		$msg = trim(GETPOST('msg', 'restricthtml'));
		if ($msg === '') {
			dolibarr_del_const($db, 'NOTES_MSG_DIFFICULTE', $conf->entity); // message par défaut
		} else {
			dolibarr_set_const($db, 'NOTES_MSG_DIFFICULTE', $msg, 'chaine', 0, '', $conf->entity);
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

llxHeader('', $langs->trans('MenuConfigSuivi'), '', '', 0, 0, '', '', '', 'mod-notes page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationNotes'), $linkback, 'title_setup');
print dol_get_fiche_head(notes_admin_prepare_head(), 'suivi', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('ElevesEnDifficulte').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('SeuilDifficulte').'</td><td><input type="number" name="seuil" min="1" max="20" step="0.25" class="flat maxwidth75" value="'.dol_escape_htmltag(getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10')).'"> / 20 <span class="opacitymedium">'.$langs->trans('SeuilDifficulteAide').'</span></td></tr>';
print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('MessageDifficulte').'</td><td><textarea name="msg" class="flat quatrevingtpercent" rows="10" dir="auto">'.dol_escape_htmltag(getDolGlobalString('NOTES_MSG_DIFFICULTE', notes_msg_difficulte_defaut())).'</textarea></td></tr>';
print '<tr class="oddeven"><td></td><td><span class="opacitymedium">'.$langs->trans('AideMessageDifficulte').'</span></td></tr>';
print '</table>';
print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
