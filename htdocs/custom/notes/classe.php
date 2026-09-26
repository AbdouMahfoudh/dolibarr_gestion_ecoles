<?php
/**
 * Onglet « Notes et résultats » de la fiche classe : tableau des moyennes de la période
 * (même contenu que la page Résultats), impression des bulletins, conseil de classe.
 *
 * Fichier : custom/notes/classe.php
 */

require 'init.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/notes/core/lib/resultats.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}
$object = new EcoleClasse($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$periode = notes_periode_requete($db, (int) $object->id);

llxHeader('', $langs->trans('NotesResultats').' - '.$object->ref, '', '', 0, 0, '', '', '', 'mod-notes page-classe-notes');
classe_print_header($object, 'notes');
print '<div class="fichecenter"><br>';
print ecole_annee_selecteur(array('id', 'periode'));
print notes_periode_tabs($db, $_SERVER['PHP_SELF'].'?id='.((int) $object->id), $periode, (int) $object->id);
notes_resultats_html($db, $user, (object) array('rowid' => (int) $object->id, 'ref' => $object->ref, 'label_fr' => $object->label_fr, 'label_ar' => $object->label_ar), $periode);
print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
