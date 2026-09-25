<?php
/**
 * Export PDF ou Excel d'une liste du module, dans la langue de l'utilisateur.
 *
 *   export.php?obj=<niveau|section|creneau|session|matiere|classe|salle>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=classe_matieres&id=<classe>&format=<pdf|excel>
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/classes/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');

$obj = GETPOST('obj', 'aZ09');
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}

// Langue d'abord : tous les libellés du document suivent la langue détectée
$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);

if ($obj === 'classe_matieres') {
	dol_include_once('/classes/class/ecole_classe.class.php');
	$classe = new EcoleClasse($db);
	if (GETPOSTINT('id') <= 0 || $classe->fetch(GETPOSTINT('id')) <= 0) {
		accessforbidden();
	}
	$ds = ecole_export_dataset_classe_matieres($db, $classe);
} else {
	$cfg = ecole_crud_config($obj);
	if (empty($cfg) || !$user->hasRight('classes', $cfg['perm_read'])) {
		accessforbidden();
	}
	dol_include_once('/classes/class/ecole_'.$cfg['dir'].'.class.php');
	$ds = ecole_export_dataset_list(new $cfg['class']($db), $cfg);
}

if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}

$db->close();
