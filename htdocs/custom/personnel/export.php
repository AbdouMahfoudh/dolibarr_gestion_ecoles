<?php
/**
 * Export PDF ou Excel d'une liste du module Personnel, dans la langue de l'utilisateur
 * (mêmes filtres et même tri que la liste affichée).
 *
 *   export.php?obj=<employe|motif|doc_type|jour_sans_cours>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=<cours|absences>&format=<pdf|excel>[&filtres de la liste]
 *   export.php?obj=heures&mois=<AAAA-MM>&format=<pdf|excel>[&filtres]
 *   export.php?obj=presence_employe&id=<employé>&mois=<AAAA-MM>&format=<pdf|excel>
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/personnel/export.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');

$obj = GETPOST('obj', 'aZ09');
$format = GETPOST('format', 'aZ09') === 'excel' ? 'excel' : 'pdf';

if (!$user->hasRight('personnel', 'employe', 'exporter')) {
	accessforbidden();
}
if (in_array($obj, array('cours', 'heures', 'presence_employe'), true)) {
	if (!$user->hasRight('personnel', 'presence', 'lire') && !($obj === 'presence_employe' && $user->hasRight('personnel', 'absence', 'lire'))) {
		accessforbidden();
	}
} elseif ($obj === 'absences') {
	if (!$user->hasRight('personnel', 'absence', 'lire')) {
		accessforbidden();
	}
} else {
	$cfg = personnel_crud_config($obj);
	if (empty($cfg) || !ecole_crud_can($cfg, 'perm_read')) {
		accessforbidden();
	}
}

// Langue d'abord : tous les libellés du document suivent la langue détectée
$lang = ecole_pdf_resolve_lang($langs);
ecole_pdf_use_lang($lang);
$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'companies'));

if (in_array($obj, array('cours', 'absences', 'heures', 'presence_employe'), true)) {
	dol_include_once('/personnel/core/lib/presence.lib.php');
	$mois = personnel_mois_ok(GETPOST('mois', 'alpha'));
	if ($mois === '') {
		$mois = dol_print_date(dol_now(), '%Y-%m', 'tzuserrel');
	}
	if ($obj === 'cours') {
		$ds = personnel_export_dataset_cours($db);
	} elseif ($obj === 'absences') {
		$ds = personnel_export_dataset_absences($db);
	} elseif ($obj === 'heures') {
		$ds = personnel_export_dataset_heures($db, $mois);
	} else {
		$emp = new EcoleEmploye($db);
		if (GETPOSTINT('id') <= 0 || $emp->fetch(GETPOSTINT('id')) <= 0) {
			accessforbidden();
		}
		$ds = personnel_export_dataset_presence_employe($db, $emp, $mois);
	}
} else {
	dol_include_once('/personnel/class/ecole_'.($obj === 'employe' ? 'employe' : ($obj === 'motif' ? 'employe_motif' : ($obj === 'doc_type' ? 'employe_doc_type' : 'jour_sans_cours'))).'.class.php');
	$ds = ecole_export_dataset_list(new $cfg['class']($db), $cfg);
}

if ($format === 'excel') {
	ecole_export_excel($ds, $lang);
} else {
	ecole_export_pdf($ds);
}

$db->close();
