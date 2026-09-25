<?php
/**
 * Champs supplémentaires de la fiche employé (attributs complémentaires Dolibarr), ajoutés par l'établissement.
 * Ils s'affichent dans le bloc « Informations complémentaires » de la fiche employé.
 *
 * Fichier : custom/personnel/admin/employe_extrafields.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

$langs->loadLangs(array('admin', 'personnel@personnel', 'classes@classes'));

if (!$user->hasRight('personnel', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}

$extrafields = new ExtraFields($db);
$form = new Form($db);

// Types de champs proposés
$type2label = ExtraFields::getListOfTypesLabels();

$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$elementtype = 'ecole_employe'; // table_element de la classe EcoleEmploye

/*
 * Actions
 */
require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';

/*
 * Affichage
 */
$textobject = $langs->transnoentitiesnoconv('Employe');

llxHeader('', $langs->trans('MenuChampsSupp'), '', '', 0, 0, '', '', '', 'mod-personnel page-admin_extrafields');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationPersonnel'), $linkback, 'title_setup');
print dol_get_fiche_head(personnel_admin_prepare_head(), 'extrafields', '', -1, '');

print '<span class="opacitymedium">'.$langs->trans('ChampsSuppAideEmploye').'</span><br><br>';

require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';

print dol_get_fiche_end();

if ($action == 'create') {
	print '<br><div id="newattrib"></div>';
	print load_fiche_titre($langs->trans('NewAttribute'));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}

if ($action == 'edit' && !empty($attrname)) {
	print '<br>';
	print load_fiche_titre($langs->trans('FieldEdition', $attrname));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}

llxFooter();
$db->close();
