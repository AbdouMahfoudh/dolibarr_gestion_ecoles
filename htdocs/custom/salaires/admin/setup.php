<?php
/**
 * Configuration du module Salaires : numérotation des bulletins, mode de paiement proposé, compte de trésorerie
 * utilisé pour chaque mode de paiement, contenu de la fiche de paie. Les règles de calcul (retenues, retards,
 * heures supplémentaires, remplacements) sont dans l'onglet « Règles de présence et de paie » (module Personnel).
 *
 * Fichier : custom/salaires/admin/setup.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');

$langs->loadLangs(array('admin', 'banks', 'bills', 'salaires@salaires', 'personnel@personnel', 'classes@classes'));

if (!$user->hasRight('salaires', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$modes = salaires_modes_paiement($db);

if ($action == 'save') {
	$prefixe = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', GETPOST('ref_prefixe', 'alphanohtml')));
	$longueur = GETPOSTINT('ref_longueur');
	if ($longueur < 3 || $longueur > 8 || dol_strlen($prefixe) + $longueur > EcoleObject::REF_MAX) {
		setEventMessages($langs->trans('ErrorNumerotationBulletin', EcoleObject::REF_MAX), null, 'errors');
	} else {
		dolibarr_set_const($db, 'SALAIRES_REF_PREFIXE', $prefixe, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SALAIRES_REF_LONGUEUR', $longueur, 'chaine', 0, '', $conf->entity);
		$def = GETPOSTINT('mode_defaut');
		dolibarr_set_const($db, 'SALAIRES_MODE_DEFAUT', isset($modes[$def]) ? $def : 0, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SALAIRES_PDF_SEANCES', GETPOST('pdf_seances', 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);
		// Avances et prêts
		dolibarr_set_const($db, 'SALAIRES_AVANCE_MAX_PCT', min(100, max(0, (float) price2num(GETPOST('avance_max_pct', 'alphanohtml')))), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SALAIRES_PRET_ECHEANCES_MAX', min(120, max(1, GETPOSTINT('pret_echeances_max'))), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SALAIRES_PRET_UN_SEUL', GETPOST('pret_un_seul', 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SALAIRES_PRET_MAX_PCT', min(100, max(0, (float) price2num(GETPOST('pret_max_pct', 'alphanohtml')))), 'chaine', 0, '', $conf->entity);
		foreach (array_keys($modes) as $mid) {
			dolibarr_set_const($db, 'SALAIRES_MODE_NUMERO_'.$mid, GETPOST('numero_'.$mid, 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);
			$acc = GETPOSTINT('compte_'.$mid);
			if ($acc > 0) {
				dolibarr_set_const($db, 'SALAIRES_COMPTE_MODE_'.$mid, $acc, 'chaine', 0, '', $conf->entity);
			} else {
				dolibarr_del_const($db, 'SALAIRES_COMPTE_MODE_'.$mid, $conf->entity);
			}
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('MenuConfigSalaires'), '', '', 0, 0, '', '', '', 'mod-salaires page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationSalaires'), $linkback, 'title_setup');
print dol_get_fiche_head(salaires_admin_prepare_head(), 'setup', '', -1, '');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';

// Bulletins
$b = new EcoleSalaire($db);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('BulletinsDePaie').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('NumerotationBulletin').'</td><td>'.$langs->trans('Prefixe').' <input type="text" name="ref_prefixe" class="flat maxwidth75" maxlength="4" value="'.dol_escape_htmltag(getDolGlobalString('SALAIRES_REF_PREFIXE', 'BP')).'"> ';
print $langs->trans('NombreChiffres').' <input type="number" name="ref_longueur" min="3" max="8" class="flat maxwidth75" value="'.min(8, max(3, getDolGlobalInt('SALAIRES_REF_LONGUEUR', 6))).'">';
print ' &nbsp; <span class="opacitymedium">'.$langs->trans('ProchainBulletin').' : <b>'.dol_escape_htmltag($b->getNextRef()).'</b></span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('FicheDePaie').'</td><td><label><input type="checkbox" name="pdf_seances" value="1"'.(getDolGlobalString('SALAIRES_PDF_SEANCES', '1') ? ' checked' : '').'> '.$langs->trans('PdfAvecSeances').'</label></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ReglesDeCalcul').'</td><td><a href="'.dol_buildpath('/personnel/admin/regles.php', 1).'">'.img_picto('', 'fa-balance-scale', 'class="pictofixedwidth"').$langs->trans('VoirReglesGenerales').'</a> <span class="opacitymedium">— '.$langs->trans('ReglesDeCalculAide').'</span></td></tr>';
print '</table><br>';

// Avances et prêts
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AvancesEtPrets').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('PlafondAvance').'</td><td><input type="text" name="avance_max_pct" class="flat maxwidth50 right" value="'.dol_escape_htmltag(price2num(getDolGlobalString('SALAIRES_AVANCE_MAX_PCT', '0'))).'"> % <span class="opacitymedium">'.$langs->trans('PlafondAvanceAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MensualitesMax').'</td><td><input type="number" name="pret_echeances_max" min="1" max="120" class="flat maxwidth75" value="'.max(1, getDolGlobalInt('SALAIRES_PRET_ECHEANCES_MAX', 24)).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PlafondMensualite').'</td><td><input type="text" name="pret_max_pct" class="flat maxwidth50 right" value="'.dol_escape_htmltag(price2num(getDolGlobalString('SALAIRES_PRET_MAX_PCT', '0'))).'"> % <span class="opacitymedium">'.$langs->trans('PlafondMensualiteAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('UnSeulPret').'</td><td><label><input type="checkbox" name="pret_un_seul" value="1"'.(getDolGlobalString('SALAIRES_PRET_UN_SEUL', '1') ? ' checked' : '').'> '.$langs->trans('UnSeulPretAide').'</label></td></tr>';
print '</table><br>';

// Paiement
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('ModePaiement').'</td><td>'.$langs->trans('CompteTresorerie').' <span class="opacitymedium small">— '.$langs->trans('CompteSalaireAide').'</span></td><td class="center">'.$langs->trans('ModeNumeroObligatoire').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('ModeProposeParDefaut').'</td><td colspan="2">'.$form->selectarray('mode_defaut', $modes, getDolGlobalInt('SALAIRES_MODE_DEFAUT'), 1).'</td></tr>';
if (!isModEnabled('banque')) {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('ModuleBanqueInactif').'</span></td></tr>';
} else {
	$comptes = array();
	$resql = $db->query("SELECT rowid, label, currency_code FROM ".$db->prefix()."bank_account WHERE clos = 0 AND entity IN (".getEntity('bank_account').") ORDER BY label");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$comptes[(int) $o->rowid] = $o->label.' ('.$o->currency_code.')';
	}
	foreach ($modes as $mid => $label) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($label).'</td><td>'.$form->selectarray('compte_'.$mid, $comptes, salaires_compte_mode($mid), $langs->trans('ModeNonUtilise'), 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td>';
		print '<td class="center"><input type="checkbox" name="numero_'.$mid.'" value="1"'.(salaires_mode_numero($db, $mid) ? ' checked' : '').'></td></tr>';
	}
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('ModeNumeroAide').'</span><br><a href="'.DOL_URL_ROOT.'/admin/dict.php?id=13">'.$langs->trans('GererModesPaiement').'</a> — <a href="'.DOL_URL_ROOT.'/compta/bank/list.php">'.$langs->trans('GererComptes').'</a></td></tr>';
}
print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
