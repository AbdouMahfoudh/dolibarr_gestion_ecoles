<?php
/**
 * Impression des bulletins (trimestre 1 à 3) ou des relevés annuels (période 0), en PDF :
 * toute la classe (un élève par page) ou un seul élève.
 * Seulement après la clôture de la période (année : les trois trimestres clôturés).
 *
 *   bulletin.php?fk_classe=<id>&periode=<0..3>[&fk_eleve=<id>][&modele=<id>]
 *
 * Fichier : custom/notes/bulletin.php
 */

require 'init.php';
dol_include_once('/notes/core/lib/bulletin_pdf.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes'));

if (!$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}
$fk_classe = GETPOSTINT('fk_classe');
$periode = GETPOSTINT('periode');
if (!in_array($periode, array(0, 1, 2, 3), true)) {
	accessforbidden();
}
$resql = $db->query("SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_classe WHERE rowid = ".$fk_classe." AND entity IN (".getEntity('ecole_classe').")");
$classe = $resql ? $db->fetch_object($resql) : null;
if (!$classe) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$erreur = '';
if (!notes_periode_close($db, $fk_classe, $periode)) {
	$erreur = $langs->trans($periode === 0 ? 'BulletinsApresClotureAnnee' : 'BulletinsApresCloture');
} else {
	$erreur = notes_pdf_bulletins($db, $classe, $periode, GETPOSTINT('fk_eleve'), GETPOSTINT('modele'));
}
if ($erreur !== '') {
	llxHeader('', $langs->trans('BulletinDeNotes'));
	print '<div class="warning">'.dol_escape_htmltag($erreur).'</div>';
	print '<br><a href="'.dol_buildpath('/notes/resultats.php', 1).'?fk_classe='.$fk_classe.'&periode='.$periode.'">'.$langs->trans('VoirResultats').'</a>';
	llxFooter();
}
$db->close();
