<?php
/**
 * Fonctions communes du module Personnel.
 *
 * Fichier : custom/personnel/core/lib/personnel.lib.php
 */

/**
 * Paramètres des pages génériques (liste / fiche de crud.lib.php du module Classes) des objets du module.
 *
 * @param  string $name employe | motif | doc_type | jour_sans_cours
 * @return array|null
 */
function personnel_crud_config($name)
{
	$cfgs = array(
		'employe' => array('module' => 'personnel', 'dir' => 'employe', 'class' => 'EcoleEmploye', 'title' => 'Employes', 'ficheTitle' => 'Employe', 'newLabel' => 'NouvelEmploye',
			'perm_export' => 'employe.exporter', 'perm_read' => 'employe.lire', 'perm_write' => 'employe.modifier', 'perm_create' => 'employe.creer', 'perm_delete' => 'employe.supprimer',
			'extra_columns' => array(
				'categories' => array('label' => 'CategoriesEmploye', 'render' => 'employe_col_categories', 'sort' => 'categories',
					'search' => array('type' => 'multiselect', 'options' => 'personnel_categories_choix', 'sql' => 'employe_search_categorie')),
				'pieces' => array('label' => 'PiecesDossier', 'render' => 'employe_col_pieces',
					'search' => array('type' => 'select', 'options' => 'employe_search_pieces_options', 'sql' => 'employe_search_pieces')),
				'compte' => array('label' => 'CompteUtilisateur', 'render' => 'employe_col_compte',
					'search' => array('type' => 'text', 'sql' => 'employe_search_compte')),
			)),
		'motif' => array('module' => 'personnel', 'dir' => 'motif', 'class' => 'EcoleEmployeMotif', 'title' => 'MotifsPersonnel', 'ficheTitle' => 'MotifPersonnel', 'newLabel' => 'NouveauMotif',
			'perm_export' => 'employe.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'doc_type' => array('module' => 'personnel', 'dir' => 'doc_type', 'class' => 'EcoleEmployeDocType', 'title' => 'PiecesAFournirPersonnel', 'ficheTitle' => 'PieceAFournir', 'newLabel' => 'NouvellePiece',
			'perm_export' => 'employe.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'jour_sans_cours' => array('module' => 'personnel', 'dir' => 'jour_sans_cours', 'class' => 'EcoleJourSansCours', 'title' => 'JoursSansCours', 'ficheTitle' => 'JourSansCours', 'newLabel' => 'NouveauJourSansCours',
			'perm_export' => 'employe.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer',
			'extra_columns' => array('jours' => array('label' => 'NombreJours', 'render' => 'jour_sans_cours_col_jours'))),
	);
	return isset($cfgs[$name]) ? $cfgs[$name] : null;
}

/* ------------------------------------------------------------------
 * Catégories
 * ---------------------------------------------------------------- */

/**
 * Catégories pour une liste de choix : clé => libellé traduit.
 *
 * @return array<string,string>
 */
function personnel_categories_choix()
{
	global $langs;
	$langs->load('classes@classes');
	$out = array();
	foreach (ecole_categories_utilisateur() as $k => $l) {
		$out[$k] = $langs->trans($l);
	}
	return $out;
}

/**
 * Catégories en badges.
 *
 * @param  string $value Clés séparées par des virgules
 * @return string        HTML
 */
function personnel_categories_badges($value)
{
	global $langs;
	$langs->load('classes@classes');
	$all = ecole_categories_utilisateur();
	$out = array();
	foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $c) {
		if (isset($all[$c])) {
			$out[] = '<span class="badge badge-secondary marginrightonly" style="font-weight:normal">'.dol_escape_htmltag($langs->trans($all[$c])).'</span>';
		}
	}
	return implode('', $out);
}

/**
 * Catégories en texte (exports) : « Enseignant, Surveillant ».
 *
 * @param  string     $value Clés séparées par des virgules
 * @param  Translate  $l     Langue (null = langue courante)
 * @return string
 */
function personnel_categories_texte($value, $l = null)
{
	global $langs;
	$l = $l ? $l : $langs;
	$l->load('classes@classes');
	$all = ecole_categories_utilisateur();
	$out = array();
	foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $c) {
		if (isset($all[$c])) {
			$out[] = $l->transnoentities($all[$c]);
		}
	}
	return implode(', ', $out);
}

/* ------------------------------------------------------------------
 * Colonnes et filtres de la liste des employés
 * ---------------------------------------------------------------- */

/**
 * Colonne « Catégories ».
 *
 * @param  EcoleEmploye $rec Employé
 * @return string
 */
function employe_col_categories($rec)
{
	return personnel_categories_badges((string) $rec->categories);
}

/**
 * L'enseignant peut-il saisir les notes dans son espace ? (réglage de sa fiche, autorisé par défaut)
 *
 * @param  EcoleEmploye $emp Employé
 * @return bool
 */
function personnel_saisie_notes_autorisee($emp)
{
	global $db;
	if (function_exists('espace_perm')) {
		return espace_perm($db, ESPACE_EMPLOYE, (int) $emp->id, 'notes_saisir', $emp); // permission de l'espace
	}
	return !isset($emp->saisie_notes) || $emp->saisie_notes === null || $emp->saisie_notes === '' || (int) $emp->saisie_notes === 1;
}

/**
 * Le surveillant a-t-il les boutons WhatsApp « Prévenir les parents » dans son espace ? (réglage de sa fiche, autorisé par défaut)
 *
 * @param  EcoleEmploye $emp Employé
 * @return bool
 */
function personnel_whatsapp_autorise($emp)
{
	global $db;
	if (function_exists('espace_perm')) {
		return espace_perm($db, ESPACE_EMPLOYE, (int) $emp->id, 'whatsapp', $emp); // permission de l'espace
	}
	return !isset($emp->envoi_whatsapp) || $emp->envoi_whatsapp === null || $emp->envoi_whatsapp === '' || (int) $emp->envoi_whatsapp === 1;
}

/**
 * L'employé peut-il changer sa photo et une partie de son dossier dans son espace ?
 * Réglage de sa fiche, sinon réglage général de la configuration (non par défaut).
 *
 * @param  EcoleEmploye $emp Employé
 * @return bool
 */
function personnel_modif_profil_autorisee($emp)
{
	global $db;
	if (function_exists('espace_perm')) {
		return espace_perm($db, ESPACE_EMPLOYE, (int) $emp->id, 'profil_modifier', $emp); // permission de l'espace
	}
	if (!isset($emp->modif_profil) || $emp->modif_profil === null || $emp->modif_profil === '') {
		return getDolGlobalInt('PERSONNEL_ESPACE_MODIF_PROFIL') === 1;
	}
	return (int) $emp->modif_profil === 1;
}

/** Champs du dossier que l'employé peut modifier lui-même dans son espace (si c'est permis) et leur longueur maximale */
function personnel_champs_modifiables()
{
	return array('urgence_nom' => 255, 'urgence_telephone' => 32, 'urgence_lien' => 64, 'diplome' => 255, 'specialite' => 255);
}

/**
 * Filtre « Catégories » : employés qui ont au moins une des catégories choisies (parmi d'autres).
 *
 * @param  EcoleEmploye $object Objet
 * @param  string[]     $value  Clés de catégorie
 * @return string               Condition SQL
 */
function employe_search_categorie($object, $value)
{
	$all = ecole_categories_utilisateur();
	$or = array();
	foreach ((array) $value as $v) {
		if (isset($all[$v])) {
			$or[] = "FIND_IN_SET('".$object->db->escape($v)."', t.categories) > 0";
		}
	}
	return $or ? '('.implode(' OR ', $or).')' : '';
}

/**
 * Choix du filtre « Pièces ».
 *
 * @return array<string,string>
 */
function employe_search_pieces_options()
{
	global $langs;
	return array('manquantes' => $langs->trans('PiecesManquantes'), 'complet' => $langs->trans('DossierComplet'));
}

/**
 * Filtre « Pièces » : dossiers complets ou avec des pièces manquantes.
 *
 * @param  EcoleEmploye $object Objet
 * @param  string       $value  manquantes | complet
 * @return string               Condition SQL
 */
function employe_search_pieces($object, $value)
{
	$sql = EcoleEmploye::sqlPiecesManquantes($object->db);
	if ($value === 'manquantes') {
		return $sql;
	}
	return $value === 'complet' ? 'NOT ('.$sql.')' : '';
}

/**
 * Filtre « Compte utilisateur » : identifiant de connexion du compte relié.
 *
 * @param  EcoleEmploye $object Objet
 * @param  string       $value  Texte saisi
 * @return string               Condition SQL
 */
function employe_search_compte($object, $value)
{
	return "t.fk_user IN (SELECT u.rowid FROM ".$object->db->prefix()."user as u WHERE ".natural_search(array('u.login', 'u.lastname', 'u.firstname'), $value, 0, 1).")";
}

/**
 * Colonne « Pièces » : pièces fournies / demandées, en rouge s'il en manque.
 *
 * @param  EcoleEmploye $rec Employé
 * @return string
 */
function employe_col_pieces($rec)
{
	global $langs;
	$total = 0;
	$fournies = 0;
	foreach ($rec->getPieces() as $o) {
		if ($o->demandee) {
			$total++;
			$fournies += empty($o->fourni) ? 0 : 1;
		}
	}
	if ($total === 0) {
		return '';
	}
	$url = dol_buildpath('/personnel/employe/documents.php', 1).'?id='.((int) $rec->id);
	$badge = ($fournies < $total) ? 'badge-danger' : 'badge-status4';
	$title = ($fournies < $total) ? $langs->trans('PiecesManquantesNb', $total - $fournies) : $langs->trans('DossierComplet');
	return '<a href="'.$url.'" title="'.dol_escape_htmltag($title).'"><span class="badge '.$badge.'">'.$fournies.' / '.$total.'</span></a>';
}

/**
 * Colonne « Compte utilisateur » : identifiant du compte Dolibarr relié (barré s'il est désactivé).
 *
 * @param  EcoleEmploye $rec Employé
 * @return string
 */
function employe_col_compte($rec)
{
	global $db;
	$u = personnel_user_row($db, (int) $rec->fk_user);
	if (!$u) {
		return '';
	}
	$txt = dol_escape_htmltag($u->login);
	return (int) $u->statut === 1 ? $txt : '<span class="opacitymedium" style="text-decoration:line-through">'.$txt.'</span>';
}

/**
 * Colonne « Nombre de jours » de la liste des jours sans cours.
 *
 * @param  EcoleJourSansCours $rec Période
 * @return string
 */
function jour_sans_cours_col_jours($rec)
{
	if (empty($rec->date_debut) || empty($rec->date_fin)) {
		return '';
	}
	return (string) (int) (round(($rec->date_fin - $rec->date_debut) / 86400) + 1);
}

/* ------------------------------------------------------------------
 * Utilisateurs Dolibarr
 * ---------------------------------------------------------------- */

/**
 * Ligne d'un utilisateur Dolibarr (login, nom, statut, admin), avec cache.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Utilisateur
 * @return object|null
 */
function personnel_user_row($db, $id)
{
	static $cache = array();
	if ((int) $id <= 0) {
		return null;
	}
	if (!array_key_exists((int) $id, $cache)) {
		$resql = $db->query("SELECT rowid, login, firstname, lastname, statut, admin, datelastlogin FROM ".$db->prefix()."user WHERE rowid = ".((int) $id));
		$cache[(int) $id] = ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}
	return $cache[(int) $id];
}

/**
 * La table existe-t-elle (module facultatif) ?
 *
 * @param  DoliDB $db    Handler base
 * @param  string $table Table sans préfixe
 * @return bool
 */
function personnel_table_existe($db, $table)
{
	static $cache = array();
	if (!isset($cache[$table])) {
		$resql = $db->query("SHOW TABLES LIKE '".$db->escape($db->prefix().$table)."'");
		$cache[$table] = ($resql && $db->num_rows($resql) > 0);
	}
	return $cache[$table];
}

/**
 * Ce compte est-il un compte de l'espace parents et élèves (module Espace) ?
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur
 * @return bool
 */
function personnel_est_compte_espace($db, $fk_user)
{
	if (!personnel_table_existe($db, 'ecole_acces')) {
		return false;
	}
	$resql = $db->query("SELECT rowid FROM ".$db->prefix()."ecole_acces WHERE fk_user = ".((int) $fk_user));
	return $resql && $db->num_rows($resql) > 0;
}

/**
 * Utilisateurs Dolibarr qu'on peut relier à une fiche employé : id => « Nom (login) »
 * (pas déjà reliés, pas les comptes de l'espace parents et élèves).
 *
 * @param  DoliDB $db   Handler base
 * @param  int    $keep Id à garder
 * @return array<int,string>
 */
function personnel_users_reliables($db, $keep = 0)
{
	$p = $db->prefix();
	$sql = "SELECT u.rowid, u.login, u.firstname, u.lastname, u.statut FROM ".$p."user u WHERE u.entity IN (".getEntity('user').")";
	$sql .= " AND (u.rowid = ".((int) $keep)." OR (u.rowid NOT IN (SELECT fk_user FROM ".$p."ecole_employe WHERE fk_user IS NOT NULL)";
	if (personnel_table_existe($db, 'ecole_acces')) {
		$sql .= " AND u.rowid NOT IN (SELECT fk_user FROM ".$p."ecole_acces WHERE fk_user IS NOT NULL)";
	}
	$sql .= ")) ORDER BY u.lastname, u.firstname, u.login";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$nom = trim($o->firstname.' '.$o->lastname);
		$out[(int) $o->rowid] = ($nom !== '' ? $nom.' ('.$o->login.')' : $o->login).((int) $o->statut === 1 ? '' : ' ✗');
	}
	return $out;
}

/**
 * Employé relié à l'utilisateur connecté (null s'il n'a pas de fiche).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return EcoleEmploye|null
 */
function personnel_employe_de($db, $user)
{
	dol_include_once('/personnel/class/ecole_employe.class.php');
	$o = EcoleEmploye::employeDeUtilisateur($db, (int) $user->id);
	if (!$o) {
		return null;
	}
	$e = new EcoleEmploye($db);
	return $e->fetch((int) $o->rowid) > 0 ? $e : null;
}

/* ------------------------------------------------------------------
 * Listes de choix
 * ---------------------------------------------------------------- */

/**
 * Employés pour une liste de choix : id => « Nom (matricule) » (présents + valeur actuelle),
 * éventuellement limités à une catégorie.
 *
 * @param  DoliDB $db       Handler base
 * @param  string $categorie '' = tous
 * @param  int    $keep     Id à garder
 * @return array<int,string>
 */
function personnel_employes_choix($db, $categorie = '', $keep = 0)
{
	$sql = "SELECT rowid, ref, nom_fr, nom_ar FROM ".$db->prefix()."ecole_employe WHERE entity IN (".getEntity('ecole_employe').")";
	$sql .= " AND (status <> 4".($categorie !== '' ? " AND FIND_IN_SET('".$db->escape($categorie)."', categories) > 0" : "").($keep > 0 ? " OR rowid = ".((int) $keep) : "").")";
	$sql .= " ORDER BY nom_fr";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = ecole_label($o).' ('.$o->ref.')';
	}
	return $out;
}

/**
 * Matières du catalogue pour une liste de choix : id => « REF - Libellé » (actives + valeurs gardées).
 *
 * @param  DoliDB $db   Handler base
 * @param  int[]  $keep Ids à garder
 * @return array<int,string>
 */
function personnel_matieres_choix($db, $keep = array())
{
	$keep = array_filter(array_map('intval', (array) $keep));
	$sql = "SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_matiere WHERE entity IN (".getEntity('ecole_matiere').")";
	$sql .= " AND (status = 1".($keep ? " OR rowid IN (".implode(',', $keep).")" : "").") ORDER BY label_fr";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o->ref.' - '.ecole_label($o);
	}
	return $out;
}

/**
 * Classes actives pour une liste de choix : id => « REF - Libellé » (ordre des niveaux).
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function personnel_classes_choix($db)
{
	$out = array();
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1 ORDER BY n.position, c.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o->ref.' - '.ecole_label($o);
	}
	return $out;
}

/**
 * Contrôle de l'emploi du temps (module Classes) : un enseignant ne peut recevoir qu'une matière inscrite sur sa
 * fiche employé (« Matières qu'il peut enseigner »). Pour en ajouter une, l'administrateur modifie d'abord la fiche.
 * Les classes, elles, ne sont pas limitées.
 *
 * @param  DoliDB $db         Handler base
 * @param  int    $fk_user    Enseignant (utilisateur Dolibarr)
 * @param  int    $fk_matiere Matière
 * @return string             Message d'erreur traduit, '' si c'est permis
 */
function personnel_controle_matiere_edt($db, $fk_user, $fk_matiere)
{
	global $langs;
	if ((int) $fk_user <= 0 || (int) $fk_matiere <= 0) {
		return '';
	}
	$langs->load('personnel@personnel');
	dol_include_once('/personnel/class/ecole_employe.class.php');
	$o = EcoleEmploye::employeDeUtilisateur($db, (int) $fk_user);
	if (!$o) {
		return $langs->trans('ErrorEnseignantSansFiche', ecole_user_label($db, $fk_user));
	}
	$resql = $db->query("SELECT rowid FROM ".$db->prefix()."ecole_employe_matiere WHERE fk_employe = ".((int) $o->rowid)." AND fk_matiere = ".((int) $fk_matiere));
	if ($resql && $db->num_rows($resql) > 0) {
		return '';
	}
	return $langs->trans('ErrorMatiereHorsFiche', ecole_row_label($db, 'ecole_matiere', $fk_matiere), trim($o->ref.' '.ecole_label($o)));
}

/* ------------------------------------------------------------------
 * Formulaires
 * ---------------------------------------------------------------- */

/**
 * Lit dans le formulaire la valeur de chaque champ donné (mêmes règles que les pages ModuleBuilder de Dolibarr).
 * Les catégories arrivent en cases à cocher.
 *
 * @param  CommonObject $object Objet à remplir
 * @param  string[]     $keys   Champs
 * @return void
 */
function personnel_fields_from_post($object, $keys)
{
	foreach ($keys as $key) {
		if (!isset($object->fields[$key])) {
			continue;
		}
		$type = $object->fields[$key]['type'];
		if ($key === 'categories') {
			// seulement si le champ était dans le formulaire (le choix multiple envoie categories_multiselect, même vide)
			if (GETPOSTISSET('categories') || GETPOSTISSET('categories_multiselect')) {
				$object->categories = implode(',', array_map('strval', GETPOST('categories', 'array')));
			}
			continue;
		}
		if ($type === 'date') {
			if (!GETPOSTISSET($key.'year') && !GETPOSTISSET($key)) {
				continue;
			}
			$object->$key = dol_mktime(12, 0, 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
			if ($object->$key === '') {
				$object->$key = null;
			}
			continue;
		}
		if (!GETPOSTISSET($key)) {
			continue;
		}
		if (preg_match('/^text/', $type)) {
			$object->$key = GETPOST($key, 'nohtml');
		} elseif (preg_match('/^(integer|price|double)/', $type)) {
			$v = trim(GETPOST($key, 'alphanohtml'));
			$object->$key = ($v === '' || $v === '-1') ? null : (preg_match('/^integer/', $type) ? (int) $v : price2num($v));
		} else {
			$v = GETPOST($key, 'alphanohtml');
			$object->$key = ($v === '-1') ? '' : $v;
		}
	}
}

/**
 * Date saisie dans un sélecteur de date Dolibarr (champs xxxday / xxxmonth / xxxyear), ou $defaut.
 *
 * @param  string   $name   Nom du champ
 * @param  int|null $defaut Valeur si rien n'est saisi
 * @return int|null
 */
function personnel_posted_date($name, $defaut = null)
{
	$d = dol_mktime(12, 0, 0, GETPOSTINT($name.'month'), GETPOSTINT($name.'day'), GETPOSTINT($name.'year'));
	return $d ? $d : $defaut;
}

/**
 * Enregistre un fichier envoyé par formulaire dans un sous-dossier du module (nom nettoyé, vignettes pour les images).
 *
 * @param  string $input  Nom du champ <input type="file">
 * @param  string $subdir Sous-dossier relatif au dossier du module
 * @param  string $prefix Préfixe du nom de fichier (ex. type de pièce)
 * @param  bool   $image  true = image seulement
 * @return string         Nom du fichier enregistré, '' si aucun fichier, ou « ERR:message » en cas d'erreur
 */
function personnel_upload_file($input, $subdir, $prefix = '', $image = false)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

	if (empty($_FILES[$input]) || empty($_FILES[$input]['name']) || (int) $_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ((int) $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
		return 'ERR:'.$langs->trans('ErrorFileNotUploaded');
	}
	$name = dol_sanitizeFileName($_FILES[$input]['name']);
	if ($image && !image_format_supported($name)) {
		return 'ERR:'.$langs->trans('ErrorBadImageFormat');
	}
	if ($prefix !== '') {
		$name = dol_sanitizeFileName($prefix).'_'.$name;
	}
	$dir = $conf->personnel->dir_output.'/'.$subdir;
	if (dol_mkdir($dir) < 0) {
		return 'ERR:'.$langs->trans('ErrorCanNotCreateDir', $dir);
	}
	$res = dol_move_uploaded_file($_FILES[$input]['tmp_name'], $dir.'/'.$name, 1, 0, $_FILES[$input]['error']);
	if (!is_numeric($res) || $res <= 0) {
		return 'ERR:'.$langs->trans(is_string($res) ? $res : 'ErrorFileNotUploaded');
	}
	if (image_format_supported($name) > 0) {
		vignette($dir.'/'.$name, 160, 160, '_small', 80, 'thumbs');
	}
	return $name;
}

/* ------------------------------------------------------------------
 * Fiche employé : onglets, bannière, photo
 * ---------------------------------------------------------------- */

/**
 * Onglets de la fiche d'un employé.
 *
 * @param  EcoleEmploye $object Employé
 * @return array
 */
function employe_prepare_head($object)
{
	global $langs, $user;
	$langs->load('personnel@personnel');
	$id = (int) $object->id;
	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/personnel/employe/card.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('FicheEmploye');
	$head[$h][2] = 'card';
	$h++;

	// Emploi du temps, matières enseignées et matières qu'il peut enseigner
	$head[$h][0] = dol_buildpath('/personnel/employe/emploi.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('EmploiMatieresEmploye');
	$head[$h][2] = 'emploi';
	$h++;

	if ($user->hasRight('personnel', 'presence', 'lire') || $user->hasRight('personnel', 'absence', 'lire')) {
		$head[$h][0] = dol_buildpath('/personnel/employe/presence.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('PresenceHeures');
		$head[$h][2] = 'presence';
		$h++;
	}

	if ($user->hasRight('personnel', 'document', 'lire')) {
		$nb = count($object->getPiecesManquantes());
		$head[$h][0] = dol_buildpath('/personnel/employe/documents.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('PiecesDossier').($nb > 0 ? '<span class="badge badge-danger marginleftonlyshort" title="'.dol_escape_htmltag($langs->trans('PiecesManquantesNb', $nb)).'">'.$nb.'</span>' : '');
		$head[$h][2] = 'documents';
		$h++;
	}

	// Bulletins de paie et règles de paie de l'employé (module Salaires)
	if (isModEnabled('salaires') && $user->hasRight('salaires', 'bulletin', 'lire')) {
		$langs->load('salaires@salaires');
		$head[$h][0] = dol_buildpath('/salaires/employe.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('OngletSalaires');
		$head[$h][2] = 'salaires';
		$h++;
	}

	// Accès à l'espace du personnel (module Espace)
	if (isModEnabled('espace') && $user->hasRight('espace', 'acces', 'lire')) {
		$langs->load('espace@espace');
		$head[$h][0] = dol_buildpath('/espace/acces.php', 1).'?type=employe&id='.$id;
		$head[$h][1] = $langs->trans('AccesEspaceOnglet');
		$head[$h][2] = 'espace';
		$h++;
	}

	$head[$h][0] = dol_buildpath('/personnel/employe/historique.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Historique');
	$head[$h][2] = 'historique';
	$h++;

	return $head;
}

/**
 * URL de la photo d'un employé (vignette si disponible), ou '' s'il n'en a pas.
 *
 * @param  EcoleEmploye $object Employé
 * @param  bool         $small  true = vignette
 * @return string
 */
function employe_photo_url($object, $small = true)
{
	global $conf;
	if (empty($object->photo)) {
		return '';
	}
	$sub = $object->getPhotoSubdir();
	$file = $object->photo;
	if ($small) {
		$thumb = preg_replace('/(\.[^.]+)$/', '_small$1', $file);
		if (is_file($conf->personnel->dir_output.'/'.$sub.'/thumbs/'.$thumb)) {
			$file = 'thumbs/'.$thumb;
		}
	}
	if (!is_file($conf->personnel->dir_output.'/'.$sub.'/'.$file)) {
		return '';
	}
	return DOL_URL_ROOT.'/viewimage.php?modulepart=personnel&entity='.((int) $conf->entity).'&file='.urlencode($sub.'/'.$file);
}

/**
 * Photo pour la bannière de la fiche ('' sans photo : Dolibarr affiche alors l'icône de l'objet).
 *
 * @param  EcoleEmploye $object Employé
 * @return string
 */
function employe_photo_html($object)
{
	$url = employe_photo_url($object, true);
	if (!$url) {
		return '';
	}
	$out = '<style>.employe-photo + .divphotoref{display:none}</style>';
	$out .= '<div class="floatleft inline-block valignmiddle divphotoref employe-photo">';
	$out .= '<a href="'.employe_photo_url($object, false).'" target="_blank" rel="noopener"><img class="photoref" style="object-fit:cover" src="'.$url.'" alt="'.dol_escape_htmltag($object->nom_fr).'"></a>';
	$out .= '</div>';
	return $out;
}

/**
 * En-tête d'un onglet de la fiche employé (onglets + bannière avec photo, noms, catégories, statut).
 * Le code appelant ferme avec dol_get_fiche_end().
 *
 * @param  EcoleEmploye $object Employé
 * @param  string       $tab    Onglet actif
 * @return void
 */
function employe_print_banner($object, $tab)
{
	global $langs;

	print dol_get_fiche_head(employe_prepare_head($object), $tab, $langs->trans('Employe'), -1, $object->picto);

	$linkback = '<a href="'.dol_buildpath('/personnel/employe/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= '<span class="bold" dir="auto">'.dol_escape_htmltag(ecole_label($object)).'</span>';
	$morehtmlref .= '<br>'.personnel_categories_badges((string) $object->categories);
	if (!empty($object->poste)) {
		$morehtmlref .= ' <span class="opacitymedium">'.dol_escape_htmltag($object->poste).'</span>';
	}
	$morehtmlref .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, employe_photo_html($object), ''); // le statut est ajouté par Dolibarr
}

/**
 * Onglets de la configuration du module.
 *
 * @return array
 */
function personnel_admin_prepare_head()
{
	global $langs;
	$langs->load('personnel@personnel');
	$head = array();
	$head[] = array(dol_buildpath('/personnel/admin/setup.php', 1), $langs->trans('MenuReglages'), 'setup');
	$head[] = array(dol_buildpath('/personnel/admin/regles.php', 1), $langs->trans('MenuReglesPresence'), 'regles');
	$head[] = array(dol_buildpath('/personnel/motif/list.php', 1), $langs->trans('MenuMotifs'), 'motifs');
	$head[] = array(dol_buildpath('/personnel/doc_type/list.php', 1), $langs->trans('MenuPiecesAFournir'), 'pieces');
	$head[] = array(dol_buildpath('/personnel/jour_sans_cours/list.php', 1), $langs->trans('MenuJoursSansCours'), 'jsc');
	$head[] = array(dol_buildpath('/personnel/admin/employe_extrafields.php', 1), $langs->trans('MenuChampsSupp'), 'extrafields');
	return $head;
}

/* ------------------------------------------------------------------
 * Divers
 * ---------------------------------------------------------------- */

/**
 * Montant dans la devise de l'établissement.
 *
 * @param  float $v Montant
 * @return string
 */
function personnel_montant($v)
{
	global $conf;
	return price((float) $v, 0, '', 1, -1, 0, $conf->currency);
}

/**
 * Minutes en « 12h30 » (« 0h00 » si zéro).
 *
 * @param  int $minutes Minutes
 * @return string
 */
function personnel_duree($minutes)
{
	$m = (int) round($minutes);
	return ($m < 0 ? '-' : '').intdiv(abs($m), 60).'h'.sprintf('%02d', abs($m) % 60);
}

/**
 * Lien WhatsApp d'un numéro (indicatif du pays ajouté aux numéros locaux).
 *
 * @param  string $num Numéro
 * @return string      URL ou ''
 */
function personnel_whatsapp_url($num)
{
	$d = preg_replace('/[^0-9]/', '', (string) $num);
	if ($d === '') {
		return '';
	}
	$ind = preg_replace('/[^0-9]/', '', getDolGlobalString('ELEVES_WHATSAPP_INDICATIF', '222'));
	if (strlen($d) <= 8 && $ind !== '' && strpos($d, $ind) !== 0) {
		$d = $ind.$d;
	}
	return 'https://wa.me/'.$d;
}
