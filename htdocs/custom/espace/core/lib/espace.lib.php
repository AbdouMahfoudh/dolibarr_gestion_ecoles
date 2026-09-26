<?php
/**
 * Fonctions du module « Espace parents et élèves » communes à l'interface de gestion et à l'espace :
 * comptes d'accès (création, mot de passe provisoire, réinitialisation, désactivation), état de l'accès
 * (coupé automatiquement quand plus aucun enfant n'est inscrit), élèves visibles, message WhatsApp.
 *
 * Fichier : custom/espace/core/lib/espace.lib.php
 */

dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/class/ecole_responsable.class.php');
dol_include_once('/espace/core/lib/permissions.lib.php');

/** Accès d'un responsable : voit tous ses enfants inscrits */
define('ESPACE_PARENT', 'parent');
/** Accès d'un élève : se voit lui-même */
define('ESPACE_ELEVE', 'eleve');
/** Accès d'un employé (module Personnel) : ses cours, l'appel, ses heures, ses absences, sa paie */
define('ESPACE_EMPLOYE', 'employe');

/**
 * Types d'accès valides.
 *
 * @return string[]
 */
function espace_types()
{
	return isModEnabled('personnel') ? array(ESPACE_PARENT, ESPACE_ELEVE, ESPACE_EMPLOYE) : array(ESPACE_PARENT, ESPACE_ELEVE);
}

/**
 * Catégories d'employés qui gardent l'interface de gestion (direction, secrétariat, comptabilité) : leur espace
 * s'ouvre depuis l'interface de gestion (« Mon espace »), leur mot de passe Dolibarr ne change pas.
 *
 * @return string[]
 */
function espace_categories_gestion()
{
	return array('direction', 'secretariat', 'comptable');
}

/**
 * Un employé garde-t-il l'interface de gestion (catégorie de gestion ou administrateur) ?
 *
 * @param  DoliDB       $db  Handler base
 * @param  EcoleEmploye $emp Employé
 * @return bool
 */
function espace_employe_gestion($db, $emp)
{
	if (array_intersect($emp->getCategories(), espace_categories_gestion())) {
		return true;
	}
	if ((int) $emp->fk_user > 0) {
		$resql = $db->query("SELECT admin FROM ".$db->prefix()."user WHERE rowid = ".((int) $emp->fk_user));
		$o = $resql ? $db->fetch_object($resql) : null;
		return $o && (int) $o->admin === 1;
	}
	return false;
}

/**
 * Rubriques de l'espace d'un employé, selon ses permissions (onglet « Accès espace » de sa fiche ; sans réglage,
 * modèle de ses catégories) : clé => [clé de traduction, icône, route]. Permission retirée = rubrique absente.
 * L'accueil et le dossier (consultation) sont toujours présents.
 *
 * @param  EcoleEmploye $emp Employé
 * @return array<string,array{0:string,1:string,2:string}>
 */
function espace_rubriques_employe($emp)
{
	global $db;
	$perms = espace_perms($db, ESPACE_EMPLOYE, (int) $emp->id, $emp);
	$a = function ($k) use ($perms) {
		return in_array($k, $perms, true);
	};
	$out = array('accueil' => array('EspAccueil', 'fa-home', ''));
	if ($a('cours_voir') || $a('cours_declarer')) {
		$out['cours'] = array('EspMesCours', 'fa-chalkboard-teacher', 'cours');
	}
	if ($a('edt')) {
		$out['edt'] = array('EspEmploiDuTemps', 'fa-calendar-alt', 'emploi-du-temps');
	}
	if (isModEnabled('notes') && ($a('notes_voir') || $a('notes_saisir'))) {
		$out['notes'] = array('EspSaisieNotes', 'fa-star', 'notes');
	}
	if ($a('classes')) {
		$out['classes'] = array('EspMesClasses', 'fa-users', 'classes');
	}
	if ($a('appel')) {
		$out['appel'] = array('EspAppel', 'fa-clipboard-check', 'appel');
	}
	if ($a('mesappels')) {
		$out['mesappels'] = array('EspMesAppels', 'fa-history', 'mes-appels');
	}
	if ($a('signales')) {
		$out['signales'] = array('EspElevesSignales', 'fa-flag', 'signales');
	}
	if ($a('examens')) {
		$out['examens'] = array('EspExamens', 'fa-file-signature', 'examens');
	}
	if ($a('heures')) {
		$out['heures'] = array('EspHeuresPaie', 'fa-coins', 'heures');
	}
	if (isModEnabled('salaires') && $a('salaires')) {
		$out['salaires'] = array('EspMesSalaires', 'fa-money-check-alt', 'salaires'); // bulletins de paie, avances et prêts (module Salaires)
	}
	if ($a('absences')) {
		$out['absences'] = array('EspMesAbsences', 'fa-user-clock', 'absences');
	}
	$out['profil'] = array('EspMonDossier', 'fa-id-card', 'profil');
	return $out;
}

/**
 * La table existe-t-elle (module facultatif) ?
 *
 * @param  DoliDB $db    Handler base
 * @param  string $table Table sans préfixe
 * @return bool
 */
function espace_table_existe($db, $table)
{
	static $cache = array();
	if (!isset($cache[$table])) {
		$resql = $db->query("SHOW TABLES LIKE '".$db->escape($db->prefix().$table)."'");
		$cache[$table] = ($resql && $db->num_rows($resql) > 0);
	}
	return $cache[$table];
}

/**
 * Les élèves peuvent-ils avoir leur propre accès (configuration) ?
 *
 * @return bool
 */
function espace_acces_eleves_actif()
{
	return getDolGlobalString('ESPACE_ACCES_ELEVES', '1') === '1';
}

/**
 * Colonnes lues pour un accès.
 *
 * @return string
 */
function espace_acces_cols()
{
	return "rowid, type, fk_cible, fk_user, mdp_provisoire, mdp_fiche, langue, date_derniere_connexion, nb_connexions, date_reinit, fk_user_reinit, date_creation, fk_user_creat, status";
}

/**
 * Accès d'un responsable ou d'un élève (null s'il n'en a pas).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  ESPACE_PARENT | ESPACE_ELEVE
 * @param  int    $cible Responsable ou élève
 * @return object|null
 */
function espace_acces_fetch($db, $type, $cible)
{
	$sql = "SELECT ".espace_acces_cols()." FROM ".$db->prefix()."ecole_acces WHERE entity IN (".getEntity('ecole_acces').")";
	$sql .= " AND type = '".$db->escape($type)."' AND fk_cible = ".((int) $cible);
	$resql = $db->query($sql);
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Accès par son identifiant.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Accès
 * @return object|null
 */
function espace_acces_fetch_id($db, $id)
{
	$resql = $db->query("SELECT ".espace_acces_cols()." FROM ".$db->prefix()."ecole_acces WHERE rowid = ".((int) $id)." AND entity IN (".getEntity('ecole_acces').")");
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Accès rattaché à un compte utilisateur Dolibarr (null si ce n'est pas un compte de l'espace).
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur
 * @return object|null
 */
function espace_acces_par_user($db, $fk_user)
{
	$resql = $db->query("SELECT ".espace_acces_cols()." FROM ".$db->prefix()."ecole_acces WHERE fk_user = ".((int) $fk_user));
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Personne à qui appartient l'accès : responsable ou élève chargé (null s'il n'existe pas).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  ESPACE_PARENT | ESPACE_ELEVE
 * @param  int    $cible Id
 * @return EcoleResponsable|EcoleEleve|null
 */
function espace_cible($db, $type, $cible)
{
	if ($type === ESPACE_EMPLOYE) {
		if (!isModEnabled('personnel')) {
			return null;
		}
		dol_include_once('/personnel/class/ecole_employe.class.php');
		$o = new EcoleEmploye($db);
		return ($cible > 0 && $o->fetch((int) $cible) > 0) ? $o : null;
	}
	$o = ($type === ESPACE_PARENT) ? new EcoleResponsable($db) : new EcoleEleve($db);
	return ($cible > 0 && $o->fetch((int) $cible) > 0) ? $o : null;
}

/**
 * Responsable à prévenir (téléphone, WhatsApp) : lui-même, ou celui de l'élève.
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  Type
 * @param  object $cible Responsable ou élève
 * @return EcoleResponsable|null
 */
function espace_responsable_de($db, $type, $cible)
{
	if ($type === ESPACE_PARENT) {
		return $cible;
	}
	$r = new EcoleResponsable($db);
	return ((int) $cible->fk_responsable > 0 && $r->fetch((int) $cible->fk_responsable) > 0) ? $r : null;
}

/**
 * Élèves visibles avec un accès : enfants du responsable inscrits (ou suspendus), ou l'élève lui-même s'il l'est.
 * Un élève parti, en abandon, exclu, pré-inscrit ou en liste d'attente n'est jamais visible.
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  Type
 * @param  int    $cible Responsable ou élève
 * @return EcoleEleve[]  id => élève
 */
function espace_eleves_visibles($db, $type, $cible)
{
	$out = array();
	if ($type !== ESPACE_PARENT && $type !== ESPACE_ELEVE) {
		return $out; // un employé ne voit jamais le dossier d'un élève par ces pages
	}
	$col = ($type === ESPACE_PARENT) ? 'fk_responsable' : 'rowid';
	$sql = "SELECT rowid FROM ".$db->prefix()."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND ".$col." = ".((int) $cible);
	$sql .= " AND status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") ORDER BY nom_fr, rowid";
	$resql = $db->query($sql);
	$ids = array();
	while ($resql && ($o = $db->fetch_object($resql))) {
		$ids[] = (int) $o->rowid;
	}
	foreach ($ids as $id) {
		$e = new EcoleEleve($db);
		if ($e->fetch($id) > 0) {
			$out[$id] = $e;
		}
	}
	return $out;
}

/**
 * État d'un accès :
 *  - 'aucun'     : pas encore d'accès ;
 *  - 'desactive' : désactivé par l'école (ou accès des élèves fermé dans la configuration) ;
 *  - 'coupe'     : coupé automatiquement, aucun élève inscrit (départ, abandon, exclusion) ;
 *  - 'actif'     : utilisable.
 *
 * @param  DoliDB      $db    Handler base
 * @param  object|null $acces Accès (espace_acces_fetch)
 * @return string
 */
function espace_etat($db, $acces)
{
	if (!$acces) {
		return 'aucun';
	}
	if (!(int) $acces->status || ($acces->type === ESPACE_ELEVE && !espace_acces_eleves_actif())) {
		return 'desactive';
	}
	if ($acces->type === ESPACE_EMPLOYE) {
		// Employé parti (ou module Personnel désactivé) : accès coupé ; il revient à la réembauche
		$emp = espace_cible($db, ESPACE_EMPLOYE, (int) $acces->fk_cible);
		return ($emp && $emp->estPresent()) ? 'actif' : 'coupe';
	}
	return empty(espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible)) ? 'coupe' : 'actif';
}

/**
 * Libellés et couleurs des états.
 *
 * @return array<string,array{0:string,1:string}> état => [clé de traduction, classe du badge]
 */
function espace_etats()
{
	return array(
		'actif' => array('EtatAccesActif', 'badge-status4'),
		'jamais' => array('EtatAccesJamais', 'badge-status1'),
		'coupe' => array('EtatAccesCoupe', 'badge-status8'),
		'desactive' => array('EtatAccesDesactive', 'badge-status9'),
		'aucun' => array('EtatAccesAucun', 'badge-status0'),
	);
}

/**
 * Badge de l'état d'un accès.
 *
 * @param  string $etat État (espace_etat)
 * @return string       HTML
 */
function espace_etat_badge($etat)
{
	global $langs;
	$map = espace_etats();
	$e = isset($map[$etat]) ? $map[$etat] : $map['aucun'];
	return '<span class="badge '.$e[1].'">'.$langs->trans($e[0]).'</span>';
}

/**
 * Peut-on créer un accès pour cette personne ? Il faut au moins un élève inscrit (ou suspendu).
 *
 * @param  DoliDB $db     Handler base
 * @param  string $type   Type
 * @param  int    $cible  Responsable ou élève
 * @param  string $raison Pourquoi c'est impossible (sortie, traduite)
 * @return bool
 */
function espace_peut_creer($db, $type, $cible, &$raison)
{
	global $langs;
	$raison = '';
	if ($type === ESPACE_ELEVE && !espace_acces_eleves_actif()) {
		$raison = $langs->trans('AccesElevesFermes');
		return false;
	}
	if ($type === ESPACE_EMPLOYE) {
		$emp = espace_cible($db, ESPACE_EMPLOYE, $cible);
		if (!$emp || !$emp->estPresent()) {
			$raison = $langs->trans('AccesImpossibleEmployeParti');
			return false;
		}
		if ((int) $emp->fk_user <= 0) {
			$raison = $langs->trans('AccesImpossibleSansCompte');
			return false;
		}
		return true;
	}
	if (empty(espace_eleves_visibles($db, $type, $cible))) {
		$raison = $langs->trans($type === ESPACE_PARENT ? 'AccesImpossibleAucunEnfant' : 'AccesImpossibleNonInscrit');
		return false;
	}
	return true;
}

/**
 * Mot de passe provisoire facile à lire et à taper sur un téléphone : 8 caractères, lettres minuscules et chiffres,
 * sans les caractères qui se confondent (0/o, 1/l/i).
 *
 * @return string
 */
function espace_mdp_genere()
{
	$lettres = 'abcdefghjkmnpqrstuvwxyz';
	$chiffres = '23456789';
	$mdp = '';
	for ($i = 0; $i < 8; $i++) {
		$src = ($i % 2) ? $chiffres : $lettres;
		$mdp .= $src[random_int(0, strlen($src) - 1)];
	}
	return $mdp;
}

/**
 * Mot de passe provisoire d'un accès (pour réimprimer la fiche), '' s'il a déjà été changé.
 *
 * @param  object $acces Accès
 * @return string
 */
function espace_mdp_fiche($acces)
{
	if (!$acces || !(int) $acces->mdp_provisoire || empty($acces->mdp_fiche)) {
		return '';
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
	return (string) dolDecrypt($acces->mdp_fiche);
}

/**
 * Retire tous les droits et groupes d'un compte de l'espace (Dolibarr donne des droits « par défaut » à la création).
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur
 * @return void
 */
function espace_user_sans_droits($db, $fk_user)
{
	$db->query("DELETE FROM ".$db->prefix()."user_rights WHERE fk_user = ".((int) $fk_user));
	$db->query("DELETE FROM ".$db->prefix()."usergroup_user WHERE fk_user = ".((int) $fk_user));
}

/**
 * Enregistre le mot de passe d'un compte de l'espace (toujours chiffré, jamais en clair).
 * Les comptes de l'espace ont leur propre règle (au moins 6 caractères, vérifiée par l'espace) : la règle
 * des mots de passe du personnel réglée dans Dolibarr (longueur, majuscules...) ne leur est pas appliquée.
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur
 * @param  string $mdp     Mot de passe en clair
 * @return int             >0 si OK
 */
function espace_user_mdp($db, $fk_user, $mdp)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
	$sql = "UPDATE ".$db->prefix()."user SET pass_crypted = '".$db->escape(dol_hash($mdp))."', pass = NULL, pass_temp = NULL,";
	$sql .= " datelastpassvalidation = '".$db->idate(dol_now())."' WHERE rowid = ".((int) $fk_user);
	return $db->query($sql) ? 1 : -1;
}

/**
 * Crée l'accès d'un responsable ou d'un élève : compte Dolibarr sans droit (identifiant = code / matricule)
 * avec un mot de passe provisoire à changer à la première connexion.
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur qui crée l'accès
 * @param  string $type  Type
 * @param  int    $cible Responsable ou élève
 * @param  string $error Message d'erreur (sortie)
 * @return int           Id de l'accès, <0 si erreur
 */
function espace_creer($db, $user, $type, $cible, &$error)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

	$error = '';
	if (!in_array($type, espace_types(), true)) {
		$error = 'Bad type';
		return -1;
	}
	$obj = espace_cible($db, $type, $cible);
	if (!$obj) {
		$error = $langs->trans('ErrorRecordNotFound');
		return -1;
	}
	if (espace_acces_fetch($db, $type, $cible)) {
		$error = $langs->trans('AccesExisteDeja');
		return -1;
	}
	$raison = '';
	if (!espace_peut_creer($db, $type, $cible, $raison)) {
		$error = $raison;
		return -1;
	}

	if ($type === ESPACE_EMPLOYE) {
		return espace_creer_employe($db, $user, $obj, $error);
	}

	$mdp = espace_mdp_genere();
	$db->begin();

	$u = new User($db);
	$u->login = $obj->ref;
	$u->lastname = $obj->nom_fr;
	$u->firstname = '';
	$u->employee = 0;
	$u->admin = 0;
	$u->entity = $conf->entity;
	$u->note_private = $langs->transnoentities($type === ESPACE_PARENT ? 'NoteCompteParent' : 'NoteCompteEleve');
	$res = $u->create($user);
	if ($res < 0) {
		$db->rollback();
		$error = ($res == -6) ? $langs->trans('ErrorLoginDejaPris', $obj->ref) : ($u->error ? $u->error : implode(', ', $u->errors));
		return -1;
	}
	if (espace_user_mdp($db, $u->id, $mdp) < 0) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	espace_user_sans_droits($db, $u->id);

	$sql = "INSERT INTO ".$db->prefix()."ecole_acces (entity, type, fk_cible, fk_user, mdp_provisoire, mdp_fiche, date_creation, fk_user_creat, status)";
	$sql .= " VALUES (".((int) $conf->entity).", '".$db->escape($type)."', ".((int) $cible).", ".((int) $u->id).", 1, '".$db->escape(dolEncrypt($mdp))."',";
	$sql .= " '".$db->idate(dol_now())."', ".((int) $user->id).", 1)";
	if (!$db->query($sql)) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	$id = (int) $db->last_insert_id($db->prefix().'ecole_acces');
	$db->commit();
	return $id;
}

/**
 * Crée l'accès d'un employé. Pas de nouveau compte : l'employé a déjà son compte Dolibarr (créé par le module
 * Personnel, identifiant = matricule). Employé de l'interface de gestion (direction, secrétariat, comptabilité,
 * administrateur) : son mot de passe ne change pas, son espace s'ouvre par « Mon espace ». Les autres reçoivent
 * un mot de passe provisoire à changer à la première connexion.
 *
 * @param  DoliDB       $db    Handler base
 * @param  User         $user  Utilisateur qui crée l'accès
 * @param  EcoleEmploye $emp   Employé
 * @param  string       $error Message d'erreur (sortie)
 * @return int                 Id de l'accès, <0 si erreur
 */
function espace_creer_employe($db, $user, $emp, &$error)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
	$error = '';
	if (espace_acces_par_user($db, (int) $emp->fk_user)) {
		$error = $langs->trans('AccesExisteDeja');
		return -1;
	}
	$gestion = espace_employe_gestion($db, $emp);
	$mdp = $gestion ? '' : espace_mdp_genere();
	$db->begin();
	if (!$gestion && espace_user_mdp($db, (int) $emp->fk_user, $mdp) < 0) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	$sql = "INSERT INTO ".$db->prefix()."ecole_acces (entity, type, fk_cible, fk_user, mdp_provisoire, mdp_fiche, date_creation, fk_user_creat, status)";
	$sql .= " VALUES (".((int) $conf->entity).", '".ESPACE_EMPLOYE."', ".((int) $emp->id).", ".((int) $emp->fk_user).", ".($gestion ? 0 : 1);
	$sql .= ", ".($gestion ? "NULL" : "'".$db->escape(dolEncrypt($mdp))."'").", '".$db->idate(dol_now())."', ".((int) $user->id).", 1)";
	if (!$db->query($sql)) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	$id = (int) $db->last_insert_id($db->prefix().'ecole_acces');
	$db->commit();
	return $id;
}

/**
 * Nouveau mot de passe provisoire (le parent a oublié le sien) : à changer à la prochaine connexion.
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  object $acces Accès
 * @param  string $error Message d'erreur (sortie)
 * @return int           >0 si OK
 */
function espace_reinitialiser($db, $user, $acces, &$error)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
	$error = '';
	$mdp = espace_mdp_genere();
	$db->begin();
	if (espace_user_mdp($db, (int) $acces->fk_user, $mdp) < 0) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_acces SET mdp_provisoire = 1, mdp_fiche = '".$db->escape(dolEncrypt($mdp))."',";
	$sql .= " date_reinit = '".$db->idate(dol_now())."', fk_user_reinit = ".((int) $user->id)." WHERE rowid = ".((int) $acces->rowid);
	if (!$db->query($sql)) {
		$db->rollback();
		$error = $db->lasterror();
		return -1;
	}
	$db->commit();
	return 1;
}

/**
 * Désactive (0) ou réactive (1) un accès. Le compte Dolibarr suit (désactivé = impossible de se connecter).
 *
 * @param  DoliDB $db     Handler base
 * @param  object $acces  Accès
 * @param  int    $status 0 ou 1
 * @return int            >0 si OK
 */
function espace_set_status($db, $acces, $status)
{
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	$status = $status ? 1 : 0;
	$db->begin();
	$u = new User($db);
	// Employé : seul l'accès à l'espace change (son compte sert aussi à l'interface de gestion et aux salaires)
	if ($acces->type !== ESPACE_EMPLOYE && $u->fetch((int) $acces->fk_user) > 0 && $u->setstatus($status) < 0) {
		$db->rollback();
		return -1;
	}
	if (!$db->query("UPDATE ".$db->prefix()."ecole_acces SET status = ".$status." WHERE rowid = ".((int) $acces->rowid))) {
		$db->rollback();
		return -1;
	}
	$db->commit();
	return 1;
}

/* ------------------------------------------------------------------
 * Adresses de l'espace : adresses propres, sans nom de fichier ni numéro
 * (ex. /espace/eleve/3f9c0a…/notes). Une seule règle de réécriture (fichier .htaccess écrit
 * à l'activation du module, ou configuration du serveur en ligne) les envoie à portail/router.php.
 * ---------------------------------------------------------------- */

/** Début et fin du bloc écrit par le module dans le fichier .htaccess de la racine de Dolibarr */
define('ESPACE_HTACCESS_DEBUT', '# BEGIN espace-parents (module Espace, ne pas modifier)');
define('ESPACE_HTACCESS_FIN', '# END espace-parents');

/**
 * Les adresses propres sont-elles actives (règle de réécriture en place) ?
 *
 * @return bool
 */
function espace_reecriture()
{
	return getDolGlobalString('ESPACE_REECRITURE') === '1';
}

/** Zones de l'espace : parents et élèves (…/espace) et personnel (…/personnel), avec adresses, connexions et sessions séparées */
define('ESPACE_ZONE_PARENTS', 'parents');
define('ESPACE_ZONE_PERSONNEL', 'personnel');

/**
 * Zone de la page en cours : l'entrée portail/personnel.php définit ESPACE_ZONE = 'personnel'.
 *
 * @return string ESPACE_ZONE_PARENTS ou ESPACE_ZONE_PERSONNEL
 */
function espace_zone()
{
	return (defined('ESPACE_ZONE') && ESPACE_ZONE === ESPACE_ZONE_PERSONNEL) ? ESPACE_ZONE_PERSONNEL : ESPACE_ZONE_PARENTS;
}

/**
 * Zone où se connecte un type d'accès (employé : espace du personnel ; responsable, élève : espace parents).
 *
 * @param  string $type Type d'accès
 * @return string
 */
function espace_zone_du_type($type)
{
	return $type === ESPACE_EMPLOYE ? ESPACE_ZONE_PERSONNEL : ESPACE_ZONE_PARENTS;
}

/**
 * Adresse de base d'une zone, sans « / » final.
 * Parents : réglage « Adresse de l'espace » (ex. https://parents.ecole.mr), sinon <adresse de Dolibarr>/espace.
 * Personnel : réglage « Adresse de l'espace du personnel », sinon la même adresse que les parents avec
 * « personnel » à la place du dernier mot (ex. http://localhost/ecoles/espace → http://localhost/ecoles/personnel),
 * ou <adresse de Dolibarr>/personnel si l'espace des parents est à la racine d'un domaine.
 *
 * @param  string $zone Zone ('' = zone de la page en cours)
 * @return string
 */
function espace_base_url($zone = '')
{
	$zone = $zone !== '' ? $zone : espace_zone();
	$url = rtrim(trim(getDolGlobalString('ESPACE_URL')), '/');
	if ($url === '') {
		$url = DOL_MAIN_URL_ROOT.'/espace';
	}
	if ($zone !== ESPACE_ZONE_PERSONNEL) {
		return $url;
	}
	$perso = rtrim(trim(getDolGlobalString('ESPACE_URL_PERSONNEL')), '/');
	if ($perso !== '') {
		return $perso;
	}
	$chemin = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
	if ($chemin === '') {
		return DOL_MAIN_URL_ROOT.'/personnel';
	}
	return substr($url, 0, strlen($url) - strlen(basename($chemin))).'personnel';
}

/**
 * Fichier d'entrée d'une zone (adresse de secours et cible de la règle de réécriture).
 *
 * @param  string $zone Zone
 * @return string       Chemin pour dol_buildpath
 */
function espace_entree($zone)
{
	return $zone === ESPACE_ZONE_PERSONNEL ? '/espace/portail/personnel.php' : '/espace/portail/router.php';
}

/**
 * Adresse d'une page de l'espace.
 * Adresses propres actives : <base>/<route> ; sinon adresse de secours .../router.php?route=<route>
 * (…/personnel.php?route=<route> pour l'espace du personnel).
 *
 * @param  string $route    Route (ex. 'connexion', 'eleve/<jeton>/notes') ; '' = accueil
 * @param  array  $params   Paramètres ajoutés à l'adresse (ex. langue)
 * @param  bool   $absolute true = adresse complète (fiche d'accès, WhatsApp)
 * @param  string $zone     Zone ('' = zone de la page en cours)
 * @return string
 */
function espace_route_url($route, $params = array(), $absolute = false, $zone = '')
{
	$route = trim((string) $route, '/');
	$zone = $zone !== '' ? $zone : espace_zone();
	if (espace_reecriture()) {
		$base = espace_base_url($zone);
		if (!$absolute) {
			$path = parse_url($base, PHP_URL_PATH);
			$base = rtrim((string) $path, '/');
		}
		$url = $base.'/'.$route;
	} else {
		$url = dol_buildpath(espace_entree($zone), $absolute ? 2 : 1);
		$params = array_merge($route !== '' ? array('route' => $route) : array(), $params);
	}
	return $url.(empty($params) ? '' : '?'.http_build_query($params));
}

/**
 * Adresse de l'espace à donner (fiche d'accès, message WhatsApp) : celle des parents et élèves,
 * ou celle du personnel pour un accès employé.
 *
 * @param  string $type Type d'accès ('' = parents et élèves)
 * @return string
 */
function espace_url($type = '')
{
	return espace_route_url('', array(), true, espace_zone_du_type($type));
}

/**
 * Code d'un élève ou d'un reçu dans les adresses de l'espace, à la place de son numéro :
 * toujours le même pour un même élève, impossible à deviner sans la clé secrète de l'installation.
 *
 * @param  string $type 'eleve' ou 'recu'
 * @param  int    $id   Numéro interne
 * @return string       20 caractères (0-9, a-f)
 */
function espace_jeton($type, $id)
{
	global $conf, $dolibarr_main_instance_unique_id;
	$secret = 'espace|'.(string) $dolibarr_main_instance_unique_id.'|'.(int) $conf->entity;
	return substr(hash_hmac('sha256', $type.':'.((int) $id), $secret), 0, 20);
}

/**
 * Retrouve un élément par son code parmi une liste permise (jamais au-delà de ce que l'accès peut voir).
 *
 * @param  string $type   'eleve' ou 'recu'
 * @param  string $jeton  Code lu dans l'adresse
 * @param  int[]  $ids    Numéros permis
 * @return int            Numéro trouvé, 0 sinon
 */
function espace_id_par_jeton($type, $jeton, $ids)
{
	$jeton = strtolower(trim((string) $jeton));
	if (!preg_match('/^[0-9a-f]{20}$/', $jeton)) {
		return 0;
	}
	foreach ($ids as $id) {
		if (hash_equals(espace_jeton($type, $id), $jeton)) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Où poser la règle des adresses propres, d'après l'adresse de l'espace :
 * l'adresse <dossier>/<nom> (ex. http://localhost/ecoles/espace) demande un fichier .htaccess dans le dossier
 * du serveur qui correspond à <dossier> (ex. le dossier « Ecoles », au-dessus de htdocs), avec la règle
 * « <nom>/... → router.php ». Possible seulement si ce dossier contient Dolibarr (même serveur).
 * Adresse à la racine d'un domaine (ex. https://parents.ecole.mr) : règle à mettre dans la configuration du serveur.
 *
 * @return array{dir:string,base:string,segment:string,cible:string}|null  null = pas d'installation automatique possible
 */
function espace_htaccess_cible($zone = ESPACE_ZONE_PARENTS)
{
	$base = espace_base_url($zone);
	$hote = strtolower((string) parse_url($base, PHP_URL_HOST));
	$chemin = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
	$segment = basename($chemin);
	$dossierUrl = rtrim(str_replace('\\', '/', dirname($chemin)), '/');
	$racineUrl = rtrim(DOL_URL_ROOT, '/');
	if ($segment === '' || $hote !== strtolower((string) parse_url(DOL_MAIN_URL_ROOT, PHP_URL_HOST))
		|| ($dossierUrl !== $racineUrl && strpos($racineUrl.'/', $dossierUrl.'/') !== 0)) {
		return null;
	}
	// Dossier du serveur correspondant à <dossier> : on remonte depuis la racine de Dolibarr
	$reste = trim(substr($racineUrl, strlen($dossierUrl)), '/');
	$dir = str_replace('\\', '/', DOL_DOCUMENT_ROOT);
	if ($reste !== '') {
		foreach (explode('/', $reste) as $unused) {
			$dir = dirname($dir);
		}
	}
	$routeur = ltrim(substr(dol_buildpath(espace_entree($zone), 1), strlen($racineUrl)), '/');
	return array(
		'dir' => $dir,
		'base' => $dossierUrl.'/',
		'segment' => $segment,
		'cible' => ($reste !== '' ? $reste.'/' : '').$routeur,
	);
}

/**
 * Zones qui ont une adresse (le personnel seulement si le module Personnel est actif).
 *
 * @return string[]
 */
function espace_zones()
{
	return isModEnabled('personnel') ? array(ESPACE_ZONE_PARENTS, ESPACE_ZONE_PERSONNEL) : array(ESPACE_ZONE_PARENTS);
}

/**
 * Bloc de règles des adresses propres (fichier .htaccess) : une règle par zone, dans le même dossier.
 *
 * @param  array[]|null $cibles Emplacements (espace_htaccess_cible) d'un même dossier ;
 *                              null = racine de Dolibarr, adresses /espace et /personnel
 * @return string
 */
function espace_htaccess_bloc($cibles = null)
{
	$racineUrl = rtrim(DOL_URL_ROOT, '/');
	if (!$cibles) {
		$cibles = array();
		foreach (espace_zones() as $z) {
			$cibles[] = array('base' => $racineUrl.'/', 'segment' => ($z === ESPACE_ZONE_PERSONNEL ? 'personnel' : 'espace'),
				'cible' => ltrim(substr(dol_buildpath(espace_entree($z), 1), strlen($racineUrl)), '/'));
		}
	}
	$regles = '';
	foreach ($cibles as $c) {
		$regles .= "RewriteRule ^".preg_quote($c['segment'], '/')."/?(.*)$ ".$c['cible']."?route=$1 [QSA,L]\n";
	}
	return ESPACE_HTACCESS_DEBUT."\n"
		."<IfModule mod_rewrite.c>\n"
		."RewriteEngine On\n"
		."RewriteBase ".$cibles[0]['base']."\n"
		.$regles
		."</IfModule>\n"
		.ESPACE_HTACCESS_FIN."\n";
}

/**
 * Emplacements des règles de toutes les zones, regroupés par dossier (un bloc par fichier .htaccess).
 * Une zone dont l'adresse n'est pas sur ce serveur n'y figure pas (règle à mettre dans la configuration du serveur).
 *
 * @return array<string,array[]> dossier => emplacements
 */
function espace_htaccess_cibles()
{
	$parDossier = array();
	foreach (espace_zones() as $z) {
		$c = espace_htaccess_cible($z);
		if ($c) {
			$parDossier[rtrim($c['dir'], '/')][] = $c;
		}
	}
	return $parDossier;
}

/**
 * Écrit (ou met à jour) le bloc du module dans un fichier .htaccess sans toucher au reste du fichier.
 *
 * @param  string $dir  Dossier
 * @param  string $bloc Bloc
 * @return bool
 */
function espace_htaccess_ecrire($dir, $bloc)
{
	$file = rtrim($dir, '/').'/.htaccess';
	$contenu = is_file($file) ? (string) file_get_contents($file) : '';
	$motif = '/'.preg_quote(ESPACE_HTACCESS_DEBUT, '/').'.*?'.preg_quote(ESPACE_HTACCESS_FIN, '/').'\R?/s';
	$nouveau = preg_match($motif, $contenu) ? preg_replace($motif, str_replace('$', '\$', $bloc), $contenu) : rtrim($contenu).($contenu !== '' ? "\n\n" : '').$bloc;
	return ($nouveau === $contenu) || (@file_put_contents($file, $nouveau) !== false);
}

/**
 * Les adresses propres répondent-elles vraiment ? Demande <adresse de l'espace>/ping au serveur :
 * la règle peut être écrite mais ignorée (réécriture désactivée, .htaccess interdits, serveur Nginx...).
 *
 * @return bool
 */
function espace_reecriture_tester()
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
	foreach (espace_zones() as $z) {
		$res = getURLContent(espace_base_url($z).'/ping', 'GET', '', 1, array(), array('http', 'https'), 2, -1, 5, 10);
		if (empty($res['content']) || trim((string) $res['content']) !== 'espace-ok') {
			return false;
		}
	}
	return true;
}

/**
 * Installe la règle des adresses propres puis vérifie qu'elle fonctionne vraiment :
 * - bloc du module dans le .htaccess de la racine de Dolibarr (adresse <Dolibarr>/espace, toujours disponible) ;
 * - et, si l'adresse de l'espace est ailleurs sur le même serveur (ex. http://localhost/ecoles/espace),
 *   dans le .htaccess du dossier correspondant.
 * Les adresses propres ne sont activées que si l'adresse de l'espace répond ; sinon adresses de secours.
 * Aucun fichier de Dolibarr n'est modifié.
 *
 * @param  DoliDB $db      Handler base
 * @param  string $message Explication du résultat (sortie, clé de traduction)
 * @return bool            true si les adresses propres sont actives
 */
function espace_htaccess_installer($db, &$message = '')
{
	global $conf;
	$ecrit = espace_htaccess_ecrire(DOL_DOCUMENT_ROOT, espace_htaccess_bloc());
	$cibles = espace_htaccess_cibles();
	foreach ($cibles as $dir => $liste) {
		if ($dir !== rtrim(str_replace('\\', '/', DOL_DOCUMENT_ROOT), '/')) {
			$ecrit = espace_htaccess_ecrire($dir, espace_htaccess_bloc($liste)) && $ecrit;
		}
	}
	$c = count($cibles) > 0;
	$ok = espace_reecriture_tester();
	dolibarr_set_const($db, 'ESPACE_REECRITURE', $ok ? '1' : '0', 'chaine', 0, '', $conf->entity);
	if ($ok) {
		$message = 'AdressesPropresInstallees';
	} elseif (!$ecrit) {
		$message = 'AdressesPropresErreur';
	} elseif (!$c) {
		$message = 'AdressesPropresServeur';
	} else {
		$message = 'AdressesPropresIgnorees';
	}
	return $ok;
}

/**
 * Message par défaut envoyant l'accès (arabe puis français).
 *
 * @param  string $type Type
 * @return string
 */
function espace_message_defaut($type)
{
	if ($type === ESPACE_EMPLOYE) {
		return "السلام عليكم،\nتم إنشاء حسابكم في فضاء موظفي {ecole}.\nالرابط : {lien}\nاسم المستخدم : {identifiant}\nكلمة المرور المؤقتة : {motdepasse}\nيجب تغييرها عند أول دخول.\n\nBonjour,\nVotre accès à l'espace du personnel de {ecole} est prêt.\nAdresse : {lien}\nIdentifiant : {identifiant}\nMot de passe provisoire : {motdepasse}\nVous devrez le changer à la première connexion.";
	}
	if ($type === ESPACE_ELEVE) {
		return "السلام عليكم،\nتم إنشاء حساب التلميذ(ة) {nom_ar} في فضاء التلاميذ الخاص بـ {ecole}.\nالرابط : {lien}\nاسم المستخدم : {identifiant}\nكلمة المرور المؤقتة : {motdepasse}\nيجب تغييرها عند أول دخول.\n\nBonjour,\nL'accès de {nom} à l'espace élèves de {ecole} est prêt.\nAdresse : {lien}\nIdentifiant : {identifiant}\nMot de passe provisoire : {motdepasse}\nIl faudra le changer à la première connexion.";
	}
	return "السلام عليكم،\nتم إنشاء حسابكم في فضاء الأولياء الخاص بـ {ecole}.\nالرابط : {lien}\nاسم المستخدم : {identifiant}\nكلمة المرور المؤقتة : {motdepasse}\nيجب تغييرها عند أول دخول.\n\nBonjour,\nVotre accès à l'espace parents de {ecole} est prêt.\nAdresse : {lien}\nIdentifiant : {identifiant}\nMot de passe provisoire : {motdepasse}\nVous devrez le changer à la première connexion.";
}

/**
 * Constante du message d'un type (modifiable dans la configuration).
 *
 * @param  string $type Type
 * @return string
 */
function espace_message_const($type)
{
	if ($type === ESPACE_EMPLOYE) {
		return 'ESPACE_MSG_EMPLOYE';
	}
	return ($type === ESPACE_ELEVE) ? 'ESPACE_MSG_ELEVE' : 'ESPACE_MSG_PARENT';
}

/**
 * Message WhatsApp prêt à envoyer au responsable avec l'identifiant et le mot de passe provisoire.
 *
 * @param  string $type  Type
 * @param  object $cible Responsable ou élève
 * @param  string $mdp   Mot de passe provisoire
 * @return string
 */
function espace_message($type, $cible, $mdp)
{
	global $mysoc;
	$tpl = getDolGlobalString(espace_message_const($type), espace_message_defaut($type));
	return strtr($tpl, array(
		'{nom}' => (string) $cible->nom_fr,
		'{nom_ar}' => (string) ($cible->nom_ar ? $cible->nom_ar : $cible->nom_fr),
		'{identifiant}' => (string) $cible->ref,
		'{motdepasse}' => $mdp,
		'{lien}' => espace_url($type),
		'{ecole}' => (string) $mysoc->name,
	));
}

/**
 * Lien WhatsApp (wa.me) vers le responsable avec le message d'accès ('' sans numéro).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  Type
 * @param  object $cible Responsable ou élève
 * @param  string $mdp   Mot de passe provisoire
 * @return string
 */
function espace_whatsapp_url($db, $type, $cible, $mdp)
{
	dol_include_once('/eleves/core/lib/discipline.lib.php');
	if ($type === ESPACE_EMPLOYE) {
		$num = eleves_whatsapp_numero((string) $cible->whatsapp, (string) $cible->telephone);
		return $num === '' ? '' : 'https://wa.me/'.$num.'?text='.rawurlencode(espace_message($type, $cible, $mdp));
	}
	$r = espace_responsable_de($db, $type, $cible);
	if (!$r) {
		return '';
	}
	$num = eleves_whatsapp_numero((string) $r->whatsapp, (string) $r->telephone);
	return $num === '' ? '' : 'https://wa.me/'.$num.'?text='.rawurlencode(espace_message($type, $cible, $mdp));
}

/**
 * Onglets de la configuration.
 *
 * @return array
 */
function espace_admin_prepare_head()
{
	global $langs;
	$head = array();
	$head[0][0] = dol_buildpath('/espace/admin/setup.php', 1);
	$head[0][1] = $langs->trans('Reglages');
	$head[0][2] = 'setup';
	return $head;
}

/**
 * Filtres de la liste des accès, lus dans la requête (même lecture pour l'écran et les exports).
 * Dolibarr envoie « -1 » pour le choix vide d'une liste déroulante : il est ignoré.
 *
 * @return array{f:array,param:string}
 */
function espace_liste_filtres()
{
	$f = array(
		'type' => GETPOST('search_type', 'aZ09'),
		'ref' => trim(GETPOST('search_ref', 'alphanohtml')),
		'nom' => trim(GETPOST('search_nom', 'alphanohtml')),
		'eleves' => trim(GETPOST('search_eleves', 'alphanohtml')),
		'etat' => GETPOST('search_etat', 'aZ09'),
		'mdp' => GETPOST('search_mdp', 'aZ09'),
		'conn_min' => GETPOST('search_conn_min', 'alpha') !== '' ? (string) max(0, GETPOSTINT('search_conn_min')) : '',
		'conn_max' => GETPOST('search_conn_max', 'alpha') !== '' ? (string) max(0, GETPOSTINT('search_conn_max')) : '',
	);
	if (!in_array($f['type'], espace_types(), true)) {
		$f['type'] = '';
	}
	if (!in_array($f['etat'], array('actif', 'jamais', 'coupe', 'desactive'), true)) {
		$f['etat'] = '';
	}
	if (!in_array($f['mdp'], array('provisoire', 'personnel'), true)) {
		$f['mdp'] = '';
	}
	$param = '';
	foreach ($f as $k => $v) {
		if ($v !== '') {
			$param .= '&search_'.$k.'='.urlencode((string) $v);
		}
	}
	return array('f' => $f, 'param' => $param);
}

/**
 * Lignes de la liste des accès (avec l'état calculé en SQL : actif, jamais connecté, coupé, désactivé).
 *
 * @param  DoliDB $db     Handler base
 * @param  array  $f      Filtres (espace_liste_filtres)
 * @param  string $sort   Colonne de tri
 * @param  string $order  ASC | DESC
 * @param  int    $limit  0 = tout
 * @param  int    $offset Décalage
 * @param  int    $total  Nombre total (sortie)
 * @return object[]
 */
function espace_acces_liste($db, $f, $sort, $order, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$occ = implode(',', EcoleEleve::statusOccupantPlace());
	$elevesOk = espace_acces_eleves_actif() ? '1' : '0';
	$inner = "SELECT a.rowid, a.type, a.fk_cible, a.fk_user, a.mdp_provisoire, a.date_derniere_connexion, a.nb_connexions, a.date_creation, a.status,";
	$emp = isModEnabled('personnel') && espace_table_existe($db, 'ecole_employe');
	$inner .= " COALESCE(r.ref, el.ref".($emp ? ", em.ref" : "").") as ref, COALESCE(r.nom_fr, el.nom_fr".($emp ? ", em.nom_fr" : "").") as nom_fr";
	$inner .= ", COALESCE(r.nom_ar, el.nom_ar".($emp ? ", em.nom_ar" : "").") as nom_ar,".($emp ? " em.categories," : " NULL as categories,");
	$inner .= " CASE WHEN a.type = 'employe' THEN ".($emp ? "(CASE WHEN em.status <> 4 THEN 1 ELSE 0 END)" : "0");
	$inner .= " ELSE (SELECT COUNT(*) FROM ".$p."ecole_eleve v WHERE v.status IN (".$occ.") AND ((a.type = 'parent' AND v.fk_responsable = a.fk_cible) OR (a.type = 'eleve' AND v.rowid = a.fk_cible))) END as nbvis";
	$inner .= " FROM ".$p."ecole_acces a";
	$inner .= " LEFT JOIN ".$p."ecole_responsable r ON a.type = 'parent' AND r.rowid = a.fk_cible";
	$inner .= " LEFT JOIN ".$p."ecole_eleve el ON a.type = 'eleve' AND el.rowid = a.fk_cible";
	if ($emp) {
		$inner .= " LEFT JOIN ".$p."ecole_employe em ON a.type = 'employe' AND em.rowid = a.fk_cible";
	}
	$inner .= " WHERE a.entity IN (".getEntity('ecole_acces').")";
	$etat = "CASE WHEN t.status = 0 OR (t.type = 'eleve' AND ".$elevesOk." = 0) THEN 'desactive' WHEN t.nbvis = 0 THEN 'coupe' WHEN t.date_derniere_connexion IS NULL THEN 'jamais' ELSE 'actif' END";

	$w = array('1 = 1');
	if ($f['type'] !== '') {
		$w[] = "t.type = '".$db->escape($f['type'])."'";
	}
	if ($f['ref'] !== '') {
		$w[] = natural_search('t.ref', $f['ref'], 0, 1);
	}
	if ($f['nom'] !== '') {
		$w[] = natural_search(array('t.nom_fr', 't.nom_ar'), $f['nom'], 0, 1);
	}
	if ($f['eleves'] !== '') {
		$cond = natural_search(array('s.ref', 's.nom_fr', 's.nom_ar'), $f['eleves'], 0, 1);
		$w[] = "(".natural_search('t.categories', $f['eleves'], 0, 1)." OR EXISTS (SELECT 1 FROM ".$p."ecole_eleve s WHERE s.status IN (".$occ.") AND ((t.type = 'parent' AND s.fk_responsable = t.fk_cible) OR (t.type = 'eleve' AND s.rowid = t.fk_cible)) AND ".$cond."))";
	}
	if ($f['etat'] === 'actif') {
		$w[] = $etat." IN ('actif', 'jamais')";
	} elseif ($f['etat'] !== '') {
		$w[] = $etat." = '".$db->escape($f['etat'])."'";
	}
	if ($f['mdp'] !== '') {
		$w[] = "t.mdp_provisoire = ".($f['mdp'] === 'provisoire' ? 1 : 0);
	}
	if ($f['conn_min'] !== '') {
		$w[] = "t.nb_connexions >= ".((int) $f['conn_min']);
	}
	if ($f['conn_max'] !== '') {
		$w[] = "t.nb_connexions <= ".((int) $f['conn_max']);
	}
	$from = " FROM (".$inner.") t WHERE ".implode(' AND ', $w);

	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$cols = array('type' => 't.type', 'ref' => 't.ref', 'nom' => 't.nom_fr', 'etat' => 'etat', 'conn' => 't.date_derniere_connexion', 'nb' => 't.nb_connexions', 'datec' => 't.date_creation', 'mdp' => 't.mdp_provisoire');
	$sql = "SELECT t.*, ".$etat." as etat".$from;
	$sql .= " ORDER BY ".(isset($cols[$sort]) ? $cols[$sort] : 't.ref')." ".($order === 'DESC' ? 'DESC' : 'ASC').", t.rowid";
	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/**
 * Élèves visibles de chaque ligne de la liste : rowid de l'accès => textes « Nom (classe) ».
 *
 * @param  DoliDB   $db     Handler base
 * @param  object[] $lignes Lignes (espace_acces_liste)
 * @return array<int,string[]>
 */
function espace_liste_eleves($db, $lignes)
{
	$out = array();
	$parents = array();
	$eleves = array();
	foreach ($lignes as $l) {
		if ($l->type === ESPACE_PARENT) {
			$parents[(int) $l->fk_cible][] = (int) $l->rowid;
		} else {
			$eleves[(int) $l->fk_cible][] = (int) $l->rowid;
		}
	}
	$w = array();
	if ($parents) {
		$w[] = "e.fk_responsable IN (".implode(',', array_keys($parents)).")";
	}
	if ($eleves) {
		$w[] = "e.rowid IN (".implode(',', array_keys($eleves)).")";
	}
	if (empty($w)) {
		return $out;
	}
	$sql = "SELECT e.rowid, e.fk_responsable, e.nom_fr, e.nom_ar, c.ref as cref FROM ".$db->prefix()."ecole_eleve e LEFT JOIN ".$db->prefix()."ecole_classe c ON c.rowid = e.fk_classe";
	$sql .= " WHERE e.status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") AND (".implode(' OR ', $w).") ORDER BY e.nom_fr";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$txt = ecole_label($o).($o->cref ? ' ('.$o->cref.')' : '');
		$cibles = array();
		if (isset($parents[(int) $o->fk_responsable])) {
			$cibles = array_merge($cibles, $parents[(int) $o->fk_responsable]);
		}
		if (isset($eleves[(int) $o->rowid])) {
			$cibles = array_merge($cibles, $eleves[(int) $o->rowid]);
		}
		foreach ($cibles as $aid) {
			$out[$aid][] = $txt;
		}
	}
	return $out;
}

/**
 * Jeu de données « Accès à l'espace » (export PDF / Excel, mêmes filtres et même tri que l'écran).
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function espace_export_dataset_acces($db)
{
	global $langs;
	$fl = espace_liste_filtres();
	$total = 0;
	$sort = GETPOST('sortfield', 'aZ09') ? GETPOST('sortfield', 'aZ09') : 'ref';
	$lignes = espace_acces_liste($db, $fl['f'], $sort, GETPOST('sortorder', 'aZ09') === 'DESC' ? 'DESC' : 'ASC', 0, 0, $total);
	$el = espace_liste_eleves($db, $lignes);
	$etats = espace_etats();
	$rows = array();
	$n = 0;
	foreach ($lignes as $l) {
		$types = array(ESPACE_PARENT => 'TypeParent', ESPACE_ELEVE => 'TypeEleve', ESPACE_EMPLOYE => 'TypeEmploye');
		$vis = ($l->type === ESPACE_EMPLOYE && isModEnabled('personnel')) ? array(personnel_categories_texte((string) $l->categories)) : (isset($el[(int) $l->rowid]) ? $el[(int) $l->rowid] : array());
		$rows[] = array((string) (++$n), ecole_pdf_trans($langs, $types[$l->type]), $l->ref, ecole_label($l),
			implode("\n", $vis), ecole_pdf_trans($langs, $etats[$l->etat][0]),
			ecole_pdf_trans($langs, (int) $l->mdp_provisoire ? 'MotDePasseProvisoire' : 'MotDePassePersonnel'),
			$l->date_derniere_connexion ? dol_print_date($db->jdate($l->date_derniere_connexion), 'dayhour') : '', (string) (int) $l->nb_connexions);
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'AccesEspace'),
		'subtitle' => ecole_pdf_trans($langs, 'NbAccesListe', count($rows)),
		'ref' => 'ACCES-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array('#', ecole_pdf_trans($langs, 'TypeAcces'), ecole_pdf_trans($langs, 'Identifiant'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'ElevesVisibles'),
			ecole_pdf_trans($langs, 'EtatAcces'), ecole_pdf_trans($langs, 'MotDePasse'), ecole_pdf_trans($langs, 'DerniereConnexion'), ecole_pdf_trans($langs, 'Connexions')),
		'ratios' => array(0.5, 1, 1.1, 2.4, 3, 1.3, 1.4, 1.6, 0.9),
		'aligns' => array('C', 'C', 'C', 'L', 'L', 'C', 'C', 'C', 'C'),
		'rows' => $rows,
		'filename' => 'acces_espace',
	);
}
