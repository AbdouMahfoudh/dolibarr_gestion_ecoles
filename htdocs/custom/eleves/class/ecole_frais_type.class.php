<?php
/**
 * Autres frais encaissés depuis la fiche élève (uniforme, fournitures, transport, cantine...).
 * Liste configurable par l'établissement, avec un prix proposé par défaut.
 *
 * Fichier : custom/eleves/class/ecole_frais_type.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleFraisType
 */
class EcoleFraisType extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_frais_type';
	/** @var string */
	public $table_element = 'ecole_frais_type';
	/** @var string */
	public $picto = 'fa-tshirt';
	/** @var string */
	public $dirpage = 'frais_type';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_paiement', 'fk_frais_type', 'EcolePaiementsEnregistres'),
	);
	public $listcolumns = array('ref', 'label', 'montant', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $montant = 0;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'montant' => array('type' => 'price', 'label' => 'PrixParDefaut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 35, 'isameasure' => 1, 'help' => 'PrixParDefautHelp'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'PositionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : prix positif.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();
		if ($this->montant === '' || $this->montant === null) {
			$this->montant = 0;
		}
		if ((float) $this->montant < 0) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('PrixParDefaut'));
		}
		return $this->finishValidation();
	}

	/**
	 * Frais actifs, dans l'ordre d'affichage : id => objet.
	 *
	 * @return EcoleFraisType[]
	 */
	public function fetchActives()
	{
		$list = $this->fetchAllObjects('t.position,t.ref', 'ASC', 0, 0, array('status' => self::STATUS_ACTIVE));
		return is_array($list) ? $list : array();
	}
}
