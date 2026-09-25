<?php
/**
 * Onglet « Historique » d'un employé : changements de statut (avec motif) et modifications sensibles
 * (paie, catégories, matières, compte relié). Les montants ne sont visibles qu'avec le droit « Paie : voir ».
 *
 * Fichier : custom/personnel/employe/historique.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_employe.class.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
if (!$user->hasRight('personnel', 'employe', 'lire')) {
	accessforbidden();
}
$object = new EcoleEmploye($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$canpaie = $user->hasRight('personnel', 'paie', 'lire');

llxHeader('', $object->ref.' - '.$langs->trans('Historique'), '', '', 0, 0, '', '', '', 'mod-personnel page-historique');

employe_print_banner($object, 'historique');
print '<div class="underbanner clearboth"></div>';

// Statuts
print load_fiche_titre($langs->trans('HistoriqueStatuts'), '', 'fa-history');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="center">'.$langs->trans('DateEffet').'</td><td>'.$langs->trans('AncienStatut').'</td><td>'.$langs->trans('NouveauStatut').'</td>';
print '<td>'.$langs->trans('Motif').'</td><td class="center">'.$langs->trans('FinPrevue').'</td><td>'.$langs->trans('EnregistrePar').'</td></tr>';
$lignes = $object->getHistoriqueStatuts();
foreach ($lignes as $h) {
	print '<tr class="oddeven">';
	print '<td class="center">'.dol_print_date($db->jdate($h->date_statut), 'day').'</td>';
	print '<td>'.($h->status_old !== null ? $object->LibStatut((int) $h->status_old, 5) : '<span class="opacitymedium">'.$langs->trans('CreationFiche').'</span>').'</td>';
	print '<td>'.$object->LibStatut((int) $h->status_new, 5).'</td>';
	print '<td>'.dol_escape_htmltag(trim(($h->fk_motif ? ecole_label($h) : '').($h->fk_motif && $h->motif ? ' — ' : '').(string) $h->motif)).'</td>';
	print '<td class="center">'.($h->date_fin_prevue ? dol_print_date($db->jdate($h->date_fin_prevue), 'day') : '').'</td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $h->fk_user)).' <span class="opacitymedium small">'.dol_print_date($db->jdate($h->date_creation), 'dayhour', 'tzuserrel').'</span></td>';
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div><br>';

// Modifications sensibles
$champs = array('mode_paie' => 'ModePaie', 'salaire_base' => 'SalaireBase', 'taux_horaire' => 'TauxHoraire', 'heures_semaine' => 'HeuresSemaine',
	'taux_heure_sup' => 'TauxHeureSup', 'categories' => 'CategoriesEmploye', 'matieres' => 'MatieresEnseignables', 'classes' => 'ClassesDeclarees', 'fk_user' => 'CompteUtilisateur',
	'saisie_notes' => 'SaisieNotesEspace', 'envoi_whatsapp' => 'EnvoiWhatsappEspace', 'modif_profil' => 'ModifProfilEspace', 'photo' => 'Photo', 'urgence_nom' => 'UrgenceNomEmploye',
	'urgence_telephone' => 'UrgenceTelephoneEmploye', 'urgence_lien' => 'UrgenceLienEmploye', 'diplome' => 'Diplome', 'specialite' => 'Specialite');
$paie = array('mode_paie', 'salaire_base', 'taux_horaire', 'heures_semaine', 'taux_heure_sup');
print load_fiche_titre($langs->trans('HistoriqueModifications'), '', 'fa-edit');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Champ').'</td><td>'.$langs->trans('Avant').'</td><td>'.$langs->trans('Apres').'</td><td>'.$langs->trans('EnregistrePar').'</td></tr>';
$nb = 0;
$valeur = function ($champ, $v) use ($object, $langs) {
	if ($champ === 'modif_profil' && ($v === null || $v === '')) {
		return '<span class="opacitymedium">'.$langs->trans('ReglageGeneral').'</span>';
	}
	if ($v === null || $v === '') {
		return '<span class="opacitymedium">—</span>';
	}
	if (in_array($champ, array('saisie_notes', 'envoi_whatsapp', 'modif_profil'), true)) {
		return $langs->trans((int) $v === 1 ? 'Autorisee' : 'Bloquee');
	}
	if ($champ === 'categories') {
		return personnel_categories_badges($v);
	}
	if ($champ === 'mode_paie') {
		return $object->showOutputField($object->fields['mode_paie'], 'mode_paie', $v);
	}
	if (in_array($champ, array('salaire_base', 'taux_horaire', 'taux_heure_sup'), true)) {
		return personnel_montant((float) $v);
	}
	return dol_escape_htmltag($v);
};
foreach ($object->getLogs() as $l) {
	if (in_array($l->champ, $paie, true) && !$canpaie) {
		continue;
	}
	$nb++;
	print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($l->date_creation), 'dayhour', 'tzuserrel').'</td>';
	print '<td>'.$langs->trans(isset($champs[$l->champ]) ? $champs[$l->champ] : $l->champ).'</td>';
	print '<td>'.$valeur($l->champ, $l->avant).'</td><td>'.$valeur($l->champ, $l->apres).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_user_label($db, $l->fk_user)).'</td></tr>';
}
if (!$nb) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
if ($canpaie) {
	print '<span class="opacitymedium small">'.$langs->trans('PaieModifAide').'</span>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
