<?php
/**
 * Motifs d'exonération des frais d'inscription (réinscription, bourse, enfant du personnel...).
 * Liste configurable par l'établissement.
 *
 * Fichier : custom/eleves/class/ecole_motif_exoneration.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleMotifExoneration
 */
class EcoleMotifExoneration extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_motif_exoneration';
	/** @var string */
	public $table_element = 'ecole_motif_exoneration';
	/** @var string */
	public $picto = 'fa-hand-holding-heart';
	/** @var string */
	public $dirpage = 'motif_exoneration';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_eleve', 'fk_motif_exo', 'Eleves'),
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
	 * Motifs pour une liste de choix : id => libellé (actifs + valeur actuelle).
	 *
	 * @param  DoliDB $db   Handler base
	 * @param  int    $keep Id à garder même s'il est inactif
	 * @return array<int,string>
	 */
	public static function choix($db, $keep = 0)
	{
		$out = array();
		$sql = "SELECT rowid, label_fr, label_ar FROM ".$db->prefix()."ecole_motif_exoneration WHERE entity IN (".getEntity('ecole_motif_exoneration').")";
		$sql .= " AND (status = 1".($keep > 0 ? " OR rowid = ".((int) $keep) : "").") ORDER BY position, ref";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = ecole_label($o);
		}
		return $out;
	}
}
