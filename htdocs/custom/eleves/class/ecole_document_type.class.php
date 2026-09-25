<?php
/**
 * Pièce à fournir pour le dossier d'un élève (acte de naissance, photos...).
 * Liste configurable par l'établissement ; une pièce désactivée n'est plus demandée.
 *
 * Fichier : custom/eleves/class/ecole_document_type.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleDocumentType
 */
class EcoleDocumentType extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_document_type';
	/** @var string */
	public $table_element = 'ecole_document_type';
	/** @var string */
	public $picto = 'fa-file-alt';
	/** @var string */
	public $dirpage = 'document_type';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_eleve_document', 'fk_document_type', 'EcoleDossiersEleves'),
	);
	public $listcolumns = array('ref', 'label', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'PositionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Pièces actives (demandées), dans l'ordre d'affichage.
	 *
	 * @return EcoleDocumentType[]
	 */
	public function fetchActives()
	{
		$list = $this->fetchAllObjects('t.position,t.ref', 'ASC', 0, 0, array('status' => self::STATUS_ACTIVE));
		return is_array($list) ? $list : array();
	}
}
