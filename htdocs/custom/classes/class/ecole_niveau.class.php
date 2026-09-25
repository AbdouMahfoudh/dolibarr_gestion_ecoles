<?php
/**
 * Niveau (cycle) : maternelle, primaire, collège, lycée...
 *
 * Fichier : custom/classes/class/ecole_niveau.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleNiveau
 */
class EcoleNiveau extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_niveau';
	/** @var string */
	public $table_element = 'ecole_niveau';
	/** @var string */
	public $picto = 'fa-layer-group';
	/** @var string */
	public $dirpage = 'niveau';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(array('ecole_classe', 'fk_niveau', 'EcoleClasses'));
	public $listcolumns = array('ref', 'label', 'avec_sections', 'bareme_defaut', 'position', 'status');
	public $sortdefault = 'position';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $avec_sections = 0;
	/** @var string Barème proposé quand on lie une matière à une classe de ce niveau : '20' ou 'coef20' */
	public $bareme_defaut = '20';
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'avec_sections' => array('type' => 'boolean', 'label' => 'AvecSections', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'AvecSectionsHelp'),
		'bareme_defaut' => array('type' => 'varchar(8)', 'label' => 'BaremeParDefaut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '20', 'position' => 45,
			'arrayofkeyval' => array('20' => 'BaremeSur20', 'coef20' => 'BaremeSurCoef20'), 'help' => 'BaremeParDefautHelp'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 50),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth300'),
	);
}
