<?php
/**
 * Fichiers publics de l'espace servis sous des adresses neutres (aucun dossier de l'application visible) :
 *   logo                           logo de l'établissement
 *   assets/portail.css             styles de l'espace
 *   assets/emploi.css              styles de la grille de l'emploi du temps
 *   assets/fa/css/all.min.css      icônes
 *   assets/fa/webfonts/<fichier>   polices des icônes
 * Seuls ces fichiers sont servis (liste fermée).
 *
 * Fichier : custom/espace/portail/fichier.php
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', 1);
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', 1);
}
require __DIR__.'/boot.php';

$f = (string) GETPOST('fichier', 'alphanohtml');
$fichier = '';
if ($f === 'logo') {
	foreach (array('logos/thumbs/'.$mysoc->logo_small, 'logos/'.$mysoc->logo) as $rel) {
		if (basename($rel) !== '' && basename($rel) !== 'thumbs' && is_file($conf->mycompany->dir_output.'/'.$rel)) {
			$fichier = $conf->mycompany->dir_output.'/'.$rel;
			break;
		}
	}
} elseif ($f === 'assets/portail.css') {
	$fichier = dol_buildpath('/espace/css/portail.css', 0);
} elseif ($f === 'assets/emploi.css') {
	$fichier = dol_buildpath('/classes/css/timetable.css', 0);
} elseif ($f === 'assets/fa/css/all.min.css') {
	$fichier = DOL_DOCUMENT_ROOT.'/theme/common/fontawesome-5/css/all.min.css';
} elseif (preg_match('/^assets\/fa\/webfonts\/([a-z0-9\-]+\.(woff2|woff|ttf|eot|svg))$/', $f, $m)) {
	$fichier = DOL_DOCUMENT_ROOT.'/theme/common/fontawesome-5/webfonts/'.$m[1];
}

$types = array('css' => 'text/css', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
	'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');
$ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
if ($fichier === '' || !is_file($fichier) || !isset($types[$ext])) {
	http_response_code(404);
	exit;
}
header('Content-Type: '.$types[$ext]);
header('Cache-Control: public, max-age=86400');
header('Content-Length: '.filesize($fichier));
readfile($fichier);
$db->close();
