<?php
/**
 * Créneau horaire fixe, commun à toute l'école (ex. 08:15 - 10:00).
 * Modifiable à tout moment par l'établissement : l'emploi du temps suit automatiquement.
 *
 * Fichier : custom/classes/class/ecole_creneau.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleCreneau
 */
class EcoleCreneau extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_creneau';
	/** @var string */
	public $table_element = 'ecole_creneau';
	/** @var string */
	public $picto = 'fa-clock';
	/** @var string */
	public $dirpage = 'creneau';

	public $required = array('ref', 'label_fr', 'heure_debut', 'heure_fin');
	public $unique = array('ref');
	public $usage = array(array('ecole_edt_cours', 'fk_creneau', 'EcoleEmploiDuTemps'));
	public $listcolumns = array('ref', 'label', 'heure_debut', 'heure_fin', 'status');
	public $sortdefault = 'heure_debut';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $heure_debut;
	public $heure_fin;
	public $position = 0;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300'),
		'heure_debut' => array('type' => 'varchar(5)', 'label' => 'HeureDebut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40, 'inputtime' => 1),
		'heure_fin' => array('type' => 'varchar(5)', 'label' => 'HeureFin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 50, 'inputtime' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 60),
	);

	/**
	 * Validation : heures au format HH:MM, fin après début, pas de chevauchement avec un autre créneau actif.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		$re = '/^([01]\d|2[0-3]):[0-5]\d$/';
		$okdeb = preg_match($re, (string) $this->heure_debut);
		$okfin = preg_match($re, (string) $this->heure_fin);
		if (!empty($this->heure_debut) && !$okdeb) {
			$this->errors[] = $langs->trans('ErrorEcoleBadTime', $langs->transnoentities('HeureDebut'));
		}
		if (!empty($this->heure_fin) && !$okfin) {
			$this->errors[] = $langs->trans('ErrorEcoleBadTime', $langs->transnoentities('HeureFin'));
		}
		if ($okdeb && $okfin) {
			if ($this->heure_fin <= $this->heure_debut) {
				$this->errors[] = $langs->trans('ErrorEcoleFinAvantDebut');
			} elseif ($this->status == self::STATUS_ACTIVE) {
				$sql = "SELECT ref, heure_debut, heure_fin FROM ".$this->db->prefix().$this->table_element;
				$sql .= " WHERE entity IN (".getEntity($this->element).") AND status = 1";
				$sql .= " AND heure_debut < '".$this->db->escape($this->heure_fin)."' AND heure_fin > '".$this->db->escape($this->heure_debut)."'";
				if ($this->id > 0) {
					$sql .= " AND rowid <> ".((int) $this->id);
				}
				$resql = $this->db->query($sql);
				if ($resql && ($obj = $this->db->fetch_object($resql))) {
					$this->errors[] = $langs->trans('ErrorEcoleCreneauChevauche', $obj->ref, $obj->heure_debut, $obj->heure_fin);
				}
			}
		}

		return $this->finishValidation();
	}
}
