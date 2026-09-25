<?php
/**
 * Liaison matière <-> classe avec son coefficient et son barème.
 *
 *  - barème « 20 »     : la note est sur 20
 *  - barème « coef20 » : la note est sur coefficient x 20 (ex. coefficient 2,5 -> sur 50)
 * Le maximum de la note (note_max) est calculé à chaque enregistrement.
 *
 * Fichier : custom/classes/class/ecole_classe_matiere.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleClasseMatiere
 */
class EcoleClasseMatiere extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_classe_matiere';
	/** @var string */
	public $table_element = 'ecole_classe_matiere';
	/** @var string */
	public $picto = 'fa-book';

	const BAREME_20 = '20';
	const BAREME_COEF20 = 'coef20';

	public $required = array('fk_classe', 'fk_matiere');

	public $fk_classe;
	public $fk_matiere;
	public $coefficient = 1;
	public $bareme = '20';
	public $note_max = 20;
	/** @var string|null Langue d'enseignement dans cette classe ('fr' / 'ar'), vide = celle de la matière */
	public $langue;

	/** @var array */
	public $fields = array(
		'fk_classe' => array('type' => 'integer', 'label' => 'Classe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1),
		'fk_matiere' => array('type' => 'integer', 'label' => 'Matiere', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'index' => 1),
		'coefficient' => array('type' => 'double(8,2)', 'label' => 'Coefficient', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 30),
		'bareme' => array('type' => 'varchar(8)', 'label' => 'Bareme', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '20', 'position' => 40),
		'note_max' => array('type' => 'double(8,2)', 'label' => 'NoteMax', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '20', 'position' => 50),
		'langue' => array('type' => 'varchar(2)', 'label' => 'LangueEnseignement', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60),
	);

	/**
	 * Cette liaison n'a pas de statut.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		unset($f['status']);
		return $f;
	}

	/**
	 * Calcule le maximum de la note selon le barème.
	 *
	 * @param float  $coefficient Coefficient
	 * @param string $bareme      '20' ou 'coef20'
	 * @return float
	 */
	public static function computeNoteMax($coefficient, $bareme)
	{
		return ($bareme === self::BAREME_COEF20) ? round((float) $coefficient * 20, 2) : 20.0;
	}

	/**
	 * Validation : coefficient > 0, barème connu, classe et matière existantes, liaison unique. Calcule note_max.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		// Barème non précisé : celui du niveau de la classe
		if (empty($this->bareme) && $this->fk_classe > 0) {
			dol_include_once('/classes/class/ecole_classe.class.php');
			$classe = new EcoleClasse($this->db);
			$this->bareme = ($classe->fetch($this->fk_classe) > 0) ? $classe->getBaremeDefaut() : self::BAREME_20;
		}
		$this->coefficient = (float) price2num($this->coefficient);
		if ($this->coefficient <= 0) {
			$this->errors[] = $langs->trans('ErrorEcoleCoefficient');
		}
		if (!in_array($this->bareme, array(self::BAREME_20, self::BAREME_COEF20), true)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Bareme'));
		}
		$this->note_max = self::computeNoteMax($this->coefficient, $this->bareme);
		if (empty($this->langue)) {
			$this->langue = null; // celle de la matière
		} elseif (!in_array($this->langue, array('fr', 'ar'), true)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('LangueEnseignement'));
		}

		$p = $this->db->prefix();
		if ($this->fk_classe > 0) {
			$r = $this->db->query("SELECT rowid FROM ".$p."ecole_classe WHERE rowid = ".((int) $this->fk_classe));
			if (!$r || $this->db->num_rows($r) == 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Classe'));
			}
		}
		if ($this->fk_matiere > 0) {
			$r = $this->db->query("SELECT rowid FROM ".$p."ecole_matiere WHERE rowid = ".((int) $this->fk_matiere));
			if (!$r || $this->db->num_rows($r) == 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Matiere'));
			}
		}
		if ($this->fk_classe > 0 && $this->fk_matiere > 0) {
			$sql = "SELECT rowid FROM ".$p."ecole_classe_matiere WHERE fk_classe = ".((int) $this->fk_classe)." AND fk_matiere = ".((int) $this->fk_matiere);
			if ($this->id > 0) {
				$sql .= " AND rowid <> ".((int) $this->id);
			}
			$r = $this->db->query($sql);
			if ($r && $this->db->num_rows($r) > 0) {
				$this->errors[] = $langs->trans('ErrorEcoleMatiereDejaLiee');
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Suppression : refusée si la matière est utilisée dans l'emploi du temps ou les examens de cette classe.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->errors = $this->getDeleteBlockers();
		if (!empty($this->errors)) {
			$this->finishValidation();
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Causes qui empêchent de délier la matière : elle est utilisée dans l'emploi du temps ou les examens de cette classe.
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$langs->load('classes@classes');
		$out = array();
		$p = $this->db->prefix();
		foreach (array('ecole_edt_cours' => 'EcoleEmploiDuTemps', 'ecole_edt_examen' => 'EcoleExamens') as $table => $key) {
			$r = $this->db->query("SELECT COUNT(*) as nb FROM ".$p.$table." WHERE fk_classe = ".((int) $this->fk_classe)." AND fk_matiere = ".((int) $this->fk_matiere));
			if ($r && ($o = $this->db->fetch_object($r)) && $o->nb > 0) {
				$out[] = $langs->trans('ErrorEcoleMatiereUtilisee', $langs->transnoentities($key), $o->nb);
			}
		}
		return $out;
	}
}
