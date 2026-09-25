<?php
/**
 * Classe de base des objets du module Classes.
 *
 * Suit le modèle « ModuleBuilder » de Dolibarr (tableau $fields + createCommon /
 * updateCommon / fetchCommon / deleteCommon) et centralise ce qui est commun :
 * champs techniques, validation (obligatoire, unicité), contrôle d'utilisation
 * avant suppression, statut actif/inactif, lecture de listes.
 *
 * Fichier : custom/classes/class/ecole_object.class.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/classes/core/lib/classes.lib.php');

/**
 * Class EcoleObject
 */
abstract class EcoleObject extends CommonObject
{
	/** @var string */
	public $module = 'classes';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int */
	public $ismultientitymanaged = 1;
	/** @var string */
	public $picto = 'generic';

	/** @var string Sous-dossier des pages de cet objet (ex. « niveau ») */
	public $dirpage = '';
	/** @var string[] Champs obligatoires (non vides) */
	public $required = array();
	/** @var string[] Champs uniques dans l'établissement */
	public $unique = array();
	/** @var array<int,array{0:string,1:string,2:string}> Utilisations bloquant la suppression : [table sans préfixe, colonne, clé de langue] */
	public $usage = array();
	/**
	 * @var string[] Colonnes affichées dans la liste. Valeurs spéciales : « label » (libellé dans la langue
	 *               de l'utilisateur), « extra:nom » (colonne calculée fournie par la configuration de la page).
	 */
	public $listcolumns = array('ref', 'label', 'status');
	/** @var string[] Non utilisé : chaque colonne de la liste a son filtre (voir ecole_crud_search_spec de crud.lib.php) */
	public $searchcolumns = array('ref', 'label');
	/** @var string Tri par défaut de la liste (plusieurs colonnes séparées par des virgules) */
	public $sortdefault = 'ref';
	/** @var array{0:string,1:string} Colonnes du libellé bilingue [français, arabe] (ex. nom_fr / nom_ar pour une personne) */
	public $labelfields = array('label_fr', 'label_ar');
	/** @var string Titre de la colonne « Libellé » des listes (clé de traduction) */
	public $labeltitle = 'EcoleLibelle';

	/** Longueur maximale d'une référence */
	const REF_MAX = 10;

	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->entity = $conf->entity;
		$this->status = self::STATUS_ACTIVE;
		$this->fields = array_merge($this->fields, static::commonFields());
	}

	/**
	 * Champs techniques communs à tous les objets.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		return array(
			'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => 0, 'notnull' => 1, 'position' => 1, 'index' => 1),
			'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => 0, 'notnull' => 1, 'default' => '1', 'position' => 5, 'index' => 1),
			'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 500),
			'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 501),
			'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 510, 'foreignkey' => 'user.rowid'),
			'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => -1, 'position' => 511, 'foreignkey' => 'user.rowid'),
			'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 1000, 'index' => 1,
				'arrayofkeyval' => array(1 => 'EcoleActif', 0 => 'EcoleInactif')),
		);
	}

	/* ------------------------------------------------------------------
	 * CRUD
	 * ---------------------------------------------------------------- */

	/**
	 * @param User $user       Utilisateur
	 * @param int  $notrigger  1 = sans trigger
	 * @return int             >0 = id créé, <0 = erreur
	 */
	public function create(User $user, $notrigger = 0)
	{
		if ($this->validate() < 0) {
			return -1;
		}
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * @param int         $id  Id
	 * @param string|null $ref Ref
	 * @return int             >0 = OK, 0 = introuvable, <0 = erreur
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * @param User $user       Utilisateur
	 * @param int  $notrigger  1 = sans trigger
	 * @return int             >0 = OK, <0 = erreur
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->validate() < 0) {
			return -1;
		}
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * @param User $user       Utilisateur
	 * @param int  $notrigger  1 = sans trigger
	 * @return int             >0 = OK, <0 = erreur
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ($this->checkUsage() < 0) {
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}

	/* ------------------------------------------------------------------
	 * Validation
	 * ---------------------------------------------------------------- */

	/**
	 * Valide l'objet (champs obligatoires, unicité). À étendre dans les sous-classes :
	 * appeler parent::validate() puis ajouter ses propres erreurs dans $this->errors.
	 *
	 * @return int 1 si OK, -1 sinon (messages traduits dans $this->errors)
	 */
	public function validate()
	{
		global $langs;
		$langs->loadLangs(array('classes@classes', 'errors'));

		$this->errors = array();
		$this->error = '';

		foreach ($this->required as $f) {
			if (is_string($this->$f)) {
				$this->$f = trim($this->$f);
			}
			if (!isset($this->$f) || $this->$f === '' || $this->$f === null) {
				$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities($this->fields[$f]['label']));
			}
		}
		if (isset($this->fields['ref']) && isset($this->ref) && dol_strlen((string) $this->ref) > self::REF_MAX) {
			$this->errors[] = $langs->trans('ErrorEcoleRefTropLongue', self::REF_MAX);
		}
		foreach ($this->unique as $f) {
			$v = isset($this->$f) ? trim((string) $this->$f) : '';
			if ($v === '') {
				continue;
			}
			$sql = "SELECT rowid FROM ".$this->db->prefix().$this->table_element;
			$sql .= " WHERE entity IN (".getEntity($this->element).")";
			$sql .= " AND ".$this->db->sanitize($f)." = '".$this->db->escape($v)."'";
			if ($this->id > 0) {
				$sql .= " AND rowid <> ".((int) $this->id);
			}
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$this->errors[] = $langs->trans('ErrorEcoleAlreadyExists', $langs->transnoentities($this->fields[$f]['label']), $v);
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Valeur actuellement enregistrée en base d'une colonne (null pour un nouvel objet).
	 * Sert à ne contrôler une règle que si la valeur a changé.
	 *
	 * @param  string $field Colonne
	 * @return mixed
	 */
	public function getStoredValue($field)
	{
		if (empty($this->id) || !isset($this->fields[$field])) {
			return null;
		}
		$r = $this->db->query("SELECT ".$this->db->sanitize($field)." as v FROM ".$this->db->prefix().$this->table_element." WHERE rowid = ".((int) $this->id));
		$o = $r ? $this->db->fetch_object($r) : null;
		return $o ? $o->v : null;
	}

	/**
	 * Termine une validation : copie les erreurs dans $this->error.
	 *
	 * @return int 1 si aucune erreur, -1 sinon
	 */
	protected function finishValidation()
	{
		if (empty($this->errors)) {
			return 1;
		}
		$this->error = implode('<br>', $this->errors);
		return -1;
	}

	/**
	 * Vérifie qu'aucun autre objet n'utilise celui-ci (bloque la suppression).
	 * Les mêmes causes servent à griser le bouton « Supprimer » (voir getDeleteBlockers()).
	 *
	 * @return int 1 si supprimable, -1 sinon
	 */
	public function checkUsage()
	{
		$this->errors = $this->getDeleteBlockers();
		return $this->finishValidation();
	}

	/**
	 * Causes qui empêchent la suppression (messages traduits, vide = supprimable). Une seule règle pour le
	 * bouton « Supprimer » (grisé avec ces causes en infobulle) et pour la suppression elle-même : les sous-classes
	 * complètent cette liste au lieu de refaire leur propre contrôle.
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$langs->loadLangs(array('classes@classes'));

		$out = array();
		foreach ($this->usage as $u) {
			$nb = $this->countUsage($u[0], $u[1]);
			if ($nb > 0) {
				$out[] = $langs->trans('ErrorEcoleObjectUsed', $langs->transnoentities($u[2]), $nb);
			}
		}
		return $out;
	}

	/**
	 * Nombre de lignes d'une table qui pointent vers cet objet (0 si la table n'existe pas, ex. module non installé).
	 *
	 * @param  string $table  Table sans préfixe
	 * @param  string $column Colonne qui contient l'identifiant de cet objet
	 * @param  string $where  Condition SQL supplémentaire (déjà sûre)
	 * @return int
	 */
	protected function countUsage($table, $column, $where = '')
	{
		if (empty($this->id) || !ecole_table_exists($this->db, $table)) {
			return 0;
		}
		$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix().$this->db->sanitize($table);
		$sql .= " WHERE ".$this->db->sanitize($column)." = ".((int) $this->id).($where !== '' ? " AND ".$where : '');
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return $obj ? (int) $obj->nb : 0;
	}

	/* ------------------------------------------------------------------
	 * Lecture de listes
	 * ---------------------------------------------------------------- */

	/**
	 * Charge une liste d'objets.
	 *
	 * @param string              $sortfield Champ de tri (ex. « t.ref »)
	 * @param string              $sortorder ASC / DESC
	 * @param int                 $limit     0 = pas de limite
	 * @param int                 $offset    Décalage
	 * @param array<string,mixed> $filter    Égalités colonne => valeur
	 * @param string              $customsql Condition SQL déjà sécurisée (sans AND)
	 * @return static[]|int                  Tableau id => objet, ou -1 si erreur
	 */
	public function fetchAllObjects($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 0, $offset = 0, $filter = array(), $customsql = '')
	{
		$records = array();

		$sql = "SELECT ".$this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		foreach ($filter as $k => $v) {
			$sql .= " AND t.".$this->db->sanitize($k)." = ".(is_int($v) ? $v : "'".$this->db->escape((string) $v)."'");
		}
		if ($customsql !== '') {
			$sql .= " AND (".$customsql.")";
		}
		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit > 0) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$rec = new static($this->db);
			$rec->setVarsFromFetchObj($obj);
			$records[$rec->id] = $rec;
		}
		$this->db->free($resql);
		return $records;
	}

	/**
	 * Compte les objets correspondant à une condition.
	 *
	 * @param string $customsql Condition SQL déjà sécurisée (sans AND)
	 * @return int
	 */
	public function countAll($customsql = '')
	{
		$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		if ($customsql !== '') {
			$sql .= " AND (".$customsql.")";
		}
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return $obj ? (int) $obj->nb : 0;
		}
		return 0;
	}

	/* ------------------------------------------------------------------
	 * Affichage
	 * ---------------------------------------------------------------- */

	/**
	 * Libellé lisible : « REF - Libellé » (libellé dans la langue de l'utilisateur).
	 *
	 * @return string
	 */
	public function getLabel()
	{
		$label = isset($this->ref) ? (string) $this->ref : '';
		$l = ecole_label($this);
		if ($l !== '') {
			$label .= ($label !== '' ? ' - ' : '').$l;
		}
		return $label;
	}

	/**
	 * Lien vers la fiche de l'objet : affiche la référence (ou le libellé avec $option = 'label').
	 *
	 * @param int    $withpicto 1 = avec picto
	 * @param string $option    '' = référence, 'label' = libellé dans la langue de l'utilisateur
	 * @param int    $notooltip Non utilisé
	 * @param string $morecss   Classes CSS
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$text = ($option === 'label') ? ecole_label($this) : (string) $this->ref;
		$url = dol_buildpath('/'.$this->module.'/'.$this->dirpage.'/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'" class="'.$morecss.'" title="'.dol_escape_htmltag($this->getLabel()).'">';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		$out .= dol_escape_htmltag($text).'</a>';
		return $out;
	}

	/**
	 * Affichage d'un champ : un lien vers un autre objet du module (niveau, section, salle...)
	 * s'affiche par son libellé dans la langue de l'utilisateur.
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
		$type = isset($this->fields[$key]['type']) ? $this->fields[$key]['type'] : '';
		if (preg_match('/^integer:(Ecole\w+):([^:]+)/', $type, $reg)) {
			return ecole_link_label($this->db, $reg[1], $reg[2], $value);
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * @param int $mode Mode d'affichage Dolibarr
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('classes@classes');
		$labels = array(self::STATUS_INACTIVE => $langs->transnoentitiesnoconv('EcoleInactif'), self::STATUS_ACTIVE => $langs->transnoentitiesnoconv('EcoleActif'));
		$type = ($status == self::STATUS_ACTIVE) ? 'status4' : 'status5';
		return dolGetStatus($labels[(int) $status], $labels[(int) $status], '', $type, $mode);
	}

	/**
	 * Statuts possibles : valeur => clé de traduction (filtre des listes, exports).
	 *
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		return array(self::STATUS_ACTIVE => 'EcoleActif', self::STATUS_INACTIVE => 'EcoleInactif');
	}

	/**
	 * Champ de saisie : gère l'heure (type « time » HTML) pour les champs marqués 'inputtime'.
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
		if (!empty($this->fields[$key]['inputtime'])) {
			$name = $keyprefix.$key.$keysuffix;
			return '<input type="time" class="flat" name="'.$name.'" id="'.$name.'" value="'.dol_escape_htmltag((string) $value).'" '.$moreparam.'>';
		}
		return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
	}
}
