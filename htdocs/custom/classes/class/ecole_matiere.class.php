<?php
/**
 * Matière (catalogue de l'école). Le coefficient est défini par classe (EcoleClasseMatiere).
 *
 * Fichier : custom/classes/class/ecole_matiere.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleMatiere
 */
class EcoleMatiere extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_matiere';
	/** @var string */
	public $table_element = 'ecole_matiere';
	/** @var string */
	public $picto = 'fa-book';
	/** @var string */
	public $dirpage = 'matiere';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref', 'code');
	public $usage = array(
		array('ecole_classe_matiere', 'fk_matiere', 'EcoleClasses'),
		array('ecole_edt_cours', 'fk_matiere', 'EcoleEmploiDuTemps'),
		array('ecole_edt_examen', 'fk_matiere', 'EcoleExamens'),
	);
	public $listcolumns = array('ref', 'label', 'langue', 'status');
	public $searchcolumns = array('ref', 'label');

	const LANGUE_FR = 'fr';
	const LANGUE_AR = 'ar';

	public $ref;
	public $code;
	public $label_fr;
	public $label_ar;
	/** @var string Langue d'enseignement ('fr' ou 'ar') : écriture de la matière sur le bulletin bilingue */
	public $langue = 'fr';
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'code' => array('type' => 'varchar(32)', 'label' => 'CodeMatiere', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 15, 'searchall' => 1, 'css' => 'maxwidth200', 'help' => 'CodeMatiereHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'langue' => array('type' => 'varchar(2)', 'label' => 'LangueEnseignement', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'fr', 'position' => 40,
			'arrayofkeyval' => array('fr' => 'LangueFrancais', 'ar' => 'LangueArabe'), 'help' => 'LangueEnseignementHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth300'),
	);

	/**
	 * Le code est facultatif : vide = NULL (plusieurs matières sans code sont permises).
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		if (isset($this->code) && trim((string) $this->code) === '') {
			$this->code = null;
		}
		if (empty($this->langue)) {
			$this->langue = self::LANGUE_FR;
		}
		parent::validate();
		if (!in_array($this->langue, array(self::LANGUE_FR, self::LANGUE_AR), true)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('LangueEnseignement'));
		}
		return $this->finishValidation();
	}
}
