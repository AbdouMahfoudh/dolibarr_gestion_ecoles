<?php
/**
 * Export PDF ou Excel d'une liste du module Élèves, dans la langue de l'utilisateur
 * (mêmes filtres et même tri que la liste affichée).
 *
 *   export.php?obj=<eleve|responsable|recu|frais_type|document_type>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=impayes&format=<pdf|excel>[&filtres de la page Impayés]
 *   export.php?obj=classe_paiements&id=<classe>&periode=<AAAA-MM>&format=<pdf|excel>
 *   export.php?obj=<absences|sanctions|signales>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=classe_discipline&id=<classe>&du=<AAAA-MM-JJ>&au=<AAAA-MM-JJ>&format=<pdf|excel>
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/eleves/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');

$obj = GETPOST('obj', 'aZ09');
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';

if ($obj === 'impayes') {
	if (!$user->hasRight('eleves', 'paiement', 'impayes') || !$user->hasRight('eleves', 'eleve', 'exporter')) {
		accessforbidden();
	}
} elseif ($obj === 'classe_paiements') {
	if (!$user->hasRight('classes', 'lire') || !$user->hasRight('eleves', 'paiement', 'lire') || !$user->hasRight('eleves', 'eleve', 'exporter')) {
		accessforbidden();
	}
} elseif ($obj === 'classe_eleves') {
	if (!$user->hasRight('classes', 'lire') || !$user->hasRight('eleves', 'eleve', 'lire') || !$user->hasRight('eleves', 'eleve', 'exporter')) {
		accessforbidden();
	}
} elseif (in_array($obj, array('absences', 'signales', 'classe_discipline', 'sanctions'), true)) {
	$droit = ($obj === 'sanctions') ? 'sanction' : 'absence';
	if (!$user->hasRight('eleves', $droit, 'lire') || !$user->hasRight('eleves', 'eleve', 'exporter') || ($obj === 'classe_discipline' && !$user->hasRight('classes', 'lire'))) {
		accessforbidden();
	}
} else {
	$cfg = eleves_crud_config($obj);
	if (empty($cfg) || !ecole_crud_can($cfg, 'perm_read') || !ecole_crud_can($cfg, 'perm_export')) {
		accessforbidden();
	}
}

// Langue d'abord : tous les libellés du document suivent la langue détectée
$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);
$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'companies'));

if ($obj === 'impayes') {
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	$ds = eleves_export_dataset_impayes($db);
} elseif ($obj === 'classe_paiements') {
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	dol_include_once('/classes/class/ecole_classe.class.php');
	$classe = new EcoleClasse($db);
	$periode = GETPOST('periode', 'alpha');
	if (GETPOSTINT('id') <= 0 || $classe->fetch(GETPOSTINT('id')) <= 0 || !in_array($periode, eleves_periodes(), true)) {
		accessforbidden();
	}
	$ds = eleves_export_dataset_classe_paiements($db, $classe, $periode);
} elseif (in_array($obj, array('absences', 'signales', 'sanctions'), true)) {
	dol_include_once('/eleves/core/lib/discipline.lib.php');
	$fn = 'eleves_export_dataset_'.$obj;
	$ds = $fn($db);
} elseif ($obj === 'classe_eleves') {
	dol_include_once('/classes/class/ecole_classe.class.php');
	$classe = new EcoleClasse($db);
	if (GETPOSTINT('id') <= 0 || $classe->fetch(GETPOSTINT('id')) <= 0) {
		accessforbidden();
	}
	$ds = eleves_export_dataset_classe_eleves($db, $classe);
} elseif ($obj === 'classe_discipline') {
	dol_include_once('/eleves/core/lib/discipline.lib.php');
	dol_include_once('/classes/class/ecole_classe.class.php');
	$classe = new EcoleClasse($db);
	$du = eleves_date_ok(GETPOST('du', 'alpha'));
	$au = eleves_date_ok(GETPOST('au', 'alpha'));
	if (GETPOSTINT('id') <= 0 || $classe->fetch(GETPOSTINT('id')) <= 0 || $du === '' || $au === '') {
		accessforbidden();
	}
	$ds = eleves_export_dataset_classe_discipline($db, $classe, $du, $au);
} else {
	dol_include_once('/eleves/class/ecole_'.$cfg['dir'].'.class.php');
	$ds = ecole_export_dataset_list(new $cfg['class']($db), $cfg);
}

if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}

$db->close();
