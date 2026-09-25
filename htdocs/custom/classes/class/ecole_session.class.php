<?php
/**
 * Session d'examen (compositions de fin de trimestre).
 *
 * Fichier : custom/classes/class/ecole_session.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleSession
 */
class EcoleSession extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_session';
	/** @var string */
	public $table_element = 'ecole_session';
	/** @var string */
	public $picto = 'fa-file-signature';
	/** @var string */
	public $dirpage = 'session';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(array('ecole_edt_examen', 'fk_session', 'EcoleExamens'));
	public $listcolumns = array('ref', 'label', 'trimestre', 'date_debut', 'date_fin', 'status');
	public $sortdefault = 'trimestre';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $trimestre = 1;
	public $date_debut;
	public $date_fin;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'trimestre' => array('type' => 'integer', 'label' => 'Trimestre', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 40,
			'arrayofkeyval' => array(1 => 'Trimestre1', 2 => 'Trimestre2', 3 => 'Trimestre3')),
		'date_debut' => array('type' => 'date', 'label' => 'DateDebut', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50),
		'date_fin' => array('type' => 'date', 'label' => 'DateFin', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60),
	);

	/**
	 * Date (horodatage ou texte) au format SQL AAAA-MM-JJ.
	 *
	 * @param  int|string $v Date
	 * @return string
	 */
	public function sqlDate($v)
	{
		if (empty($v)) {
			return '';
		}
		return substr(is_numeric($v) ? $this->db->idate($v) : (string) $v, 0, 10);
	}

	/**
	 * Validation : la date de fin ne peut précéder la date de début.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		if (!empty($this->date_debut) && !empty($this->date_fin) && $this->date_fin < $this->date_debut) {
			$this->errors[] = $langs->trans('ErrorEcoleDateFinAvantDebut');
		}
		// Changement de période : aucune épreuve déjà programmée ne doit se retrouver en dehors
		if ($this->id > 0 && (!empty($this->date_debut) || !empty($this->date_fin))) {
			$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix()."ecole_edt_examen WHERE fk_session = ".((int) $this->id)." AND (1 = 0";
			if (!empty($this->date_debut)) {
				$sql .= " OR date_examen < '".$this->db->escape($this->sqlDate($this->date_debut))."'";
			}
			if (!empty($this->date_fin)) {
				$sql .= " OR date_examen > '".$this->db->escape($this->sqlDate($this->date_fin))."'";
			}
			$sql .= ")";
			$r = $this->db->query($sql);
			if ($r && ($o = $this->db->fetch_object($r)) && $o->nb > 0) {
				$this->errors[] = $langs->trans('ErrorEcoleSessionExamensHorsPeriode', $o->nb);
			}
		}
		if (!in_array((int) $this->trimestre, array(1, 2, 3), true)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Trimestre'));
		}

		return $this->finishValidation();
	}
}
