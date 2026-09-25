<?php
/**
 * Configuration « Absences et discipline » : seuils d'alerte (absences / retards non justifiés),
 * indicatif téléphonique pour WhatsApp, messages envoyés aux parents.
 *
 * Fichier : custom/eleves/admin/discipline.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('admin', 'eleves@eleves', 'classes@classes'));

if (!$user->hasRight('eleves', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */
if ($action == 'save') {
	$sa = GETPOSTINT('seuil_absences');
	$sr = GETPOSTINT('seuil_retards');
	$ind = preg_replace('/[^0-9]/', '', GETPOST('indicatif', 'alphanohtml'));
	if ($sa < 0 || $sa > 999 || $sr < 0 || $sr > 999) {
		setEventMessages($langs->trans('ErrorSeuils'), null, 'errors');
	} else {
		dolibarr_set_const($db, 'ELEVES_SEUIL_ABSENCES', $sa, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_SEUIL_RETARDS', $sr, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'ELEVES_WHATSAPP_INDICATIF', $ind, 'chaine', 0, '', $conf->entity);
		foreach (array('ELEVES_MSG_ABSENCE' => 'msg_absence', 'ELEVES_MSG_RETARD' => 'msg_retard', 'ELEVES_MSG_RENVOI' => 'msg_renvoi') as $const => $field) {
			$msg = trim(GETPOST($field, 'restricthtml'));
			if ($msg === '') {
				dolibarr_del_const($db, $const, $conf->entity); // message par défaut
			} else {
				dolibarr_set_const($db, $const, $msg, 'chaine', 0, '', $conf->entity);
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
llxHeader('', $langs->trans('MenuConfigDiscipline'), '', '', 0, 0, '', '', '', 'mod-eleves page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationEleves'), $linkback, 'title_setup');
print dol_get_fiche_head(eleves_admin_prepare_head(), 'discipline', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

$s = eleves_seuils();
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SeuilsAlerte').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('SeuilAbsences').'</td><td><input type="number" name="seuil_absences" min="0" max="999" class="flat maxwidth75" value="'.$s['absences'].'"> <span class="opacitymedium">'.$langs->trans('SeuilAbsencesAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('SeuilRetards').'</td><td><input type="number" name="seuil_retards" min="0" max="999" class="flat maxwidth75" value="'.$s['retards'].'"> <span class="opacitymedium">'.$langs->trans('SeuilRetardsAide').'</span></td></tr>';
print '</table><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('MessagesWhatsApp').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('IndicatifPays').'</td><td>+<input type="text" name="indicatif" class="flat maxwidth75" maxlength="4" value="'.dol_escape_htmltag(getDolGlobalString('ELEVES_WHATSAPP_INDICATIF', '222')).'"> <span class="opacitymedium">'.$langs->trans('IndicatifPaysAide').'</span></td></tr>';
foreach (array('msg_absence' => array('ELEVES_MSG_ABSENCE', ELEVES_ABSENT, 'MessageAbsence'), 'msg_retard' => array('ELEVES_MSG_RETARD', ELEVES_RETARD, 'MessageRetard'), 'msg_renvoi' => array('ELEVES_MSG_RENVOI', ELEVES_RENVOYE, 'MessageRenvoi')) as $field => $d) {
	print '<tr class="oddeven"><td class="tdtop">'.$langs->trans($d[2]).'</td><td><textarea name="'.$field.'" class="flat quatrevingtpercent" rows="8" dir="auto">'.dol_escape_htmltag(getDolGlobalString($d[0], eleves_message_defaut($d[1]))).'</textarea></td></tr>';
}
print '<tr class="oddeven"><td></td><td><span class="opacitymedium">'.$langs->trans('AideMessagesWhatsApp').'</span></td></tr>';
print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
