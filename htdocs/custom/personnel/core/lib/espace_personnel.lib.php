<?php
/**
 * Espace du personnel (étape 3 du module Personnel) : fonctions communes des pages de l'espace d'un employé.
 *
 * L'espace du personnel est le même que l'espace des parents (module Espace) : même adresse, même page de
 * connexion (identifiant = matricule), même session, mêmes adresses propres. Ces pages sont appelées par
 * l'aiguilleur de l'Espace (espace/portail/router.php) et ne montrent que ce qui concerne l'employé connecté :
 *  - enseignant : ses cours (déclaration, élèves renvoyés), son emploi du temps, la saisie des notes de ses classes
 *    et matières, les élèves de ses classes (sans coordonnées ni situation financière), les examens ;
 *  - surveillant : l'appel de toutes les classes, les appels qu'il a faits, ses surveillances d'examens ;
 *  - tous : heures du mois et estimation de la paie, salaires versés, absences, dossier.
 * Les rubriques dépendent des catégories de l'employé (plusieurs catégories = toutes les rubriques réunies).
 *
 * Fichier : custom/personnel/core/lib/espace_personnel.lib.php
 */

dol_include_once('/personnel/core/lib/personnel.lib.php');
dol_include_once('/personnel/core/lib/presence.lib.php');

/**
 * Charge l'employé connecté à l'espace, ou renvoie à l'accueil si ce n'est pas un employé.
 * Donne aussi son compte Dolibarr (pour garder qui a fait chaque enregistrement) et vérifie la rubrique.
 *
 * @param  DoliDB $db      Handler base
 * @param  string $rubrique Rubrique demandée ('' = aucune vérification)
 * @return array{0:object,1:EcoleEmploye,2:User,3:array}  accès, employé, compte, rubriques permises
 */
function pe_session($db, $rubrique = '')
{
	global $langs;
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	$acces = espace_exiger_session($db);
	if ($acces->type !== ESPACE_EMPLOYE || !isModEnabled('personnel')) {
		header('Location: '.espace_page_url(''));
		exit;
	}
	$langs = espace_langs_init(espace_langue_code($db, $acces));
	$emp = espace_cible($db, ESPACE_EMPLOYE, (int) $acces->fk_cible);
	$moi = new User($db);
	if (!$emp || $moi->fetch((int) $acces->fk_user) <= 0) {
		espace_session_fermer();
		header('Location: '.espace_page_url('connexion', array('refus' => 1)));
		exit;
	}
	$moi->getrights();
	$GLOBALS['user'] = $moi;
	$rubriques = espace_rubriques_employe($emp);
	if ($rubrique !== '' && !isset($rubriques[$rubrique])) {
		header('Location: '.espace_page_url(''));
		exit;
	}
	return array($acces, $emp, $moi, $rubriques);
}

/**
 * Début d'une page de l'espace du personnel : barre du haut, carte de l'employé (accueil) et menu des rubriques.
 *
 * @param  string       $title     Titre
 * @param  object       $acces     Accès
 * @param  EcoleEmploye $emp       Employé
 * @param  array        $rubriques Rubriques permises
 * @param  string       $actif     Rubrique active
 * @param  string       $back      URL du bouton retour
 * @return void
 */
function pe_header($title, $acces, $emp, $rubriques, $actif, $back = '')
{
	global $langs;
	espace_header($title, $acces, $back);
	if (!empty($_SESSION['ecole_espace_msg'])) {
		print espace_msg(dol_escape_htmltag($_SESSION['ecole_espace_msg']), 'ok');
		unset($_SESSION['ecole_espace_msg']);
	}
	if (!empty($_SESSION['ecole_espace_err'])) {
		print espace_msg(dol_escape_htmltag($_SESSION['ecole_espace_err']), 'error');
		unset($_SESSION['ecole_espace_err']);
	}
	print '<nav class="es-tabs">';
	foreach ($rubriques as $k => $r) {
		print '<a class="es-tab'.($k === $actif ? ' active' : '').'" href="'.dol_escape_htmltag(espace_page_url($r[2])).'"><i class="fas '.$r[1].'"></i><span>'.$langs->trans($r[0]).'</span></a>';
	}
	print '</nav>';
}

/**
 * Message affiché à la page suivante (après une redirection).
 *
 * @param  string $text Texte
 * @param  bool   $ok   true = succès, false = erreur
 * @return void
 */
function pe_flash($text, $ok = true)
{
	$_SESSION[$ok ? 'ecole_espace_msg' : 'ecole_espace_err'] = $text;
}

/**
 * Arguments de l'adresse (parties après la rubrique), passés par l'aiguilleur.
 *
 * @param  int    $i Position (0 = première partie)
 * @return string
 */
function pe_arg($i)
{
	$args = (isset($_GET['args']) && is_array($_GET['args'])) ? $_GET['args'] : array();
	return isset($args[$i]) ? (string) $args[$i] : '';
}

/**
 * Code d'un cours (classe + créneau) dans les adresses, à la place des numéros.
 *
 * @param  int $fk_classe  Classe
 * @param  int $fk_creneau Créneau
 * @return string
 */
function pe_jeton_cours($fk_classe, $fk_creneau)
{
	return espace_jeton('cours', ((int) $fk_classe) * 10000 + (int) $fk_creneau);
}

/**
 * Retrouve un cours par son code parmi les cours permis : [fk_classe, fk_creneau] ou null.
 *
 * @param  string $jeton  Code
 * @param  array  $cours  Cours permis (lignes avec fk_classe, fk_creneau)
 * @return array{0:int,1:int}|null
 */
function pe_cours_par_jeton($jeton, $cours)
{
	$ids = array();
	foreach ($cours as $c) {
		$ids[] = ((int) $c->fk_classe) * 10000 + (int) $c->fk_creneau;
	}
	$id = espace_id_par_jeton('cours', $jeton, $ids);
	return $id > 0 ? array(intdiv($id, 10000), $id % 10000) : null;
}

/**
 * Classes et matières de l'enseignant d'après l'emploi du temps : fk_classe => array(fk_matiere => true).
 *
 * @param  DoliDB       $db  Handler base
 * @param  EcoleEmploye $emp Employé
 * @return array<int,array<int,bool>>
 */
function pe_affectations($db, $emp)
{
	$out = array();
	if ((int) $emp->fk_user <= 0) {
		return $out;
	}
	$resql = $db->query("SELECT DISTINCT fk_classe, fk_matiere FROM ".$db->prefix()."ecole_edt_cours WHERE fk_user = ".((int) $emp->fk_user));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_classe][(int) $o->fk_matiere] = true;
	}
	return $out;
}

/**
 * Classes de l'enseignant (actives), dans l'ordre des niveaux : id => ligne (rowid, ref, label_fr, label_ar).
 *
 * @param  DoliDB       $db  Handler base
 * @param  EcoleEmploye $emp Employé
 * @return array<int,object>
 */
function pe_classes($db, $emp)
{
	$ids = array_keys(pe_affectations($db, $emp));
	$out = array();
	if (empty($ids)) {
		return $out;
	}
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.rowid IN (".implode(',', array_map('intval', $ids)).") AND c.status = 1 ORDER BY n.position, c.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/**
 * Élèves que l'employé peut voir (photo, liste de classe) : ceux des classes de l'enseignant ;
 * pour un surveillant, tous les élèves inscrits ou suspendus.
 *
 * @param  DoliDB       $db  Handler base
 * @param  EcoleEmploye $emp Employé
 * @return int[]
 */
function pe_eleves_autorises($db, $emp)
{
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$sql = "SELECT rowid FROM ".$db->prefix()."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND status IN (".implode(',', EcoleEleve::statusOccupantPlace()).")";
	if (!$emp->aCategorie('surveillant')) {
		$ids = array_keys(pe_affectations($db, $emp));
		$sql .= " AND fk_classe IN (".(empty($ids) ? '0' : implode(',', array_map('intval', $ids))).")";
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = (int) $o->rowid;
	}
	return $out;
}

/**
 * Pastille de l'état d'un cours dans l'espace.
 *
 * @param  string $etat present | declare | absent | retard | avenir | sanscours | suspendu | remplacement
 * @param  string $info Texte en plus
 * @return string       HTML
 */
function pe_etat_chip($etat, $info = '')
{
	global $langs;
	$map = array(
		'present' => array('es-chip-green', 'Present', 'fa-check'),
		'declare' => array('es-chip-green', 'CoursDeclare', 'fa-check-double'),
		'absent' => array('es-chip-red', 'Absent', 'fa-times'),
		'retard' => array('es-chip-orange', 'EnRetard', 'fa-clock'),
		'avenir' => array('', 'AVenir', 'fa-hourglass-start'),
		'sanscours' => array('', 'JourSansCours', 'fa-umbrella-beach'),
		'suspendu' => array('es-chip-purple', 'StatutSuspenduConge', 'fa-pause'),
		'remplacement' => array('es-chip-blue', 'Remplacement', 'fa-exchange-alt'),
	);
	$m = isset($map[$etat]) ? $map[$etat] : array('', $etat, 'fa-circle');
	return '<span class="es-chip '.$m[0].'"><i class="fas '.$m[2].'"></i> '.dol_escape_htmltag($langs->trans($m[1]).($info !== '' ? ' '.$info : '')).'</span>';
}

/**
 * Durée affichée de gauche à droite.
 *
 * @param  int $minutes Minutes
 * @return string       HTML
 */
function pe_duree($minutes)
{
	return '<span dir="ltr">'.dol_escape_htmltag(personnel_duree($minutes)).'</span>';
}

/**
 * Libellé d'une ligne (matière, classe...) à partir de colonnes préfixées : ex. pe_label($o, 'm_') lit m_fr / m_ar.
 *
 * @param  object $o      Ligne
 * @param  string $prefix Préfixe
 * @return string
 */
function pe_label($o, $prefix)
{
	$fr = $prefix.'fr';
	$ar = $prefix.'ar';
	return ecole_label((object) array('label_fr' => isset($o->$fr) ? $o->$fr : '', 'label_ar' => isset($o->$ar) ? $o->$ar : ''));
}
