<?php
/**
 * Section (ex. lycée : Série C, Série D, Série A).
 *
 * Fichier : custom/classes/class/ecole_section.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleSection
 */
class EcoleSection extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_section';
	/** @var string */
	public $table_element = 'ecole_section';
	/** @var string */
	public $picto = 'fa-code-branch';
	/** @var string */
	public $dirpage = 'section';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(array('ecole_classe', 'fk_section', 'EcoleClasses'));
	public $listcolumns = array('ref', 'label', 'position', 'status');
	public $sortdefault = 'position';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 50),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth300'),
	);
}
