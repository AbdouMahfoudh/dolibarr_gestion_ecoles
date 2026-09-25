<?php
/**
 * Onglet « Historique » d'un élève : classes successives (avec dates) et changements de statut.
 *
 * Fichier : custom/eleves/eleve/historique.php
 */

require '../init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('eleves', 'eleve', 'lire')) {
	accessforbidden();
}
$object = new EcoleEleve($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

llxHeader('', $object->ref.' - '.$langs->trans('Historique'), '', '', 0, 0, '', '', '', 'mod-eleves page-historique');

eleve_print_banner($object, 'historique');
print '<div class="underbanner clearboth"></div>';

// Classes
print load_fiche_titre($langs->trans('HistoriqueClasses'), '', 'fa-chalkboard');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td class="center">'.$langs->trans('DateDebut').'</td><td class="center">'.$langs->trans('DateFin').'</td>';
print '<td>'.$langs->trans('Motif').'</td><td>'.$langs->trans('EnregistrePar').'</td></tr>';
$lignes = $object->getHistoriqueClasses();
foreach ($lignes as $h) {
	print '<tr class="oddeven">';
	print '<td>'.ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $h->fk_classe).' <span class="opacitymedium">'.dol_escape_htmltag($h->ref).'</span></td>';
	print '<td class="center">'.dol_print_date($db->jdate($h->date_debut), 'day').'</td>';
	print '<td class="center">'.($h->date_fin ? dol_print_date($db->jdate($h->date_fin), 'day') : '<span class="badge badge-status4">'.$langs->trans('ClasseActuelle').'</span>').'</td>';
	print '<td>'.dol_escape_htmltag((string) $h->motif).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $h->fk_user)).' <span class="opacitymedium small">'.dol_print_date($db->jdate($h->date_creation), 'dayhour').'</span></td>';
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div><br>';

// Statuts
print load_fiche_titre($langs->trans('HistoriqueStatuts'), '', 'fa-history');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="center">'.$langs->trans('DateEffet').'</td><td>'.$langs->trans('AncienStatut').'</td><td>'.$langs->trans('NouveauStatut').'</td>';
print '<td>'.$langs->trans('Motif').'</td><td>'.$langs->trans('EnregistrePar').'</td></tr>';
$lignes = $object->getHistoriqueStatuts();
foreach ($lignes as $h) {
	print '<tr class="oddeven">';
	print '<td class="center">'.dol_print_date($db->jdate($h->date_statut), 'day').'</td>';
	print '<td>'.($h->status_old !== null ? $object->LibStatut((int) $h->status_old, 5) : '<span class="opacitymedium">'.$langs->trans('CreationDossier').'</span>').'</td>';
	print '<td>'.$object->LibStatut((int) $h->status_new, 5).'</td>';
	print '<td>'.dol_escape_htmltag((string) $h->motif).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $h->fk_user)).' <span class="opacitymedium small">'.dol_print_date($db->jdate($h->date_creation), 'dayhour').'</span></td>';
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
