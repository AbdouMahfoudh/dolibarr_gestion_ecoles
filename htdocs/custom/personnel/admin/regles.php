<?php
/**
 * Règles de présence et de paie (choisies par l'établissement) :
 *  - effet d'un retard de l'enseignant : aucun / minutes déduites / au-delà de X minutes le cours compte absent ;
 *  - paiement des remplacements : au prix de l'heure du remplaçant / montant fixe par séance / pas payé ;
 *  - retenue pour absence non justifiée des salariés au fixe : aucune / au prorata des séances ou des jours ;
 *  - heures supplémentaires des salariés au fixe : payées ou non ; suspension / congé : salaire maintenu ou réduit ;
 *  - heure qui sépare le matin de l'après-midi (absences d'une demi-journée).
 * Ces règles servent à l'estimation du mois et aux bulletins du module Salaires ; chaque employé peut avoir
 * sa propre règle (onglet « Salaires » de sa fiche).
 *
 * Fichier : custom/personnel/admin/regles.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'personnel@personnel', 'classes@classes'));

if (!$user->hasRight('personnel', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$regles = array(
	'PERSONNEL_RETARD_EFFET' => array('aucun', 'minutes', 'seuil'),
	'PERSONNEL_REMPLACEMENT_PAIE' => array('taux', 'forfait', 'aucun'),
	'PERSONNEL_RETENUE_ABSENCE' => array('aucune', 'prorata'),
	'PERSONNEL_HEURES_SUP' => array('oui', 'non'),
	'PERSONNEL_SUSPENSION_PAYEE' => array('oui', 'non'),
);

if ($action == 'save') {
	$errors = array();
	foreach ($regles as $c => $vals) {
		$v = GETPOST($c, 'aZ09');
		if (!in_array($v, $vals, true)) {
			$v = $vals[0];
		}
		dolibarr_set_const($db, $c, $v, 'chaine', 0, '', $conf->entity);
	}
	$seuil = GETPOSTINT('PERSONNEL_RETARD_SEUIL');
	if ($seuil < 1 || $seuil > 240) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('RetardSeuil'));
	} else {
		dolibarr_set_const($db, 'PERSONNEL_RETARD_SEUIL', $seuil, 'chaine', 0, '', $conf->entity);
	}
	$montant = price2num(GETPOST('PERSONNEL_REMPLACEMENT_MONTANT', 'alphanohtml'));
	if ($montant === '' || (float) $montant < 0) {
		$montant = 0;
	}
	dolibarr_set_const($db, 'PERSONNEL_REMPLACEMENT_MONTANT', $montant, 'chaine', 0, '', $conf->entity);
	$midi = GETPOST('PERSONNEL_HEURE_MIDI', 'alphanohtml');
	if (!ecole_hhmm_ok($midi)) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('HeureMidi'));
	} else {
		dolibarr_set_const($db, 'PERSONNEL_HEURE_MIDI', $midi, 'chaine', 0, '', $conf->entity);
	}
	if ($errors) {
		setEventMessages(null, $errors, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('MenuReglesPresence'), '', '', 0, 0, '', '', '', 'mod-personnel page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationPersonnel'), $linkback, 'title_setup');
print dol_get_fiche_head(personnel_admin_prepare_head(), 'regles', '', -1, '');

print '<span class="opacitymedium">'.$langs->trans('ReglesAide').'</span><br><br>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

/**
 * Boutons radio d'une règle.
 *
 * @param  string   $const Constante
 * @param  string[] $vals  Valeurs possibles
 * @param  string   $extra HTML ajouté après une valeur (valeur => html)
 * @return string
 */
function personnel_regle_radios($const, $vals, $extra = array())
{
	global $langs;
	$cur = getDolGlobalString($const, $vals[0]);
	$out = '';
	foreach ($vals as $v) {
		$out .= '<div class="margintoponlyshort"><label><input type="radio" name="'.$const.'" value="'.$v.'"'.($cur === $v ? ' checked' : '').'> '.$langs->trans($const.'_'.$v).'</label>';
		if (isset($extra[$v])) {
			$out .= ' '.$extra[$v];
		}
		$out .= '</div>';
	}
	return $out;
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.$langs->trans('Regle').'</td><td>'.$langs->trans('Choix').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RegleRetard').'<br><span class="opacitymedium small">'.$langs->trans('RegleRetardAide').'</span></td><td>';
print personnel_regle_radios('PERSONNEL_RETARD_EFFET', $regles['PERSONNEL_RETARD_EFFET'],
	array('seuil' => '<input type="number" name="PERSONNEL_RETARD_SEUIL" min="1" max="240" class="flat maxwidth75" value="'.getDolGlobalInt('PERSONNEL_RETARD_SEUIL', 15).'"> '.$langs->trans('Minutes')));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RegleRemplacement').'<br><span class="opacitymedium small">'.$langs->trans('RegleRemplacementAide').'</span></td><td>';
print personnel_regle_radios('PERSONNEL_REMPLACEMENT_PAIE', $regles['PERSONNEL_REMPLACEMENT_PAIE'],
	array('forfait' => '<input type="text" name="PERSONNEL_REMPLACEMENT_MONTANT" class="flat maxwidth100" value="'.dol_escape_htmltag(price2num(getDolGlobalString('PERSONNEL_REMPLACEMENT_MONTANT', '0'))).'"> '.$langs->trans('ParSeance')));
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RegleRetenue').'<br><span class="opacitymedium small">'.$langs->trans('RegleRetenueAide').'</span></td><td>';
print personnel_regle_radios('PERSONNEL_RETENUE_ABSENCE', $regles['PERSONNEL_RETENUE_ABSENCE']);
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RegleHeuresSup').'<br><span class="opacitymedium small">'.$langs->trans('RegleHeuresSupAide').'</span></td><td>';
print personnel_regle_radios('PERSONNEL_HEURES_SUP', $regles['PERSONNEL_HEURES_SUP']);
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RegleSuspension').'<br><span class="opacitymedium small">'.$langs->trans('RegleSuspensionAide').'</span></td><td>';
print personnel_regle_radios('PERSONNEL_SUSPENSION_PAYEE', $regles['PERSONNEL_SUSPENSION_PAYEE']);
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('HeureMidi').'<br><span class="opacitymedium small">'.$langs->trans('HeureMidiAide').'</span></td><td>';
print '<input type="time" name="PERSONNEL_HEURE_MIDI" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('PERSONNEL_HEURE_MIDI', '12:00')).'">';
print '</td></tr>';
print '</table>';
print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
