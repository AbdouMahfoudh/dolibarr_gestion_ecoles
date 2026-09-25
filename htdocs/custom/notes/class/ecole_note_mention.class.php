<?php
/**
 * Mention (appréciation automatique selon la moyenne générale) : Très bien, Bien, Assez bien...
 * La mention d'une moyenne est celle dont le seuil est le plus haut atteint.
 *
 * Fichier : custom/notes/class/ecole_note_mention.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleNoteMention
 */
class EcoleNoteMention extends EcoleObject
{
	/** @var string */
	public $module = 'notes';
	/** @var string */
	public $element = 'ecole_note_mention';
	/** @var string */
	public $table_element = 'ecole_note_mention';
	/** @var string */
	public $picto = 'fa-medal';
	/** @var string */
	public $dirpage = 'mention';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'label', 'seuil', 'status');
	public $sortdefault = 'seuil';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $seuil = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'seuil' => array('type' => 'double(8,2)', 'label' => 'SeuilMention', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'css' => 'maxwidth75', 'help' => 'SeuilMentionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : seuil entre 0 et 20.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();
		$this->seuil = (float) price2num($this->seuil);
		if ($this->seuil < 0 || $this->seuil > 20) {
			$this->errors[] = $langs->trans('ErrorSeuil020');
		}
		return $this->finishValidation();
	}
}
