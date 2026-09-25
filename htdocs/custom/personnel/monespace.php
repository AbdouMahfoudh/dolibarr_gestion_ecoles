<?php
/**
 * « Mon espace » : ouvre l'espace du personnel depuis l'interface de gestion, sans nouveau mot de passe.
 * Pour la direction, le secrétariat, la comptabilité et les administrateurs qui ont une fiche employé :
 * l'accès est créé la première fois (leur mot de passe Dolibarr ne change pas), puis l'espace s'ouvre.
 *
 * Fichier : custom/personnel/monespace.php
 */

require 'init.php';

$langs->loadLangs(array('personnel@personnel', 'espace@espace'));

if (!isModEnabled('espace')) {
	accessforbidden($langs->trans('EspModuleEspaceInactif'));
}
dol_include_once('/espace/core/lib/espace.lib.php');
dol_include_once('/espace/core/lib/portail.lib.php');

$emp = personnel_employe_de($db, $user);
if (!$emp) {
	accessforbidden($langs->trans('ErrorPasDeFicheEmploye'));
}
$acces = espace_acces_fetch($db, ESPACE_EMPLOYE, (int) $emp->id);
if (!$acces) {
	if (!espace_employe_gestion($db, $emp)) {
		accessforbidden($langs->trans('EspDemanderAcces'));
	}
	$error = '';
	$id = espace_creer($db, $user, ESPACE_EMPLOYE, (int) $emp->id, $error);
	if ($id <= 0) {
		accessforbidden($error);
	}
	$acces = espace_acces_fetch_id($db, $id);
}
if (espace_etat($db, $acces) !== 'actif') {
	accessforbidden($langs->trans('AccesPlusActif'));
}
espace_session_ouvrir($db, $acces);
header('Location: '.espace_route_url('', array(), false, ESPACE_ZONE_PERSONNEL));
exit;
