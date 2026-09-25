<?php
/**
 * Salle de l'établissement. Une salle inactive ne peut plus être choisie ; elle ne peut être
 * désactivée tant qu'elle est utilisée dans un emploi du temps ou un examen à venir.
 *
 * Fichier : custom/classes/class/ecole_salle.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleSalle
 */
class EcoleSalle extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_salle';
	/** @var string */
	public $table_element = 'ecole_salle';
	/** @var string */
	public $picto = 'fa-door-open';
	/** @var string */
	public $dirpage = 'salle';

	public $required = array('ref', 'label_fr', 'type_salle');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_classe', 'fk_salle', 'EcoleClasses'),
		array('ecole_edt_cours', 'fk_salle', 'EcoleEmploiDuTemps'),
		array('ecole_edt_examen', 'fk_salle', 'EcoleExamens'),
	);
	public $listcolumns = array('ref', 'label', 'type_salle', 'capacite', 'extra:attitree', 'extra:heures', 'status');

	public $ref;
	public $label_fr;
	public $label_ar;
	public $type_salle = 'classe';
	public $capacite;
	public $emplacement;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'type_salle' => array('type' => 'varchar(16)', 'label' => 'TypeSalle', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'classe', 'position' => 40,
			'arrayofkeyval' => array('classe' => 'SalleTypeClasse', 'labo' => 'SalleTypeLabo', 'info' => 'SalleTypeInfo', 'sport' => 'SalleTypeSport', 'autre' => 'SalleTypeAutre')),
		'capacite' => array('type' => 'integer', 'label' => 'Capacite', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50),
		'emplacement' => array('type' => 'varchar(128)', 'label' => 'Emplacement', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth300'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : capacité positive ; désactivation refusée si la salle est encore planifiée.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		if ($this->capacite !== null && $this->capacite !== '' && (int) $this->capacite < 0) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Capacite'));
		}
		if ($this->id > 0 && (int) $this->status === self::STATUS_INACTIVE) {
			$p = $this->db->prefix();
			$r = $this->db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_edt_cours WHERE fk_salle = ".((int) $this->id));
			$nbc = ($r && ($o = $this->db->fetch_object($r))) ? (int) $o->nb : 0;
			$r = $this->db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_edt_examen WHERE fk_salle = ".((int) $this->id)." AND date_examen >= '".$this->db->escape(dol_print_date(dol_now(), '%Y-%m-%d'))."'");
			$nbx = ($r && ($o = $this->db->fetch_object($r))) ? (int) $o->nb : 0;
			if ($nbc + $nbx > 0) {
				$this->errors[] = $langs->trans('ErrorEcoleSalleDesactivation', $nbc, $nbx);
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Classes dont c'est la salle attitrée.
	 *
	 * @return array<int,object> rowid => ligne (ref, label_fr, label_ar)
	 */
	public function getClassesAttitrees()
	{
		$out = array();
		$r = $this->db->query("SELECT rowid, ref, label_fr, label_ar FROM ".$this->db->prefix()."ecole_classe WHERE fk_salle = ".((int) $this->id)." ORDER BY fk_niveau, rowid");
		if ($r) {
			while ($o = $this->db->fetch_object($r)) {
				$out[(int) $o->rowid] = $o;
			}
		}
		return $out;
	}
}
