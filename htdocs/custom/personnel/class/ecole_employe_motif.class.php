<?php
/**
 * Motifs configurables du module Personnel : absences (maladie, raison familiale...), suspensions / congés
 * (congé maladie, maternité, sans solde...) et départs (démission, fin de contrat, licenciement...).
 * Un nouveau cas se crée ici, sans toucher au programme.
 *
 * Fichier : custom/personnel/class/ecole_employe_motif.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleEmployeMotif
 */
class EcoleEmployeMotif extends EcoleObject
{
	/** @var string */
	public $module = 'personnel';
	/** @var string */
	public $element = 'ecole_employe_motif';
	/** @var string */
	public $table_element = 'ecole_employe_motif';
	/** @var string */
	public $picto = 'fa-tags';
	/** @var string */
	public $dirpage = 'motif';

	public $required = array('ref', 'label_fr', 'usage_motif');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_absence_perso', 'fk_motif', 'AbsencesPersonnel'),
		array('ecole_employe_statut', 'fk_motif', 'HistoriqueStatuts'),
		array('ecole_cours_prof', 'fk_motif', 'PresenceEnseignant'),
	);
	public $listcolumns = array('ref', 'label', 'usage_motif', 'position', 'status');
	public $sortdefault = 'usage_motif,position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $usage_motif = 'absence';
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'usage_motif' => array('type' => 'varchar(16)', 'label' => 'UsageMotif', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 35, 'default' => 'absence', 'help' => 'UsageMotifHelp',
			'arrayofkeyval' => array('absence' => 'UsageAbsence', 'suspension' => 'UsageSuspension', 'depart' => 'UsageDepart')),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'PositionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Motifs d'un usage pour une liste de choix : id => libellé (actifs + valeur actuelle).
	 *
	 * @param  DoliDB $db    Handler base
	 * @param  string $usage absence | suspension | depart
	 * @param  int    $keep  Id à garder même s'il est inactif
	 * @return array<int,string>
	 */
	public static function choix($db, $usage, $keep = 0)
	{
		$out = array();
		$sql = "SELECT rowid, label_fr, label_ar FROM ".$db->prefix()."ecole_employe_motif WHERE entity IN (".getEntity('ecole_employe_motif').")";
		$sql .= " AND usage_motif = '".$db->escape($usage)."' AND (status = 1".($keep > 0 ? " OR rowid = ".((int) $keep) : "").") ORDER BY position, ref";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = ecole_label($o);
		}
		return $out;
	}
}
