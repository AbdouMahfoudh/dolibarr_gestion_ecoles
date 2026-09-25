<?php
/**
 * Types de sanctions (avertissement, blâme, retenue, exclusion temporaire, exclusion définitive).
 * Liste configurable par l'établissement. Le champ « exclusion » dit ce que la sanction entraîne :
 * 0 = rien, 1 = exclusion temporaire (date de fin, élève « exclu » à l'appel pendant la période),
 * 2 = exclusion définitive (statut de l'élève passé automatiquement à « exclu »).
 *
 * Fichier : custom/eleves/class/ecole_sanction_type.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleSanctionType
 */
class EcoleSanctionType extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_sanction_type';
	/** @var string */
	public $table_element = 'ecole_sanction_type';
	/** @var string */
	public $picto = 'fa-gavel';
	/** @var string */
	public $dirpage = 'sanction_type';

	const EXCLUSION_AUCUNE = 0;
	const EXCLUSION_TEMPORAIRE = 1;
	const EXCLUSION_DEFINITIVE = 2;

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_sanction', 'fk_type', 'Sanctions'),
	);
	public $listcolumns = array('ref', 'label', 'exclusion', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $exclusion = 0;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'exclusion' => array('type' => 'integer', 'label' => 'EffetSanction', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 35, 'help' => 'EffetSanctionHelp',
			'arrayofkeyval' => array(0 => 'EffetAucun', 1 => 'EffetExclusionTemporaire', 2 => 'EffetExclusionDefinitive')),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'PositionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : effet connu.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();
		if (!in_array((int) $this->exclusion, array(0, 1, 2), true)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('EffetSanction'));
		}
		return $this->finishValidation();
	}

	/**
	 * Types actifs (+ valeur actuelle) : id => objet léger (rowid, ref, label_fr, label_ar, exclusion).
	 *
	 * @param  DoliDB $db   Handler base
	 * @param  int    $keep Id à garder même s'il est inactif
	 * @return array<int,object>
	 */
	public static function actifs($db, $keep = 0)
	{
		$out = array();
		$sql = "SELECT rowid, ref, label_fr, label_ar, exclusion FROM ".$db->prefix()."ecole_sanction_type WHERE entity IN (".getEntity('ecole_sanction_type').")";
		$sql .= " AND (status = 1".($keep > 0 ? " OR rowid = ".((int) $keep) : "").") ORDER BY position, ref";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = $o;
		}
		return $out;
	}
}
