<?php
/**
 * Onglet « Paiements » d'une classe : on choisit un mois et on voit, parmi les élèves de la classe,
 * ceux qui ont payé, ceux qui ont payé en partie et ceux qui n'ont pas payé, avec le nom de l'élève,
 * son responsable et le téléphone du responsable. Exports PDF / Excel.
 *
 * Fichier : custom/eleves/classe/paiements.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'bills', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('classes', 'lire') || !$user->hasRight('eleves', 'eleve', 'lire') || !$user->hasRight('eleves', 'paiement', 'lire')) {
	accessforbidden();
}
$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'];

// Mois choisi : par défaut le mois en cours s'il est payant, sinon le dernier mois payant déjà commencé, sinon le premier
$periodes = eleves_periodes();
$periode = GETPOST('periode', 'alpha');
if (!in_array($periode, $periodes, true)) {
	$periode = '';
	$courant = eleves_periode_of(dol_now());
	foreach ($periodes as $per) {
		if ($per <= $courant) {
			$periode = $per;
		}
	}
	if ($periode === '' && !empty($periodes)) {
		$periode = $periodes[0];
	}
}

llxHeader('', $object->ref.' - '.$langs->trans('PaiementsClasse'), '', '', 0, 0, '', '', '', 'mod-eleves page-classe-paiements');

classe_print_header($object, 'paiements');
print '<div class="fichecenter"><br>';

if (empty($periodes)) {
	print '<div class="warning">'.$langs->trans('AucunMoisPayantConfigure').'</div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// Choix du mois
$choix = array();
foreach ($periodes as $per) {
	$choix[$per] = eleves_periode_label($per);
}
$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons = ecole_export_buttons('classe_paiements', '&id='.((int) $object->id).'&periode='.urlencode($periode), 'eleves');
}
print '<form method="GET" action="'.dol_escape_htmltag($self).'">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<div class="inline-block valignmiddle marginrightonly"><span class="opacitymedium">'.img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').$langs->trans('MoisChoisi').'</span> ';
print $form->selectarray('periode', $choix, $periode, 0, 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200');
print ' <noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'"></noscript></div>';
print '<div class="inline-block valignmiddle floatright">'.$buttons.'</div>';
print '</form><br>';

$data = eleves_classe_paiements($db, $object, $periode);
$t = $data['totaux'];
$nbtotal = count($data['groupes']['paye']) + count($data['groupes']['partiel']) + count($data['groupes']['impaye']);

// Résumé du mois
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('PaiementsClasseResumeMois', eleves_periode_label($periode), $nbtotal).'</div>';
print '<table class="noborder centpercent"><tr class="liste_titre">';
print '<td>'.$langs->trans('GroupePayes').'</td><td>'.$langs->trans('GroupePartiels').'</td><td>'.$langs->trans('GroupeNonPayes').'</td>';
print '<td class="right">'.$langs->trans('MontantDu').'</td><td class="right">'.$langs->trans('DejaPaye').'</td><td class="right">'.$langs->trans('Reste').'</td></tr>';
print '<tr class="oddeven"><td><span class="badge badge-status4">'.count($data['groupes']['paye']).'</span></td>';
print '<td><span class="badge badge-status1">'.count($data['groupes']['partiel']).'</span></td>';
print '<td><span class="badge badge-danger">'.count($data['groupes']['impaye']).'</span></td>';
print '<td class="right">'.eleves_montant($t['du']).'</td><td class="right">'.eleves_montant($t['paye']).'</td><td class="right">'.eleves_montant($t['reste']).'</td></tr>';
print '</table><br>';

// Trois listes : ont payé, ont payé en partie, n'ont pas payé
$sections = array(
	'paye' => array('GroupePayes', 'fa-check-circle'),
	'partiel' => array('GroupePartiels', 'fa-adjust'),
	'impaye' => array('GroupeNonPayes', 'fa-exclamation-circle'),
);
foreach ($sections as $g => $def) {
	$lignes = $data['groupes'][$g];
	print load_fiche_titre($langs->trans($def[0]).' ('.count($lignes).')', '', $def[1]);
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Responsable').'</td><td>'.$langs->trans('TelephoneResponsable').'</td>';
	print '<td class="right">'.$langs->trans('MontantDu').'</td><td class="right">'.$langs->trans('DejaPaye').'</td><td class="right">'.$langs->trans('Reste').'</td><td class="center">'.$langs->trans('Etat').'</td></tr>';
	foreach ($lignes as $l) {
		$e = $l['eleve'];
		$r = $l['responsable'];
		$m = $l['mois'];
		print '<tr class="oddeven"><td class="nowraponall">'.$e->getNomUrl(1).'</td>';
		print '<td><a href="'.dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $e->id).'">'.dol_escape_htmltag(ecole_label($e)).'</a></td>';
		print '<td>'.($r ? '<a href="'.dol_buildpath('/eleves/responsable/card.php', 1).'?id='.((int) $e->fk_responsable).'">'.dol_escape_htmltag(ecole_label($r)).'</a>' : '').'</td>';
		$tel = $r ? dol_print_phone($r->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone') : '';
		if ($r && $r->whatsapp) {
			$tel .= ' <a href="https://wa.me/'.preg_replace('/[^0-9]/', '', $r->whatsapp).'" target="_blank" rel="noopener" title="WhatsApp">'.img_picto('WhatsApp', 'fa-whatsapp').'</a>';
		}
		print '<td class="nowraponall">'.$tel.'</td>';
		print '<td class="right">'.price($m['du']).'</td><td class="right">'.price($m['paye']).'</td><td class="right">'.price($m['reste']).'</td>';
		print '<td class="center">'.eleves_etat_badge($m['etat'], $m['reste']).'</td></tr>';
	}
	if (empty($lignes)) {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunEleve').'</span></td></tr>';
	}
	print '</table></div><br>';
}

if ($data['non_concernes'] > 0) {
	print '<span class="opacitymedium">'.$langs->trans('NonConcernesCeMois', $data['non_concernes']).'</span>';
}
print '<span class="opacitymedium small">'.$langs->trans('AideElevesClassePaiements').'</span>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
