<?php
/**
 * Engagement signé par le responsable à l'inscription (règlement intérieur, paiement...) : titre et texte en
 * français et en arabe, variables remplacées à l'impression. Liste configurable (ajouter, désactiver, ordonner).
 * Tous les engagements actifs sont imprimés dans un seul document (bouton « Engagement du responsable »).
 *
 * Fichier : custom/eleves/class/ecole_engagement.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleEngagement
 */
class EcoleEngagement extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_engagement';
	/** @var string */
	public $table_element = 'ecole_engagement';
	/** @var string */
	public $picto = 'fa-file-signature';
	/** @var string */
	public $dirpage = 'engagement';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'label', 'signature_eleve', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $texte_fr;
	public $texte_ar;
	public $signature_eleve = 0;
	public $position = 0;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(32)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'TitreFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'TitreAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'texte_fr' => array('type' => 'text', 'label' => 'TexteFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40, 'css' => 'quatrevingtpercent', 'help' => 'EngagementVariablesAide'),
		'texte_ar' => array('type' => 'text', 'label' => 'TexteAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50, 'css' => 'quatrevingtpercent', 'help' => 'EngagementVariablesAide'),
		'signature_eleve' => array('type' => 'boolean', 'label' => 'SignatureEleve', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 60, 'help' => 'SignatureEleveAide'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 70, 'help' => 'PositionHelp'),
	);

	/**
	 * Engagements actifs, dans l'ordre.
	 *
	 * @param  DoliDB $db Handler base
	 * @return object[]
	 */
	public static function actifs($db)
	{
		$out = array();
		$sql = "SELECT rowid, ref, label_fr, label_ar, texte_fr, texte_ar, signature_eleve FROM ".$db->prefix()."ecole_engagement";
		$sql .= " WHERE entity IN (".getEntity('ecole_engagement').") AND status = 1 ORDER BY position, ref";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}
}
