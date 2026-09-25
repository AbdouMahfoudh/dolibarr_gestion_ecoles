<?php
/**
 * Distinction : félicitations, tableau d'honneur, encouragements, avertissement...
 * Proposée automatiquement quand la moyenne générale est entre le seuil minimum (compris) et le seuil
 * maximum (non compris) ; la première de la liste (ordre de position) qui convient est retenue.
 *
 * Fichier : custom/notes/class/ecole_note_distinction.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleNoteDistinction
 */
class EcoleNoteDistinction extends EcoleObject
{
	/** @var string */
	public $module = 'notes';
	/** @var string */
	public $element = 'ecole_note_distinction';
	/** @var string */
	public $table_element = 'ecole_note_distinction';
	/** @var string */
	public $picto = 'fa-award';
	/** @var string */
	public $dirpage = 'distinction';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(array('ecole_bulletin', 'fk_distinction', 'Bulletins'));
	public $listcolumns = array('ref', 'label', 'seuil_min', 'seuil_max', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $seuil_min;
	public $seuil_max;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'seuil_min' => array('type' => 'double(8,2)', 'label' => 'SeuilMin', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40, 'css' => 'maxwidth75', 'help' => 'SeuilMinHelp'),
		'seuil_max' => array('type' => 'double(8,2)', 'label' => 'SeuilMax', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 45, 'css' => 'maxwidth75', 'help' => 'SeuilMaxHelp'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 50, 'help' => 'PositionDistinctionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : seuils vides ou entre 0 et 20, minimum inférieur au maximum, au moins un seuil.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();
		foreach (array('seuil_min', 'seuil_max') as $k) {
			if ($this->$k === '' || $this->$k === null) {
				$this->$k = null;
				continue;
			}
			$this->$k = (float) price2num($this->$k);
			if ($this->$k < 0 || $this->$k > 20) {
				$this->errors[] = $langs->trans('ErrorSeuil020');
			}
		}
		if ($this->seuil_min === null && $this->seuil_max === null) {
			$this->errors[] = $langs->trans('ErrorDistinctionSansSeuil');
		} elseif ($this->seuil_min !== null && $this->seuil_max !== null && $this->seuil_min >= $this->seuil_max) {
			$this->errors[] = $langs->trans('ErrorSeuilMinMax');
		}
		return $this->finishValidation();
	}
}
