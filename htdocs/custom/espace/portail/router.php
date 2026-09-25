<?php
/**
 * Aiguilleur de l'espace parents et élèves : toutes les adresses propres arrivent ici
 * (règle de réécriture « espace/... » du fichier .htaccess, ou du serveur en ligne) et sont
 * envoyées à la bonne page. Les adresses ne montrent ni nom de fichier ni numéro d'élève.
 *
 *   (accueil)                                  index.php
 *   connexion | deconnexion | mot-de-passe     login.php | logout.php | motdepasse.php
 *   eleve/<code>[/<onglet>[/<periode>]]        eleve.php   onglet : notes, emploi-du-temps, absences, paiements, dossier
 *                                                           période : t1, t2, t3, annee
 *   document/<code>/certificat                 pdf.php
 *   document/<code>/bulletin/<periode>         pdf.php
 *   document/<code>/recu/<code du reçu>        pdf.php
 *   photo/<code> | logo | assets/<fichier>     photo, logo de l'établissement, styles et icônes
 *
 * Espace du personnel : adresse séparée (…/personnel, entrée portail/personnel.php), pages du module Personnel
 * (dossier personnel/portail). Chaque zone n'ouvre que ses pages : …/espace/cours ou …/personnel/eleve/… n'existent pas.
 *   cours[/<AAAA-MM-JJ>[/<code du cours>]]     cours du jour, déclaration, élèves renvoyés
 *   emploi-du-temps | examens | mes-appels | absences | profil
 *   notes[/<classe>/<matière>[/t1|t2|t3[/<évaluation>]]]   saisie des notes
 *   classes[/<classe>]                         élèves de ses classes
 *   appel[/<AAAA-MM-JJ>[/<classe>[/<créneau>]]] appel (surveillant)
 *   heures[/<AAAA-MM>]                         heures du mois, estimation
 *   salaires[/fiche/<code du bulletin>]         bulletins de paie (mois, paiement), avances et prêts ; fiche de paie PDF
 *
 * Fichier : custom/espace/portail/router.php
 */

define('ESPACE_ROUTER', 1);

$route = trim((string) (isset($_GET['route']) ? $_GET['route'] : ''), '/');
unset($_GET['route']);

// Vérification de la règle des adresses propres par le module (configuration) : réponse fixe, rien d'autre
if ($route === 'ping') {
	header('Content-Type: text/plain');
	header('Cache-Control: no-store');
	print 'espace-ok';
	exit;
}
$parts = ($route === '') ? array() : explode('/', $route);

$onglets = array('notes' => 'notes', 'emploi-du-temps' => 'edt', 'absences' => 'absences', 'paiements' => 'paiements', 'dossier' => 'dossier');
$periodes = array('t1' => 1, 't2' => 2, 't3' => 3, 'annee' => 0);
$pages = array('connexion' => 'login.php', 'deconnexion' => 'logout.php', 'mot-de-passe' => 'motdepasse.php');

// Espace du personnel : ces pages sont dans le module Personnel (codes passés en paramètres, jamais de numéro)
$pagesPersonnel = array('cours' => 3, 'emploi-du-temps' => 1, 'notes' => 5, 'classes' => 2, 'examens' => 1, 'appel' => 4, 'mes-appels' => 1,
	'heures' => 2, 'salaires' => 3, 'absences' => 1, 'profil' => 1);
$dirPersonnel = dirname(__DIR__, 2).'/personnel/portail/';

$zonePersonnel = defined('ESPACE_ZONE') && ESPACE_ZONE === 'personnel';
$page = '';
if ($zonePersonnel && !empty($parts) && !isset($pagesPersonnel[$parts[0]]) && !isset($pages[$parts[0]]) && !in_array($parts[0], array('photo', 'logo', 'assets'), true)) {
	$page = ''; // pages des parents et élèves : jamais dans l'espace du personnel
} elseif ($zonePersonnel && !empty($parts) && isset($pagesPersonnel[$parts[0]]) && count($parts) <= $pagesPersonnel[$parts[0]] && is_dir($dirPersonnel)) {
	$_GET['args'] = array_slice($parts, 1);
	$fichiers = array('emploi-du-temps' => 'edt', 'mes-appels' => 'mesappels');
	$page = $dirPersonnel.(isset($fichiers[$parts[0]]) ? $fichiers[$parts[0]] : $parts[0]).'.php';
} elseif (empty($parts)) {
	$page = 'index.php';
} elseif (count($parts) === 1 && isset($pages[$parts[0]])) {
	$page = $pages[$parts[0]];
} elseif ($parts[0] === 'eleve' && isset($parts[1]) && count($parts) <= 4) {
	$_GET['jeton'] = $parts[1];
	if (isset($parts[2])) {
		if (!isset($onglets[$parts[2]])) {
			$page = '';
		} else {
			$_GET['onglet'] = $onglets[$parts[2]];
			$page = 'eleve.php';
		}
		if (isset($parts[3])) {
			if ($parts[2] === 'notes' && isset($periodes[$parts[3]])) {
				$_GET['periode'] = $periodes[$parts[3]];
			} else {
				$page = '';
			}
		}
	} else {
		$page = 'eleve.php';
	}
} elseif ($parts[0] === 'document' && isset($parts[1], $parts[2])) {
	$_GET['jeton'] = $parts[1];
	$_GET['doc'] = $parts[2];
	if ($parts[2] === 'certificat' && count($parts) === 3) {
		$page = 'pdf.php';
	} elseif ($parts[2] === 'bulletin' && count($parts) === 4 && isset($periodes[$parts[3]])) {
		$_GET['periode'] = $periodes[$parts[3]];
		$page = 'pdf.php';
	} elseif ($parts[2] === 'recu' && count($parts) === 4) {
		$_GET['recu'] = $parts[3];
		$page = 'pdf.php';
	}
} elseif ($parts[0] === 'photo' && count($parts) === 2) {
	$_GET['jeton'] = $parts[1];
	$page = 'photo.php';
} elseif (($parts[0] === 'logo' && count($parts) === 1) || $parts[0] === 'assets') {
	$page = 'fichier.php';
	$_GET['fichier'] = $route;
}
$_REQUEST = array_merge($_REQUEST, $_GET);

if ($page === '') {
	http_response_code(404);
	require __DIR__.'/boot.php';
	$langs = espace_langs_init(espace_langue_code($db));
	espace_header($langs->trans('PageIntrouvable'), null, espace_page_url(''));
	print espace_msg($langs->trans('PageIntrouvableAide'), 'warn');
	espace_footer();
	$db->close();
	exit;
}
require (strpos($page, '/') !== false ? $page : __DIR__.'/'.$page);
