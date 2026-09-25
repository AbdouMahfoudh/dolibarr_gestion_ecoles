<?php
/**
 * Impayés : élèves en retard de paiement (frais d'inscription restants et mensualités dont le jour limite
 * est passé), filtres par classe, statut et nom, exports PDF / Excel. Sans blocage ni pénalité.
 *
 * Fichier : custom/eleves/impayes.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'bills', 'other'));

if (!$user->hasRight('eleves', 'paiement', 'impayes')) {
	accessforbidden();
}
$form = new Form($db);
$imp = eleves_impayes($db);
$f = $imp['filtres'];
$self = $_SERVER['PHP_SELF'];
$canencaisser = $user->hasRight('eleves', 'paiement', 'encaisser');

llxHeader('', $langs->trans('Impayes'), '', '', 0, 0, '', '', '', 'mod-eleves page-impayes');

$buttons = '';
if ($user->hasRight('eleves', 'eleve', 'exporter')) {
	$buttons = ecole_export_buttons('impayes', $imp['param'], 'eleves');
}
print_barre_liste($langs->trans('Impayes'), 0, $self, $imp['param'], '', '', '', count($imp['lignes']), count($imp['lignes']), 'fa-exclamation-triangle', 0, $buttons, '', 0, 0, 0, 1);
print '<span class="opacitymedium">'.$langs->trans('ImpayesAide', min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10)))).'</span><br><br>';

print '<form method="GET" action="'.dol_escape_htmltag($self).'">';
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
// Filtres
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_nom" value="'.dol_escape_htmltag($f['nom']).'" placeholder="'.dol_escape_htmltag($langs->trans('NomOuMatricule')).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('search_fk_classe', eleves_classes_choix($db), $f['classe'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre">'.$form->selectarray('search_groupe', array('actifs' => $langs->trans('GroupeActifs'), 'sortis' => $langs->trans('GroupeSortis'), 'tous' => $langs->trans('GroupeTous')), $f['groupe'], 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('tri', array('classe' => $langs->trans('TriParClasse'), 'montant' => $langs->trans('TriParMontant')), $f['tri'], 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Responsable').'</td>';
print '<td>'.$langs->trans('Telephone').'</td><td>'.$langs->trans('MoisEnRetard').'</td><td class="right">'.$langs->trans('MontantImpaye').'</td><td></td></tr>';

foreach ($imp['lignes'] as $l) {
	$e = $l['eleve'];
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.$e->getNomUrl(1).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label($e)).' '.($e->status != EcoleEleve::STATUS_INSCRIT ? $e->getLibStatut(3) : '').'</td>';
	print '<td>'.($l['classe'] ? dol_escape_htmltag($l['classe']->ref) : '').'</td>';
	print '<td>'.($l['responsable'] ? '<a href="'.dol_buildpath('/eleves/responsable/card.php', 1).'?id='.((int) $e->fk_responsable).'">'.dol_escape_htmltag(ecole_label($l['responsable'])).'</a>' : '').'</td>';
	$tel = $l['responsable'] ? dol_print_phone($l['responsable']->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone') : '';
	if ($l['responsable'] && $l['responsable']->whatsapp) {
		$tel .= ' <a href="https://wa.me/'.preg_replace('/[^0-9]/', '', $l['responsable']->whatsapp).'" target="_blank" rel="noopener" title="WhatsApp">'.img_picto('WhatsApp', 'fa-whatsapp').'</a>';
	}
	print '<td class="nowraponall">'.$tel.'</td>';
	print '<td>'.dol_escape_htmltag(eleves_impayes_libelle($l['mois'])).'</td>';
	print '<td class="right nowraponall"><a href="'.dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $e->id).'"><span class="badge badge-danger">'.price($l['montant']).'</span></a></td>';
	print '<td class="right nowraponall">';
	if ($canencaisser) {
		print '<a class="butActionSmall" href="'.dol_buildpath('/eleves/caisse.php', 1).'?fk_responsable='.((int) $e->fk_responsable).'">'.$langs->trans('Encaisser').'</a>';
	}
	print '</td></tr>';
}
if (empty($imp['lignes'])) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunImpaye').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="6">'.$langs->trans('Total').' ('.count($imp['lignes']).')</td><td class="right">'.eleves_montant($imp['total']).'</td><td></td></tr>';
}
print '</table></div>';
print '</form>';

llxFooter();
$db->close();
