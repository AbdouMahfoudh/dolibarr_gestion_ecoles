<?php
/**
 * Dossier d'un élève.
 *
 * 5 blocs : identité, scolarité, santé + contact d'urgence, documents, responsable.
 * - ref = matricule automatique (format choisi dans la configuration), jamais modifié ensuite ;
 * - rip = identifiant du ministère, facultatif mais unique ;
 * - statuts : pré-inscrit, liste d'attente, inscrit, suspendu, parti/transféré, abandon, exclu ;
 * - classe actuelle dans fk_classe, historique des classes dans llx_ecole_eleve_classe ;
 * - un Tiers Dolibarr (client) est créé et mis à jour automatiquement (sens unique élève → Tiers).
 *
 * Fichier : custom/eleves/class/ecole_eleve.class.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/class/ecole_responsable.class.php');
dol_include_once('/eleves/core/lib/eleves.lib.php');

/**
 * Class EcoleEleve
 */
class EcoleEleve extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_eleve';
	/** @var string */
	public $table_element = 'ecole_eleve';
	/** @var string */
	public $picto = 'fa-user-graduate';
	/** @var string */
	public $dirpage = 'eleve';
	/** @var int Champs supplémentaires ajoutés par l'établissement */
	public $isextrafieldmanaged = 1;

	const STATUS_PREINSCRIT = 0;
	const STATUS_INSCRIT = 1;
	const STATUS_ATTENTE = 2;
	const STATUS_SUSPENDU = 3;
	const STATUS_PARTI = 4;
	const STATUS_ABANDON = 5;
	const STATUS_EXCLU = 6;

	public $required = array('nom_fr', 'date_inscription', 'fk_classe', 'fk_responsable');
	public $unique = array('ref', 'rip');
	public $usage = array();
	public $listcolumns = array('ref', 'numero_appel', 'label', 'sexe', 'date_naissance', 'fk_classe', 'fk_responsable', 'extra:telephone', 'extra:pieces', 'status');
	public $searchcolumns = array('ref', 'label', 'sexe', 'fk_classe');
	public $sortdefault = 'nom_fr';
	public $labelfields = array('nom_fr', 'nom_ar');
	public $labeltitle = 'NomComplet';

	public $ref;
	public $rip;
	public $nom_fr;
	public $nom_ar;
	public $date_naissance;
	public $lieu_naissance;
	public $sexe;
	public $fk_nationalite;
	public $photo;
	public $date_inscription;
	public $ecole_origine;
	public $fk_classe;
	public $numero_appel;
	public $observations;
	public $groupe_sanguin;
	public $allergies;
	public $maladies;
	public $urgence_nom;
	public $urgence_telephone;
	public $urgence_lien;
	public $fk_responsable;
	public $fk_soc;
	public $mois_debut;
	public $reduction_type;
	public $reduction_valeur;
	public $reduction_motif;
	public $frais_inscription_du;
	public $date_statut;
	public $date_validation;
	public $fk_user_valid;

	/**
	 * Champs. La clé « bloc » range chaque champ dans un bloc de la fiche :
	 * identite, scolarite, sante, urgence, responsable ; « technique » = géré par le programme.
	 *
	 * @var array
	 */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Matricule', 'enabled' => '1', 'visible' => 5, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'bloc' => 'scolarite'),
		// Identité
		'nom_fr' => array('type' => 'varchar(255)', 'label' => 'NomCompletFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300', 'bloc' => 'identite'),
		'nom_ar' => array('type' => 'varchar(255)', 'label' => 'NomCompletAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 21, 'searchall' => 1, 'css' => 'minwidth300', 'bloc' => 'identite'),
		'date_naissance' => array('type' => 'date', 'label' => 'DateNaissance', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 22, 'bloc' => 'identite'),
		'lieu_naissance' => array('type' => 'varchar(128)', 'label' => 'LieuNaissance', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 23, 'css' => 'minwidth200', 'bloc' => 'identite'),
		'sexe' => array('type' => 'varchar(1)', 'label' => 'Sexe', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 24, 'bloc' => 'identite',
			'arrayofkeyval' => array('M' => 'SexeM', 'F' => 'SexeF')),
		'fk_nationalite' => array('type' => 'integer', 'label' => 'Nationalite', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 25, 'bloc' => 'identite'),
		'rip' => array('type' => 'varchar(32)', 'label' => 'RIP', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 26, 'searchall' => 1, 'css' => 'maxwidth200', 'help' => 'RIPHelp', 'bloc' => 'identite'),
		'photo' => array('type' => 'varchar(255)', 'label' => 'Photo', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 27, 'bloc' => 'technique'),
		// Scolarité
		'date_inscription' => array('type' => 'date', 'label' => 'DateInscription', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30, 'bloc' => 'scolarite'),
		'fk_classe' => array('type' => 'integer:EcoleClasse:classes/class/ecole_classe.class.php:0:(status:=:1)', 'label' => 'Classe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 31, 'index' => 1, 'css' => 'minwidth200', 'bloc' => 'scolarite'),
		'numero_appel' => array('type' => 'integer', 'label' => 'NumeroAppel', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 31, 'css' => 'maxwidth75', 'help' => 'NumeroAppelHelp', 'bloc' => 'scolarite'),
		'ecole_origine' => array('type' => 'varchar(255)', 'label' => 'EcoleOrigine', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 32, 'css' => 'minwidth300', 'bloc' => 'scolarite'),
		'observations' => array('type' => 'text', 'label' => 'Observations', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 33, 'css' => 'minwidth300', 'bloc' => 'scolarite'),
		// Santé
		'groupe_sanguin' => array('type' => 'varchar(4)', 'label' => 'GroupeSanguin', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 40, 'bloc' => 'sante',
			'arrayofkeyval' => array('A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-')),
		'allergies' => array('type' => 'text', 'label' => 'Allergies', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 41, 'css' => 'minwidth300', 'bloc' => 'sante'),
		'maladies' => array('type' => 'text', 'label' => 'Maladies', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 42, 'css' => 'minwidth300', 'help' => 'MaladiesHelp', 'bloc' => 'sante'),
		'urgence_nom' => array('type' => 'varchar(255)', 'label' => 'UrgenceNom', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 45, 'css' => 'minwidth300', 'bloc' => 'urgence'),
		'urgence_telephone' => array('type' => 'phone', 'label' => 'UrgenceTelephone', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 46, 'css' => 'minwidth200', 'bloc' => 'urgence'),
		'urgence_lien' => array('type' => 'varchar(64)', 'label' => 'UrgenceLien', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 47, 'css' => 'minwidth200', 'bloc' => 'urgence'),
		// Responsable
		'fk_responsable' => array('type' => 'integer:EcoleResponsable:eleves/class/ecole_responsable.class.php', 'label' => 'Responsable', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 50, 'index' => 1, 'bloc' => 'responsable',
			'searchtext' => array('table' => 'ecole_responsable', 'cols' => array('ref', 'nom_fr', 'nom_ar'))), // filtre de la liste : nom ou matricule du responsable
		// Géré par le programme
		'fk_soc' => array('type' => 'integer', 'label' => 'ThirdParty', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 60, 'bloc' => 'technique'),
		// Paiements (onglet Paiements, droit « régler les réductions »)
		'mois_debut' => array('type' => 'varchar(7)', 'label' => 'PremierMoisDu', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 61, 'bloc' => 'finance'),
		'reduction_type' => array('type' => 'varchar(8)', 'label' => 'TypeReduction', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 62, 'bloc' => 'finance',
			'arrayofkeyval' => array('montant' => 'ReductionMontant', 'pourcent' => 'ReductionPourcent')),
		'reduction_valeur' => array('type' => 'double(24,8)', 'label' => 'ValeurReduction', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 63, 'bloc' => 'finance'),
		'reduction_motif' => array('type' => 'varchar(255)', 'label' => 'MotifReduction', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 64, 'bloc' => 'finance'),
		'frais_inscription_du' => array('type' => 'double(24,8)', 'label' => 'FraisInscriptionDu', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 65, 'bloc' => 'finance'),
		'date_statut' => array('type' => 'date', 'label' => 'DateStatut', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 64, 'bloc' => 'technique'),
		'date_validation' => array('type' => 'datetime', 'label' => 'DateValidationInscription', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 65, 'bloc' => 'technique'),
		'fk_user_valid' => array('type' => 'integer', 'label' => 'UserValidation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 66, 'bloc' => 'technique'),
	);

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_PREINSCRIT;
	}

	/**
	 * Champs techniques : le statut de l'élève remplace « actif / inactif ».
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		$f['status']['default'] = '0';
		$f['status']['label'] = 'StatutEleve';
		$f['status']['arrayofkeyval'] = array();
		foreach (self::statusLabels() as $k => $v) {
			$f['status']['arrayofkeyval'][$k] = $v[0];
		}
		return $f;
	}

	/* ------------------------------------------------------------------
	 * Statuts
	 * ---------------------------------------------------------------- */

	/**
	 * Statuts : valeur => [clé de traduction, couleur Dolibarr].
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function statusLabels()
	{
		return array(
			self::STATUS_PREINSCRIT => array('StatutPreinscrit', 'status0'),
			self::STATUS_ATTENTE => array('StatutAttente', 'status1'),
			self::STATUS_INSCRIT => array('StatutInscrit', 'status4'),
			self::STATUS_SUSPENDU => array('StatutSuspendu', 'status7'),
			self::STATUS_PARTI => array('StatutParti', 'status6'),
			self::STATUS_ABANDON => array('StatutAbandon', 'status5'),
			self::STATUS_EXCLU => array('StatutExclu', 'status8'),
		);
	}

	/**
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		$out = array();
		foreach (self::statusLabels() as $k => $v) {
			$out[$k] = $v[0];
		}
		return $out;
	}

	/**
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('eleves@eleves');
		$all = self::statusLabels();
		$s = isset($all[(int) $status]) ? $all[(int) $status] : array('Unknown', 'status0');
		$label = $langs->transnoentitiesnoconv($s[0]);
		return dolGetStatus($label, $label, '', $s[1], $mode);
	}

	/**
	 * Changements de statut autorisés (hors validation de l'inscription, qui a ses propres contrôles) :
	 * statut actuel => statuts possibles.
	 *
	 * @return array<int,int[]>
	 */
	public static function transitions()
	{
		return array(
			self::STATUS_PREINSCRIT => array(self::STATUS_ATTENTE, self::STATUS_ABANDON),
			self::STATUS_ATTENTE => array(self::STATUS_PREINSCRIT, self::STATUS_ABANDON),
			self::STATUS_INSCRIT => array(self::STATUS_SUSPENDU, self::STATUS_PARTI, self::STATUS_ABANDON, self::STATUS_EXCLU),
			self::STATUS_SUSPENDU => array(self::STATUS_INSCRIT, self::STATUS_PARTI, self::STATUS_ABANDON, self::STATUS_EXCLU),
			self::STATUS_PARTI => array(self::STATUS_PREINSCRIT),
			self::STATUS_ABANDON => array(self::STATUS_PREINSCRIT),
			self::STATUS_EXCLU => array(self::STATUS_PREINSCRIT),
		);
	}

	/**
	 * Statuts qui occupent une place dans la classe (effectif).
	 *
	 * @return int[]
	 */
	public static function statusOccupantPlace()
	{
		return array(self::STATUS_INSCRIT, self::STATUS_SUSPENDU);
	}

	/**
	 * Statuts d'un élève qui a quitté l'école (sa période dans la classe est close).
	 *
	 * @return int[]
	 */
	public static function statusSortis()
	{
		return array(self::STATUS_PARTI, self::STATUS_ABANDON, self::STATUS_EXCLU);
	}

	/* ------------------------------------------------------------------
	 * Numéro d'appel (numéro de l'élève dans sa classe : 1, 2, 3...)
	 * ---------------------------------------------------------------- */

	/**
	 * Statuts des élèves qui gardent un numéro dans la classe (tous sauf les sortis).
	 *
	 * @return string Liste SQL
	 */
	protected static function sqlStatutsNumerotes()
	{
		return implode(',', array(self::STATUS_PREINSCRIT, self::STATUS_INSCRIT, self::STATUS_ATTENTE, self::STATUS_SUSPENDU));
	}

	/**
	 * Élève (non sorti) qui a déjà ce numéro dans la classe.
	 *
	 * @param  DoliDB $db        Handler base
	 * @param  int    $fk_classe Classe
	 * @param  int    $numero    Numéro
	 * @param  int    $exclude   Élève à ignorer
	 * @return object|null       Ligne (rowid, ref, nom_fr, nom_ar)
	 */
	public static function eleveAvecNumero($db, $fk_classe, $numero, $exclude = 0)
	{
		$sql = "SELECT rowid, ref, nom_fr, nom_ar FROM ".$db->prefix()."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').")";
		$sql .= " AND fk_classe = ".((int) $fk_classe)." AND numero_appel = ".((int) $numero)." AND rowid <> ".((int) $exclude);
		$sql .= " AND status IN (".self::sqlStatutsNumerotes().")";
		$resql = $db->query($sql);
		return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}

	/**
	 * Prochain numéro d'appel libre d'une classe (plus grand numéro + 1).
	 *
	 * @param  DoliDB $db        Handler base
	 * @param  int    $fk_classe Classe
	 * @param  int    $exclude   Élève à ignorer
	 * @return int
	 */
	public static function prochainNumeroAppel($db, $fk_classe, $exclude = 0)
	{
		$sql = "SELECT MAX(numero_appel) as maxi FROM ".$db->prefix()."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').")";
		$sql .= " AND fk_classe = ".((int) $fk_classe)." AND rowid <> ".((int) $exclude)." AND status IN (".self::sqlStatutsNumerotes().")";
		$resql = $db->query($sql);
		$o = $resql ? $db->fetch_object($resql) : null;
		return ($o ? (int) $o->maxi : 0) + 1;
	}

	/**
	 * Donne à l'élève le prochain numéro libre de sa classe.
	 *
	 * @return int 1 si OK, -1 sinon
	 */
	public function attribuerNumeroAppel()
	{
		$num = self::prochainNumeroAppel($this->db, (int) $this->fk_classe, (int) $this->id);
		if (!$this->db->query("UPDATE ".$this->db->prefix().$this->table_element." SET numero_appel = ".$num." WHERE rowid = ".((int) $this->id))) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->numero_appel = $num;
		return 1;
	}

	/**
	 * Renumérote une classe par ordre alphabétique (1, 2, 3...) : élèves inscrits et suspendus d'abord,
	 * puis pré-inscrits et en liste d'attente. Les élèves sortis perdent leur numéro.
	 *
	 * @param  DoliDB $db        Handler base
	 * @param  int    $fk_classe Classe
	 * @return int               Nombre d'élèves numérotés, -1 si erreur
	 */
	public static function renumeroterClasse($db, $fk_classe)
	{
		$p = $db->prefix();
		$db->begin();
		$ok = (bool) $db->query("UPDATE ".$p."ecole_eleve SET numero_appel = NULL WHERE fk_classe = ".((int) $fk_classe)." AND entity IN (".getEntity('ecole_eleve').")");
		$sql = "SELECT rowid FROM ".$p."ecole_eleve WHERE fk_classe = ".((int) $fk_classe)." AND entity IN (".getEntity('ecole_eleve').") AND status IN (".self::sqlStatutsNumerotes().")";
		$sql .= " ORDER BY (status IN (".self::STATUS_INSCRIT.", ".self::STATUS_SUSPENDU.")) DESC, nom_fr, rowid";
		$resql = $ok ? $db->query($sql) : false;
		$n = 0;
		$ids = array();
		while ($resql && ($o = $db->fetch_object($resql))) {
			$ids[] = (int) $o->rowid;
		}
		foreach ($ids as $id) {
			$n++;
			$ok = $ok && $db->query("UPDATE ".$p."ecole_eleve SET numero_appel = ".$n." WHERE rowid = ".$id);
		}
		if (!$ok || !$resql) {
			$db->rollback();
			return -1;
		}
		$db->commit();
		return $n;
	}

	/* ------------------------------------------------------------------
	 * Matricule
	 * ---------------------------------------------------------------- */

	/**
	 * Construit un matricule : préfixe + année facultative (AA / AAAA) + compteur sur N chiffres.
	 *
	 * @param  int    $numero   Numéro du compteur
	 * @param  string $prefixe  Préfixe
	 * @param  string $annee    '', 'AA' ou 'AAAA'
	 * @param  int    $longueur Nombre de chiffres du compteur
	 * @param  int    $date     Date (horodatage) pour l'année
	 * @return string
	 */
	public static function formatMatricule($numero, $prefixe, $annee, $longueur, $date)
	{
		$y = ($annee === 'AAAA') ? dol_print_date($date, '%Y') : (($annee === 'AA') ? dol_print_date($date, '%y') : '');
		return $prefixe.$y.str_pad((string) ((int) $numero), max(1, (int) $longueur), '0', STR_PAD_LEFT);
	}

	/**
	 * Prochain matricule selon la configuration. Le compteur augmente à chaque élève et
	 * n'est jamais remis à zéro (il repart du plus grand numéro déjà attribué).
	 *
	 * @param  int $date Date d'inscription (pour l'année éventuelle)
	 * @return string
	 */
	public function getNextMatricule($date = 0)
	{
		$len = max(1, getDolGlobalInt('ELEVES_MATRICULE_LONGUEUR', 5));
		$sql = "SELECT MAX(CAST(RIGHT(ref, ".$len.") AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{".$len."}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		$next = max(($o ? (int) $o->maxi : 0) + 1, getDolGlobalInt('ELEVES_MATRICULE_DEBUT', 1));
		return self::formatMatricule($next, getDolGlobalString('ELEVES_MATRICULE_PREFIXE'), getDolGlobalString('ELEVES_MATRICULE_ANNEE'), $len, $date ? $date : dol_now());
	}

	/* ------------------------------------------------------------------
	 * Création, modification, suppression
	 * ---------------------------------------------------------------- */

	/**
	 * Validation des champs.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		$langs->load('eleves@eleves');

		$this->rip = trim((string) $this->rip);
		if ($this->rip === '') {
			$this->rip = null; // facultatif : plusieurs élèves sans RIP
		}
		if ($this->sexe === '' || $this->sexe === '-1') {
			$this->sexe = null;
		}
		if (empty($this->fk_nationalite) || (int) $this->fk_nationalite < 0) {
			$this->fk_nationalite = null;
		}
		foreach (array('groupe_sanguin', 'urgence_nom', 'urgence_telephone', 'urgence_lien', 'ecole_origine', 'lieu_naissance', 'nom_ar') as $f) {
			if (is_string($this->$f)) {
				$this->$f = trim($this->$f);
			}
		}

		parent::validate();

		if (!empty($this->sexe) && !isset($this->fields['sexe']['arrayofkeyval'][$this->sexe])) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Sexe'));
		}
		if (!empty($this->groupe_sanguin) && !isset($this->fields['groupe_sanguin']['arrayofkeyval'][$this->groupe_sanguin])) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('GroupeSanguin'));
		}
		if (!empty($this->date_naissance) && $this->date_naissance > dol_now()) {
			$this->errors[] = $langs->trans('ErrorDateNaissanceFuture');
		}
		// Une nouvelle classe doit être active
		if ((int) $this->fk_classe > 0 && (int) $this->getStoredValue('fk_classe') !== (int) $this->fk_classe) {
			$classe = new EcoleClasse($this->db);
			if ($classe->fetch((int) $this->fk_classe) <= 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Classe'));
			} elseif ((int) $classe->status !== EcoleClasse::STATUS_ACTIVE) {
				$this->errors[] = $langs->trans('ErrorClasseInactive', $classe->ref);
			}
		}
		// Numéro d'appel : facultatif (donné automatiquement à l'inscription), unique dans la classe
		if ($this->numero_appel === '' || $this->numero_appel === null) {
			$this->numero_appel = null;
		} elseif ((int) $this->numero_appel <= 0 || (int) $this->numero_appel > 999) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('NumeroAppel'));
		} elseif ((int) $this->fk_classe > 0) {
			$autre = self::eleveAvecNumero($this->db, (int) $this->fk_classe, (int) $this->numero_appel, (int) $this->id);
			if ($autre) {
				$this->errors[] = $langs->trans('ErrorNumeroAppelPris', (int) $this->numero_appel, $autre->ref.' '.ecole_label($autre));
			}
		}
		if ((int) $this->fk_responsable > 0) {
			$resp = new EcoleResponsable($this->db);
			if ($resp->fetch((int) $this->fk_responsable) <= 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Responsable'));
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Crée l'élève (pré-inscrit) : matricule automatique, historique de classe et de statut, Tiers.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = id, <0 = erreur
	 */
	public function create(User $user, $notrigger = 0)
	{
		if (empty($this->date_inscription)) {
			$this->date_inscription = dol_now();
		}
		$this->ref = $this->getNextMatricule($this->date_inscription);
		$this->status = self::STATUS_PREINSCRIT;
		$this->date_statut = $this->date_inscription;

		if ($this->validate() < 0) {
			return -1;
		}

		$this->db->begin();
		$res = $this->createCommon($user, $notrigger);
		if ($res > 0 && empty($this->numero_appel)) {
			$res = $this->attribuerNumeroAppel(); // dernier numéro de la classe + 1
		}
		if ($res > 0) {
			$res = $this->addHistoriqueClasse($user, (int) $this->fk_classe, $this->date_inscription, '');
		}
		if ($res > 0) {
			$res = $this->addHistoriqueStatut($user, null, self::STATUS_PREINSCRIT, $this->date_inscription, '');
		}
		if ($res > 0) {
			$res = $this->syncTiers($user);
		}
		if ($res > 0) {
			$this->db->commit();
			return $this->id;
		}
		$this->db->rollback();
		$this->id = 0;
		return -1;
	}

	/**
	 * Enregistre les modifications et met à jour le Tiers. Tant que l'élève n'est pas inscrit,
	 * la classe demandée peut être corrigée ici ; ensuite il faut « Changer de classe » (historique).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = OK, <0 = erreur
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $langs;
		$langs->load('eleves@eleves');

		$oldclasse = (int) $this->getStoredValue('fk_classe');
		$classechange = ($oldclasse !== (int) $this->fk_classe);
		if ($classechange && !in_array((int) $this->status, array(self::STATUS_PREINSCRIT, self::STATUS_ATTENTE), true)) {
			$this->error = $langs->trans('ErrorUtiliserChangerClasse');
			$this->errors = array($this->error);
			return -1;
		}
		if ($classechange && (string) $this->numero_appel === (string) $this->getStoredValue('numero_appel')) {
			$this->numero_appel = null; // nouveau numéro dans la nouvelle classe (donné ci-dessous)
		}
		if ($this->validate() < 0) {
			return -1;
		}

		$this->db->begin();
		$res = $this->updateCommon($user, $notrigger);
		if ($res > 0 && empty($this->numero_appel)) {
			$res = $this->attribuerNumeroAppel();
		}
		if ($res > 0 && $classechange) {
			// Classe demandée corrigée avant l'inscription : la période en cours change de classe
			$sql = "UPDATE ".$this->db->prefix()."ecole_eleve_classe SET fk_classe = ".((int) $this->fk_classe);
			$sql .= " WHERE fk_eleve = ".((int) $this->id)." AND date_fin IS NULL";
			$res = $this->db->query($sql) ? 1 : -1;
		}
		if ($res > 0) {
			$res = $this->syncTiers($user);
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Causes qui empêchent la suppression : paiements (factures du Tiers), notes, absences et retards, sanctions,
	 * bulletins. Un élève qui a servi ne se supprime pas : on change son statut (parti, abandon, exclu...).
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$langs->load('eleves@eleves');

		$items = array();
		$nb = $this->countFactures();
		if ($nb > 0) {
			$items[] = $langs->transnoentitiesnoconv('EleveUsagePaiements').' ('.$nb.')';
		}
		$plus = array(
			array('ecole_note', 'EleveUsageNotes'),
			array('ecole_absence', 'EleveUsageAbsences'),
			array('ecole_sanction', 'EleveUsageSanctions'),
			array('ecole_bulletin', 'EleveUsageBulletins'),
		);
		foreach ($plus as $u) {
			$nb = $this->countUsage($u[0], 'fk_eleve');
			if ($nb > 0) {
				$items[] = $langs->transnoentitiesnoconv($u[1]).' ('.$nb.')';
			}
		}
		return $items ? array($langs->trans('ErrorEleveUtilise', implode(', ', $items))) : array();
	}

	/**
	 * Suppression : impossible si l'élève a déjà des paiements, notes, absences, sanctions ou bulletins.
	 * Supprime aussi le Tiers, les historiques, les pièces et les fichiers.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = OK, <0 = erreur
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf, $langs;
		$langs->load('eleves@eleves');

		$this->errors = $this->getDeleteBlockers();
		if (!empty($this->errors)) {
			$this->error = implode('<br>', $this->errors);
			return -1;
		}

		$p = $this->db->prefix();
		$this->db->begin();
		$ok = $this->db->query("DELETE FROM ".$p."ecole_eleve_classe WHERE fk_eleve = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_eleve_statut WHERE fk_eleve = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_eleve_document WHERE fk_eleve = ".((int) $this->id));
		if ($ok && $this->deleteCommon($user, $notrigger) <= 0) {
			$ok = false;
		}
		if ($ok && $this->fk_soc > 0) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$soc = new Societe($this->db);
			if ($soc->fetch((int) $this->fk_soc) > 0 && $soc->delete((int) $this->fk_soc, $user) <= 0) {
				$this->error = $langs->trans('ErrorSuppressionTiers').' '.$soc->error;
				$this->errors = array_merge(array($this->error), (array) $soc->errors);
				$ok = false;
			}
		}
		if (!$ok) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		// Fichiers (photo, pièces scannées)
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		foreach (array('eleve', 'document') as $part) {
			$dir = $conf->eleves->dir_output.'/'.$part.'/'.dol_sanitizeFileName($this->ref);
			if (is_dir($dir)) {
				dol_delete_dir_recursive($dir);
			}
		}
		return 1;
	}

	/* ------------------------------------------------------------------
	 * Tiers Dolibarr (sens unique : fiche élève → Tiers)
	 * ---------------------------------------------------------------- */

	/**
	 * Crée ou met à jour le Tiers client de l'élève : nom de l'élève (nom arabe en nom alternatif),
	 * code client = matricule, coordonnées du responsable, étiquette « Élève ».
	 *
	 * @param  User $user Utilisateur
	 * @return int        1 si OK, -1 sinon
	 */
	public function syncTiers(User $user)
	{
		global $langs, $mysoc;
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		$langs->loadLangs(array('eleves@eleves', 'companies', 'errors'));

		$resp = new EcoleResponsable($this->db);
		if ($resp->fetch((int) $this->fk_responsable) <= 0) {
			$this->error = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Responsable'));
			$this->errors = array($this->error);
			return -1;
		}

		$soc = new Societe($this->db);
		$exists = ($this->fk_soc > 0 && $soc->fetch((int) $this->fk_soc) > 0);
		if ($exists) {
			$soc->oldcopy = clone $soc; // le code client (matricule) reste accepté même s'il ne suit pas la numérotation Dolibarr
		}
		$soc->name = $this->nom_fr;
		$soc->name_alias = (string) $this->nom_ar;
		$soc->client = 1;
		$soc->fournisseur = 0;
		$soc->status = 1;
		$soc->phone = (string) $resp->telephone;
		$soc->phone_mobile = (string) $resp->whatsapp;
		$soc->email = (string) $resp->email;
		$soc->address = (string) $resp->adresse;
		if (empty($soc->country_id) && is_object($mysoc)) {
			$soc->country_id = $mysoc->country_id;
		}

		if ($exists) {
			// code client laissé tel quel pour la vérification Dolibarr, remis au matricule juste après
			$res = $soc->update($soc->id, $user, 1, 0, 0, 'update', 1);
		} else {
			$soc->code_client = ''; // posé juste après : le matricule ne suit pas forcément la numérotation des codes clients
			$res = $soc->create($user);
			if ($res > 0) {
				$this->fk_soc = $soc->id;
				$res = $this->db->query("UPDATE ".$this->db->prefix().$this->table_element." SET fk_soc = ".((int) $soc->id)." WHERE rowid = ".((int) $this->id)) ? 1 : -1;
			}
		}
		if ($res > 0) {
			// Code client = matricule (unique parmi les Tiers)
			$sql = "SELECT rowid FROM ".$this->db->prefix()."societe WHERE code_client = '".$this->db->escape($this->ref)."' AND rowid <> ".((int) $soc->id);
			$sql .= " AND entity IN (".getEntity('societe').")";
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$this->error = $langs->trans('ErrorCodeClientDejaUtilise', $this->ref);
				$this->errors = array($this->error);
				return -1;
			}
			$res = $this->db->query("UPDATE ".$this->db->prefix()."societe SET code_client = '".$this->db->escape($this->ref)."' WHERE rowid = ".((int) $soc->id)) ? 1 : -1;
		}
		if ($res > 0) {
			$catid = eleves_categorie_tiers($this->db, $user);
			if ($catid > 0) {
				$resql = $this->db->query("SELECT fk_categorie FROM ".$this->db->prefix()."categorie_societe WHERE fk_categorie = ".((int) $catid)." AND fk_soc = ".((int) $soc->id));
				if ($resql && !$this->db->num_rows($resql)) {
					$cat = new Categorie($this->db);
					if ($cat->fetch($catid) > 0 && $cat->add_type($soc, Categorie::TYPE_CUSTOMER) < 0) {
						$res = -1;
						$soc->error = $cat->error;
					}
				}
			}
		}
		if ($res <= 0) {
			$this->error = $langs->trans('ErrorSyncTiers').' '.($soc->error ? $langs->trans($soc->error) : '');
			foreach ((array) $soc->errors as $e) {
				$this->error .= ' '.$langs->trans($e);
			}
			$this->errors = array($this->error);
			return -1;
		}
		return 1;
	}

	/**
	 * Nombre de factures Dolibarr (comptabilité) du Tiers de l'élève : s'il y en a, le dossier ne se supprime pas.
	 *
	 * @return int
	 */
	public function countFactures()
	{
		if (empty($this->fk_soc)) {
			return 0;
		}
		$resql = $this->db->query("SELECT COUNT(*) as nb FROM ".$this->db->prefix()."facture WHERE fk_soc = ".((int) $this->fk_soc));
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return $o ? (int) $o->nb : 0;
	}

	/* ------------------------------------------------------------------
	 * Inscription et statuts
	 * ---------------------------------------------------------------- */

	/**
	 * Frais d'inscription réglés pour pouvoir valider l'inscription : au moins un paiement (même partiel,
	 * le reste va dans les impayés), ou aucun frais demandé pour la classe.
	 *
	 * @return bool
	 */
	public function fraisInscriptionPayes()
	{
		dol_include_once('/eleves/core/lib/paiements.lib.php');
		$s = eleves_situation($this->db, $this);
		return $s['inscription']['du'] <= 0 || $s['inscription']['paye'] > 0;
	}

	/**
	 * Places de la classe : effectif maximum (100 par défaut quand la classe n'en fixe pas), places occupées
	 * (inscrits + suspendus), places libres.
	 *
	 * @param  DoliDB $db        Handler base
	 * @param  int    $fk_classe Classe
	 * @param  int    $exclude   Élève à ne pas compter
	 * @return array{max:int|null,occupees:int,libres:int|null}
	 */
	public static function placesClasse($db, $fk_classe, $exclude = 0)
	{
		$max = EcoleClasse::effectifMaxDefaut();
		$resql = $db->query("SELECT effectif_max FROM ".$db->prefix()."ecole_classe WHERE rowid = ".((int) $fk_classe));
		if ($resql && ($o = $db->fetch_object($resql)) && $o->effectif_max !== null && (int) $o->effectif_max > 0) {
			$max = (int) $o->effectif_max;
		}
		$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_eleve WHERE fk_classe = ".((int) $fk_classe);
		$sql .= " AND status IN (".implode(',', self::statusOccupantPlace()).")";
		if ($exclude > 0) {
			$sql .= " AND rowid <> ".((int) $exclude);
		}
		$resql = $db->query($sql);
		$o = $resql ? $db->fetch_object($resql) : null;
		$occ = $o ? (int) $o->nb : 0;
		return array('max' => $max, 'occupees' => $occ, 'libres' => ($max === null ? null : max(0, $max - $occ)));
	}

	/**
	 * Conditions pour valider l'inscription (pré-inscrit / liste d'attente → inscrit).
	 *
	 * @return array{ok:bool,place:bool,raisons:string[]} raisons = messages traduits des conditions non remplies
	 */
	public function checkValidation()
	{
		global $langs;
		$langs->load('eleves@eleves');
		$raisons = array();
		if (!in_array((int) $this->status, array(self::STATUS_PREINSCRIT, self::STATUS_ATTENTE), true)) {
			$raisons[] = $langs->trans('ErrorStatutNonValidable');
		}
		if (empty($this->fk_responsable)) {
			$raisons[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Responsable'));
		}
		if (!$this->fraisInscriptionPayes()) {
			$raisons[] = $langs->trans('ErrorFraisInscriptionNonPayes');
		}
		$places = self::placesClasse($this->db, (int) $this->fk_classe, (int) $this->id);
		$place = ($places['libres'] === null || $places['libres'] > 0);
		if (!$place) {
			$raisons[] = $langs->trans('ErrorClassePleine', ecole_row_label($this->db, 'ecole_classe', $this->fk_classe), $places['max']);
		}
		return array('ok' => empty($raisons), 'place' => $place, 'raisons' => $raisons);
	}

	/**
	 * Valide l'inscription (décision de la direction) : l'élève devient « inscrit ».
	 *
	 * @param  User   $user  Utilisateur
	 * @param  int    $date  Date d'effet
	 * @param  string $motif Commentaire
	 * @return int           1 si OK, -1 sinon
	 */
	public function valider(User $user, $date, $motif = '')
	{
		if (!$this->checkId()) {
			return -1;
		}
		$check = $this->checkValidation();
		if (!$check['ok']) {
			$this->errors = $check['raisons'];
			$this->error = implode('<br>', $check['raisons']);
			return -1;
		}
		$this->db->begin();
		$res = $this->enregistrerStatut($user, self::STATUS_INSCRIT, $date, $motif);
		if ($res > 0) {
			$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET date_validation = '".$this->db->idate(dol_now())."', fk_user_valid = ".((int) $user->id);
			$sql .= " WHERE rowid = ".((int) $this->id);
			$res = $this->db->query($sql) ? 1 : -1;
		}
		if ($res > 0) {
			$this->db->commit();
			$this->date_validation = dol_now();
			$this->fk_user_valid = $user->id;
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Change le statut (liste d'attente, suspension, départ, abandon, exclusion, réintégration, réinscription).
	 *
	 * @param  User   $user   Utilisateur
	 * @param  int    $status Nouveau statut
	 * @param  int    $date   Date d'effet
	 * @param  string $motif  Motif
	 * @return int            1 si OK, -1 sinon
	 */
	public function changerStatut(User $user, $status, $date, $motif = '')
	{
		if (!$this->checkId()) {
			return -1;
		}
		global $langs;
		$langs->load('eleves@eleves');
		$trans = self::transitions();
		if (!isset($trans[(int) $this->status]) || !in_array((int) $status, $trans[(int) $this->status], true)) {
			$this->error = $langs->trans('ErrorChangementStatutImpossible', $this->getLibStatut(1), $this->LibStatut($status, 1));
			$this->errors = array($this->error);
			return -1;
		}
		if (empty($date)) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('DateEffet'));
			$this->errors = array($this->error);
			return -1;
		}
		$this->db->begin();
		$res = $this->enregistrerStatut($user, (int) $status, $date, $motif);
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Enregistre un statut (sans contrôle) : statut, historique, période de classe close / rouverte.
	 *
	 * @param  User   $user   Utilisateur
	 * @param  int    $status Nouveau statut
	 * @param  int    $date   Date d'effet
	 * @param  string $motif  Motif
	 * @return int            1 si OK, -1 sinon
	 */
	protected function enregistrerStatut(User $user, $status, $date, $motif)
	{
		$old = (int) $this->status;
		$p = $this->db->prefix();
		$sql = "UPDATE ".$p.$this->table_element." SET status = ".((int) $status).", date_statut = '".$this->db->idate($date)."', fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$res = $this->addHistoriqueStatut($user, $old, (int) $status, $date, $motif);

		$sorti = in_array((int) $status, self::statusSortis(), true);
		$etaitsorti = in_array($old, self::statusSortis(), true);
		if ($res > 0 && $sorti && !$etaitsorti) {
			// Départ : la période dans la classe se termine à la date de départ
			$sql = "UPDATE ".$p."ecole_eleve_classe SET date_fin = '".$this->db->idate($date)."'";
			$sql .= " WHERE fk_eleve = ".((int) $this->id)." AND date_fin IS NULL";
			$res = $this->db->query($sql) ? 1 : -1;
		} elseif ($res > 0 && $etaitsorti && !$sorti) {
			// Retour après un départ : nouvelle période dans la classe et nouveau numéro d'appel
			$res = $this->addHistoriqueClasse($user, (int) $this->fk_classe, $date, $motif);
			if ($res > 0) {
				$this->status = (int) $status;
				$res = $this->attribuerNumeroAppel();
			}
		}
		if ($res > 0) {
			$this->status = (int) $status;
			$this->date_statut = $date;
		}
		return $res;
	}

	/**
	 * Enregistre les réglages de paiement de l'élève : premier mois dû, réduction (montant ou %, motif),
	 * frais d'inscription particuliers (vide = ceux de la classe).
	 *
	 * @param  User        $user   Utilisateur
	 * @param  string      $mois   Premier mois dû AAAA-MM ('' = mois d'inscription)
	 * @param  string      $type   '' | montant | pourcent
	 * @param  float|null  $valeur Valeur de la réduction
	 * @param  string      $motif  Motif de la réduction
	 * @param  float|null  $frais  Frais d'inscription particuliers (null = ceux de la classe)
	 * @return int                 1 si OK, -1 sinon
	 */
	public function setReglagesPaiement(User $user, $mois, $type, $valeur, $motif, $frais)
	{
		global $langs;
		$langs->load('eleves@eleves');
		if (!$this->checkId()) {
			return -1;
		}
		$this->errors = array();
		if ($mois !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('PremierMoisDu'));
		} elseif ($mois !== '') {
			// Le premier mois dû est un mois payant : pas avant le premier mois de la configuration (octobre par défaut)
			dol_include_once('/eleves/core/lib/paiements.lib.php');
			$periodes = eleves_periodes();
			if (!in_array($mois, $periodes, true)) {
				$this->errors[] = $langs->trans('ErrorPremierMoisPayant', eleves_periode_label(reset($periodes)), eleves_periode_label(end($periodes)));
			}
		}
		if (!in_array($type, array('', 'montant', 'pourcent'), true)) {
			$type = '';
		}
		if ($type !== '' && ($valeur === null || $valeur === '' || (float) $valeur <= 0 || ($type === 'pourcent' && (float) $valeur > 100))) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('ValeurReduction'));
		}
		if ($type !== '' && trim((string) $motif) === '') {
			$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifReduction'));
		}
		if ($frais !== null && $frais !== '' && (float) $frais < 0) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('FraisInscriptionDu'));
		}
		if ($this->finishValidation() < 0) {
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET";
		$sql .= " mois_debut = ".($mois !== '' ? "'".$this->db->escape($mois)."'" : "NULL");
		$sql .= ", reduction_type = ".($type !== '' ? "'".$this->db->escape($type)."'" : "NULL");
		$sql .= ", reduction_valeur = ".($type !== '' ? (float) price2num($valeur) : "NULL");
		$sql .= ", reduction_motif = ".($type !== '' ? "'".$this->db->escape(dol_trunc(trim((string) $motif), 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", frais_inscription_du = ".(($frais !== null && $frais !== '') ? (float) price2num($frais) : "NULL");
		$sql .= ", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->mois_debut = $mois !== '' ? $mois : null;
		$this->reduction_type = $type !== '' ? $type : null;
		$this->reduction_valeur = $type !== '' ? (float) price2num($valeur) : null;
		$this->reduction_motif = $type !== '' ? trim((string) $motif) : null;
		$this->frais_inscription_du = ($frais !== null && $frais !== '') ? (float) price2num($frais) : null;
		return 1;
	}

	/**
	 * Change l'élève de classe en cours d'année : la période en cours se termine la veille,
	 * une nouvelle période commence à la date choisie. Un élève inscrit a besoin d'une place libre.
	 *
	 * @param  User   $user      Utilisateur
	 * @param  int    $fk_classe Nouvelle classe
	 * @param  int    $date      Date du changement
	 * @param  string $motif     Motif
	 * @return int               1 si OK, -1 sinon
	 */
	public function changerClasse(User $user, $fk_classe, $date, $motif = '')
	{
		if (!$this->checkId()) {
			return -1;
		}
		global $langs;
		$langs->load('eleves@eleves');
		$this->errors = array();

		$classe = new EcoleClasse($this->db);
		if ((int) $fk_classe <= 0 || $classe->fetch((int) $fk_classe) <= 0) {
			$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('NouvelleClasseEleve'));
		} elseif ((int) $classe->status !== EcoleClasse::STATUS_ACTIVE) {
			$this->errors[] = $langs->trans('ErrorClasseInactive', $classe->ref);
		} elseif ((int) $fk_classe === (int) $this->fk_classe) {
			$this->errors[] = $langs->trans('ErrorMemeClasse');
		}
		if (empty($date)) {
			$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('DateChangement'));
		}
		if (in_array((int) $this->status, self::statusSortis(), true)) {
			$this->errors[] = $langs->trans('ErrorEleveSorti');
		}
		$current = $this->getPeriodeClasseEnCours();
		if ($current && $date && dol_print_date($date, '%Y-%m-%d') <= dol_print_date($current['date_debut'], '%Y-%m-%d')) {
			$this->errors[] = $langs->trans('ErrorDateChangementAvantDebut', dol_print_date($current['date_debut'], 'day'));
		}
		if (empty($this->errors) && in_array((int) $this->status, self::statusOccupantPlace(), true)) {
			$places = self::placesClasse($this->db, (int) $fk_classe, (int) $this->id);
			if ($places['libres'] !== null && $places['libres'] <= 0) {
				$this->errors[] = $langs->trans('ErrorClassePleine', $classe->ref.' - '.ecole_label($classe), $places['max']);
			}
		}
		if ($this->finishValidation() < 0) {
			return -1;
		}

		$this->db->begin();
		$ok = true;
		if ($current) {
			$veille = dol_time_plus_duree($date, -1, 'd');
			$ok = (bool) $this->db->query("UPDATE ".$this->db->prefix()."ecole_eleve_classe SET date_fin = '".$this->db->idate($veille)."' WHERE rowid = ".((int) $current['id']));
		}
		if ($ok) {
			$ok = $this->addHistoriqueClasse($user, (int) $fk_classe, $date, $motif) > 0;
		}
		if ($ok) {
			$ok = (bool) $this->db->query("UPDATE ".$this->db->prefix().$this->table_element." SET fk_classe = ".((int) $fk_classe).", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $this->id));
		}
		if ($ok) {
			$this->fk_classe = (int) $fk_classe;
			$ok = $this->attribuerNumeroAppel() > 0; // dernier numéro de la nouvelle classe + 1
		}
		if (!$ok) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		$this->fk_classe = (int) $fk_classe;
		return 1;
	}

	/* ------------------------------------------------------------------
	 * Historiques
	 * ---------------------------------------------------------------- */

	/**
	 * Ouvre une période dans une classe.
	 *
	 * @param  User   $user      Utilisateur
	 * @param  int    $fk_classe Classe
	 * @param  int    $date      Date de début
	 * @param  string $motif     Motif
	 * @return int               1 si OK, -1 sinon
	 */
	protected function addHistoriqueClasse(User $user, $fk_classe, $date, $motif)
	{
		if (!$this->checkId()) {
			return -1;
		}
		global $conf;
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_eleve_classe (entity, fk_eleve, fk_classe, date_debut, motif, fk_user, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".((int) $fk_classe).", '".$this->db->idate($date)."'";
		$sql .= ", ".($motif !== '' ? "'".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Ajoute une ligne à l'historique des statuts.
	 *
	 * @param  User     $user Utilisateur
	 * @param  int|null $old  Ancien statut (null à la création)
	 * @param  int      $new  Nouveau statut
	 * @param  int      $date Date d'effet
	 * @param  string   $motif Motif
	 * @return int            1 si OK, -1 sinon
	 */
	protected function addHistoriqueStatut(User $user, $old, $new, $date, $motif)
	{
		if (!$this->checkId()) {
			return -1;
		}
		global $conf;
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_eleve_statut (entity, fk_eleve, status_old, status_new, date_statut, motif, fk_user, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".($old === null ? "NULL" : (int) $old).", ".((int) $new).", '".$this->db->idate($date)."'";
		$sql .= ", ".($motif !== '' ? "'".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Période de classe en cours (date de fin vide).
	 *
	 * @return array{id:int,fk_classe:int,date_debut:int}|null
	 */
	public function getPeriodeClasseEnCours()
	{
		$resql = $this->db->query("SELECT rowid, fk_classe, date_debut FROM ".$this->db->prefix()."ecole_eleve_classe WHERE fk_eleve = ".((int) $this->id)." AND date_fin IS NULL ORDER BY date_debut DESC, rowid DESC");
		if ($resql && ($o = $this->db->fetch_object($resql))) {
			return array('id' => (int) $o->rowid, 'fk_classe' => (int) $o->fk_classe, 'date_debut' => $this->db->jdate($o->date_debut));
		}
		return null;
	}

	/**
	 * Historique des classes (plus récente en premier).
	 *
	 * @return array<int,object>
	 */
	public function getHistoriqueClasses()
	{
		$out = array();
		$sql = "SELECT h.rowid, h.fk_classe, h.date_debut, h.date_fin, h.motif, h.fk_user, h.date_creation, c.ref, c.label_fr, c.label_ar";
		$sql .= " FROM ".$this->db->prefix()."ecole_eleve_classe h LEFT JOIN ".$this->db->prefix()."ecole_classe c ON c.rowid = h.fk_classe";
		$sql .= " WHERE h.fk_eleve = ".((int) $this->id)." ORDER BY h.date_debut DESC, h.rowid DESC";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Historique des statuts (plus récent en premier).
	 *
	 * @return array<int,object>
	 */
	public function getHistoriqueStatuts()
	{
		$out = array();
		$sql = "SELECT rowid, status_old, status_new, date_statut, motif, fk_user, date_creation FROM ".$this->db->prefix()."ecole_eleve_statut";
		$sql .= " WHERE fk_eleve = ".((int) $this->id)." ORDER BY date_statut DESC, rowid DESC";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Pièces du dossier
	 * ---------------------------------------------------------------- */

	/**
	 * Pièces demandées (types actifs) avec leur état pour cet élève, plus les pièces d'un type
	 * désactivé déjà renseignées.
	 *
	 * @return array<int,object> id du type => (ref, label_fr, label_ar, type_status, fourni, date_fourni, filename, fk_user)
	 */
	public function getPieces()
	{
		$out = array();
		$sql = "SELECT t.rowid as typeid, t.ref, t.label_fr, t.label_ar, t.status as type_status, d.fourni, d.date_fourni, d.filename, d.fk_user";
		$sql .= " FROM ".$this->db->prefix()."ecole_document_type t";
		$sql .= " LEFT JOIN ".$this->db->prefix()."ecole_eleve_document d ON d.fk_document_type = t.rowid AND d.fk_eleve = ".((int) $this->id);
		$sql .= " WHERE t.entity IN (".getEntity('ecole_document_type').") AND (t.status = 1 OR d.rowid IS NOT NULL)";
		$sql .= " ORDER BY t.position, t.ref";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$o->date_fourni = $this->db->jdate($o->date_fourni);
			$out[(int) $o->typeid] = $o;
		}
		return $out;
	}

	/**
	 * Pièces demandées non fournies.
	 *
	 * @return array<int,object>
	 */
	public function getPiecesManquantes()
	{
		$out = array();
		foreach ($this->getPieces() as $id => $o) {
			if ((int) $o->type_status === 1 && empty($o->fourni)) {
				$out[$id] = $o;
			}
		}
		return $out;
	}

	/**
	 * Condition SQL (table t = ecole_eleve) : élèves auxquels il manque au moins une pièce demandée.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string
	 */
	public static function sqlPiecesManquantes($db)
	{
		return "EXISTS (SELECT 1 FROM ".$db->prefix()."ecole_document_type dt WHERE dt.status = 1 AND dt.entity IN (".getEntity('ecole_document_type').")"
			." AND NOT EXISTS (SELECT 1 FROM ".$db->prefix()."ecole_eleve_document dd WHERE dd.fk_eleve = t.rowid AND dd.fk_document_type = dt.rowid AND dd.fourni = 1))";
	}

	/**
	 * Enregistre l'état d'une pièce (fourni, date, fichier).
	 *
	 * @param  User        $user     Utilisateur
	 * @param  int         $typeid   Type de pièce
	 * @param  bool        $fourni   Fournie ?
	 * @param  int|null    $date     Date de remise
	 * @param  string|null $filename Nom du fichier (null = inchangé, '' = aucun)
	 * @return int                   1 si OK, -1 sinon
	 */
	public function setPiece(User $user, $typeid, $fourni, $date, $filename = null)
	{
		if (!$this->checkId()) {
			return -1;
		}
		global $conf;
		$p = $this->db->prefix();
		$resql = $this->db->query("SELECT rowid FROM ".$p."ecole_eleve_document WHERE fk_eleve = ".((int) $this->id)." AND fk_document_type = ".((int) $typeid));
		$o = $resql ? $this->db->fetch_object($resql) : null;
		$d = ($fourni && $date) ? "'".$this->db->idate($date)."'" : "NULL";
		if ($o) {
			$sql = "UPDATE ".$p."ecole_eleve_document SET fourni = ".($fourni ? 1 : 0).", date_fourni = ".$d.", fk_user = ".((int) $user->id);
			if ($filename !== null) {
				$sql .= ", filename = ".($filename !== '' ? "'".$this->db->escape($filename)."'" : "NULL");
			}
			$sql .= " WHERE rowid = ".((int) $o->rowid);
		} else {
			$sql = "INSERT INTO ".$p."ecole_eleve_document (entity, fk_eleve, fk_document_type, fourni, date_fourni, filename, fk_user)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".((int) $typeid).", ".($fourni ? 1 : 0).", ".$d;
			$sql .= ", ".($filename ? "'".$this->db->escape($filename)."'" : "NULL").", ".((int) $user->id).")";
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Dossier des fichiers scannés de l'élève (relatif au dossier du module) :
	 * « document/MATRICULE » — l'accès aux fichiers demande le droit « Documents : voir ».
	 *
	 * @return string
	 */
	public function getDocumentsSubdir()
	{
		return 'document/'.dol_sanitizeFileName($this->ref);
	}

	/**
	 * Dossier de la photo (relatif au dossier du module) : « eleve/MATRICULE/photo ».
	 *
	 * @return string
	 */
	public function getPhotoSubdir()
	{
		return 'eleve/'.dol_sanitizeFileName($this->ref).'/photo';
	}

	/* ------------------------------------------------------------------
	 * Divers
	 * ---------------------------------------------------------------- */

	/**
	 * L'élève est-il enregistré ? (les actions sur un dossier non créé sont refusées)
	 *
	 * @return bool
	 */
	protected function checkId()
	{
		global $langs;
		if ((int) $this->id > 0) {
			return true;
		}
		$this->error = $langs->trans('ErrorRecordNotFound');
		$this->errors = array($this->error);
		return false;
	}

	/**
	 * Frères et sœurs : autres élèves du même responsable.
	 *
	 * @return EcoleEleve[]
	 */
	public function getFratrie()
	{
		if (empty($this->fk_responsable)) {
			return array();
		}
		$list = $this->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array('fk_responsable' => (int) $this->fk_responsable), 't.rowid <> '.((int) $this->id));
		return is_array($list) ? $list : array();
	}

	/**
	 * Âge en années à aujourd'hui.
	 *
	 * @return int|null
	 */
	public function getAge()
	{
		if (empty($this->date_naissance)) {
			return null;
		}
		$b = dol_getdate($this->date_naissance);
		$n = dol_getdate(dol_now());
		$age = $n['year'] - $b['year'];
		if ($n['mon'] < $b['mon'] || ($n['mon'] == $b['mon'] && $n['mday'] < $b['mday'])) {
			$age--;
		}
		return $age;
	}

	/**
	 * Affichage d'un champ : nationalité (pays traduit).
	 *
	 * @param array  $val       Définition du champ
	 * @param string $key       Nom du champ
	 * @param mixed  $value     Valeur
	 * @param string $moreparam Paramètres
	 * @param string $keysuffix Suffixe
	 * @param string $keyprefix Préfixe
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		if ($key === 'fk_nationalite') {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
			return (int) $value > 0 ? dol_escape_htmltag(getCountry((int) $value, '0')) : '';
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Champ de saisie : nationalité (liste des pays), responsable (nom + téléphone), classe (active).
	 *
	 * @param array  $val         Définition du champ
	 * @param string $key         Nom du champ
	 * @param mixed  $value       Valeur
	 * @param string $moreparam   Paramètres HTML
	 * @param string $keysuffix   Suffixe
	 * @param string $keyprefix   Préfixe
	 * @param mixed  $morecss     CSS
	 * @param int    $nonewbutton Sans bouton « nouveau »
	 * @return string
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $form, $mysoc;
		if (!is_object($form)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($this->db);
		}
		if ($key === 'fk_nationalite') {
			return $form->select_country((int) $value > 0 ? (int) $value : (is_object($mysoc) ? $mysoc->country_id : ''), $keyprefix.$key.$keysuffix, '', 0, 'minwidth200');
		}
		if ($key === 'fk_responsable') {
			return $form->selectarray($keyprefix.$key.$keysuffix, eleves_responsables_choix($this->db, (int) $value), (int) $value, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1);
		}
		if ($key === 'fk_classe') {
			return $form->selectarray($keyprefix.$key.$keysuffix, eleves_classes_choix($this->db, (int) $value), (int) $value, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1);
		}
		return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
	}
}
