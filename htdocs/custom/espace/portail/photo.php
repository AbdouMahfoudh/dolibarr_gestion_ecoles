<?php
/**
 * Photo d'un élève dans l'espace : seulement pour un élève visible avec l'accès connecté
 * (pour un employé : élève de ses classes, ou de toute l'école pour un surveillant),
 * et, dans l'espace du personnel, la photo de l'employé connecté (photo/moi).
 *
 *   photo/<code de l'élève> | photo/moi
 *
 * Fichier : custom/espace/portail/photo.php
 */

require 'boot.php';

$acces = espace_exiger_session($db, true);

// Photo de l'employé connecté
if ($acces->type === ESPACE_EMPLOYE && isModEnabled('personnel') && GETPOST('jeton', 'alphanohtml') === 'moi') {
	$emp = espace_cible($db, ESPACE_EMPLOYE, (int) $acces->fk_cible);
	$racine = $conf->personnel->dir_output;
	$nom = $emp ? (string) $emp->photo : '';
	espace_photo_envoyer($racine, $emp && $nom !== '' ? $racine.'/'.$emp->getPhotoSubdir() : '', $nom);
	$db->close();
	exit;
}
$eleves = espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible);
if ($acces->type === ESPACE_EMPLOYE && isModEnabled('personnel')) {
	// Employé : élèves de ses classes (enseignant) ou de toute l'école (surveillant)
	dol_include_once('/personnel/core/lib/espace_personnel.lib.php');
	$emp = espace_cible($db, ESPACE_EMPLOYE, (int) $acces->fk_cible);
	$eid = espace_id_par_jeton('eleve', GETPOST('jeton', 'alphanohtml'), $emp ? pe_eleves_autorises($db, $emp) : array());
	$e = new EcoleEleve($db);
	if ($eid > 0 && $e->fetch($eid) > 0) {
		$eleves[$eid] = $e;
	}
}
$id = espace_id_par_jeton('eleve', GETPOST('jeton', 'alphanohtml'), array_keys($eleves));
if (!isset($eleves[$id]) || empty($eleves[$id]->photo)) {
	http_response_code(404);
	exit;
}
$e = $eleves[$id];
espace_photo_envoyer($conf->eleves->dir_output, $conf->eleves->dir_output.'/'.$e->getPhotoSubdir(), (string) $e->photo);
$db->close();

/**
 * Envoie une photo (vignette si elle existe), seulement si elle est bien dans le dossier du module ; sinon 404.
 *
 * @param  string $racine Dossier des documents du module
 * @param  string $dir    Dossier de la photo ('' = pas de photo)
 * @param  string $nom    Nom du fichier
 * @return void
 */
function espace_photo_envoyer($racine, $dir, $nom)
{
	$file = '';
	if ($dir !== '' && $nom !== '') {
		$thumb = preg_replace('/(\.[^.]+)$/', '_small$1', $nom);
		$file = realpath(is_file($dir.'/thumbs/'.$thumb) ? $dir.'/thumbs/'.$thumb : $dir.'/'.$nom);
	}
	$types = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
	$ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
	if (!$file || strpos($file, (string) realpath($racine)) !== 0 || !is_file($file) || !isset($types[$ext])) {
		http_response_code(404);
		return;
	}
	header('Content-Type: '.$types[$ext]);
	header('Cache-Control: private, max-age=3600');
	header('Content-Length: '.filesize($file));
	readfile($file);
}
