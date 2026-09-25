<?php
/**
 * Jours sans cours : vacances scolaires, fêtes, jours fériés (une date ou une période).
 * Ces jours-là, aucun cours n'est attendu : ils ne comptent ni comme faits ni comme absences
 * dans les heures des enseignants.
 *
 * Fichier : custom/personnel/class/ecole_jour_sans_cours.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleJourSansCours
 */
class EcoleJourSansCours extends EcoleObject
{
	/** @var string */
	public $module = 'personnel';
	/** @var string */
	public $element = 'ecole_jour_sans_cours';
	/** @var string */
	public $table_element = 'ecole_jour_sans_cours';
	/** @var string */
	public $picto = 'fa-umbrella-beach';
	/** @var string */
	public $dirpage = 'jour_sans_cours';

	public $required = array('ref', 'label_fr', 'date_debut', 'date_fin');
	public $unique = array('ref');
	public $usage = array();
	public $listcolumns = array('ref', 'label', 'date_debut', 'date_fin', 'extra:jours', 'status');
	public $sortdefault = 'date_debut';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $date_debut;
	public $date_fin;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'date_debut' => array('type' => 'date', 'label' => 'DateDebut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40),
		'date_fin' => array('type' => 'date', 'label' => 'DateFin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 41, 'help' => 'DateFinJourSansCoursHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : la fin n'est pas avant le début.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();
		if (!empty($this->date_debut) && !empty($this->date_fin) && $this->date_fin < $this->date_debut) {
			$this->errors[] = $langs->trans('ErrorDateFinAvantDebut');
		}
		return $this->finishValidation();
	}
}
