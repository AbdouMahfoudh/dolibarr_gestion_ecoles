<?php
/**
 * Classe (ex. 6ème A) : niveau, section (si le niveau en utilise), effectif max,
 * salle attitrée, mensualité et frais d'inscription.
 *
 * Fichier : custom/classes/class/ecole_classe.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/classes/class/ecole_niveau.class.php');

/**
 * Class EcoleClasse
 */
class EcoleClasse extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_classe';
	/** @var string */
	public $table_element = 'ecole_classe';
	/** @var string */
	public $picto = 'fa-chalkboard';
	/** @var string */
	public $dirpage = 'classe';

	public $required = array('ref', 'label_fr', 'fk_niveau');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_classe_matiere', 'fk_classe', 'EcoleMatieres'),
		array('ecole_edt_cours', 'fk_classe', 'EcoleEmploiDuTemps'),
		array('ecole_edt_examen', 'fk_classe', 'EcoleExamens'),
	);
	public $listcolumns = array('ref', 'label', 'fk_niveau', 'fk_section', 'fk_salle', 'extra:effectif', 'mensualite', 'frais_inscription', 'extra:edt', 'status');
	public $searchcolumns = array('ref', 'label');
	public $sortdefault = 'fk_niveau,rowid';

	/** Effectif maximum proposé si la configuration du module n'en fixe pas (même valeur par défaut dans la base) */
	const EFFECTIF_MAX_DEFAUT = 100;

	public $ref;
	public $label_fr;
	public $label_ar;
	public $fk_niveau;
	public $fk_section;
	public $effectif_max;
	public $fk_salle;
	public $mensualite = 0;
	public $frais_inscription = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'fk_niveau' => array('type' => 'integer:EcoleNiveau:classes/class/ecole_niveau.class.php:0:(status:=:1)', 'label' => 'Niveau', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40, 'index' => 1, 'css' => 'minwidth200'),
		'fk_section' => array('type' => 'integer:EcoleSection:classes/class/ecole_section.class.php:0:(status:=:1)', 'label' => 'Section', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50, 'index' => 1, 'css' => 'minwidth200', 'help' => 'SectionHelp'),
		'effectif_max' => array('type' => 'integer', 'label' => 'EffectifMax', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '100', 'createdefault' => array('EcoleClasse', 'effectifMaxDefaut'), 'position' => 60, 'help' => 'EffectifMaxHelp'),
		'fk_salle' => array('type' => 'integer:EcoleSalle:classes/class/ecole_salle.class.php:0:(status:=:1)', 'label' => 'SalleAttitree', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70, 'css' => 'minwidth200'),
		'mensualite' => array('type' => 'price', 'label' => 'Mensualite', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 80, 'isameasure' => 1, 'help' => 'MensualiteHelp'),
		'frais_inscription' => array('type' => 'price', 'label' => 'FraisInscriptionClasse', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 85, 'isameasure' => 1, 'help' => 'FraisInscriptionClasseHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 90, 'css' => 'minwidth300'),
	);

	/**
	 * Effectif maximum par défaut : réglage « Effectif maximum par défaut » de la configuration du module Classes (100 sinon).
	 *
	 * @return int
	 */
	public static function effectifMaxDefaut()
	{
		$v = getDolGlobalInt('ECOLE_EFFECTIF_MAX_DEFAUT');
		return $v > 0 ? $v : self::EFFECTIF_MAX_DEFAUT;
	}

	/**
	 * Validation : section obligatoire si le niveau en utilise (sinon vidée), effectif et mensualité positifs.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		if ($this->fk_niveau > 0) {
			$niveau = new EcoleNiveau($this->db);
			if ($niveau->fetch($this->fk_niveau) > 0) {
				if (!empty($niveau->avec_sections)) {
					if (empty($this->fk_section) || $this->fk_section < 0) {
						$this->errors[] = $langs->trans('ErrorEcoleSectionRequired', ecole_label($niveau));
					}
				} else {
					$this->fk_section = null;
				}
			} else {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Niveau'));
			}
		}
		$old = null;
		if ($this->id > 0) {
			$r = $this->db->query("SELECT fk_salle FROM ".$this->db->prefix()."ecole_classe WHERE rowid = ".((int) $this->id));
			$old = $r ? $this->db->fetch_object($r) : null;
		}
		if (!$old || (int) $old->fk_salle !== (int) $this->fk_salle) {
			ecole_check_salle_active($this->db, $this->fk_salle, $this->errors); // nouvelle salle attitrée : doit être active
		}
		if ($this->effectif_max === null || $this->effectif_max === '') {
			$this->effectif_max = self::effectifMaxDefaut(); // non renseigné : valeur par défaut de la configuration
		}
		if ((int) $this->effectif_max < 1) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('EffectifMax'));
		}
		if ($this->mensualite === '' || $this->mensualite === null) {
			$this->mensualite = 0;
		}
		if ((float) $this->mensualite < 0) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Mensualite'));
		}
		if ($this->frais_inscription === '' || $this->frais_inscription === null) {
			$this->frais_inscription = 0;
		}
		if ((float) $this->frais_inscription < 0) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('FraisInscriptionClasse'));
		}

		return $this->finishValidation();
	}

	/**
	 * Causes qui empêchent la suppression : matières, emploi du temps, examens (parent), et tout ce qui
	 * concerne des élèves : élèves rattachés (quel que soit leur statut), historique des changements de classe,
	 * appels, évaluations et notes, bulletins. Une classe qui a servi se passe en « inactive ».
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$out = parent::getDeleteBlockers();

		$plus = array(
			array('ecole_eleve', 'fk_classe', 'ClasseEleves', ''),
			array('ecole_eleve_classe', 'fk_classe', 'ClasseHistoriqueEleves', ''),
			array('ecole_appel', 'fk_classe', 'ClasseAppels', ''),
			array('ecole_evaluation', 'fk_classe', 'ClasseEvaluations', 'status = 1'),
			array('ecole_bulletin', 'fk_classe', 'ClasseBulletins', ''),
		);
		foreach ($plus as $u) {
			$nb = $this->countUsage($u[0], $u[1], $u[3]);
			if ($nb > 0) {
				$out[] = $langs->trans('ErrorEcoleObjectUsed', $langs->transnoentities($u[2]), $nb);
			}
		}
		return $out;
	}

	/**
	 * Barème proposé pour les matières de cette classe, défini sur son niveau
	 * (ex. maternelle et primaire : coefficient x 20 ; collège et lycée : sur 20).
	 *
	 * @return string '20' ou 'coef20'
	 */
	public function getBaremeDefaut()
	{
		$r = $this->db->query("SELECT bareme_defaut FROM ".$this->db->prefix()."ecole_niveau WHERE rowid = ".((int) $this->fk_niveau));
		if ($r && ($o = $this->db->fetch_object($r)) && in_array($o->bareme_defaut, array('20', 'coef20'), true)) {
			return $o->bareme_defaut;
		}
		return '20';
	}

	/**
	 * Résumé de la classe : nombre de matières, somme des coefficients, cases d'emploi du temps, épreuves.
	 *
	 * @return array{matieres:int,coefficients:float,cours:int,examens:int}
	 */
	public function getResume()
	{
		$p = $this->db->prefix();
		$id = (int) $this->id;
		$res = array('matieres' => 0, 'coefficients' => 0.0, 'cours' => 0, 'examens' => 0);

		$resql = $this->db->query("SELECT COUNT(*) as nb, COALESCE(SUM(coefficient), 0) as coef FROM ".$p."ecole_classe_matiere WHERE fk_classe = ".$id);
		if ($resql && ($o = $this->db->fetch_object($resql))) {
			$res['matieres'] = (int) $o->nb;
			$res['coefficients'] = (float) $o->coef;
		}
		$resql = $this->db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_edt_cours WHERE fk_classe = ".$id);
		if ($resql && ($o = $this->db->fetch_object($resql))) {
			$res['cours'] = (int) $o->nb;
		}
		$resql = $this->db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_edt_examen WHERE fk_classe = ".$id);
		if ($resql && ($o = $this->db->fetch_object($resql))) {
			$res['examens'] = (int) $o->nb;
		}
		return $res;
	}
}
