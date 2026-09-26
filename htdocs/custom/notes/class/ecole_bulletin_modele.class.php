<?php
/**
 * Modèle de bulletin : langue (bilingue « mixte » = chaque matière dans sa langue d'enseignement,
 * tout en français, tout en arabe), style visuel du PDF et informations affichées.
 *
 * Fichier : custom/notes/class/ecole_bulletin_modele.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleBulletinModele
 */
class EcoleBulletinModele extends EcoleObject
{
	/** @var string */
	public $module = 'notes';
	/** @var string */
	public $element = 'ecole_bulletin_modele';
	/** @var string */
	public $table_element = 'ecole_bulletin_modele';
	/** @var string */
	public $picto = 'fa-file-alt';
	/** @var string */
	public $dirpage = 'modele';

	public $required = array('ref', 'label_fr', 'langue', 'style');
	public $unique = array('ref');
	public $usage = array(array('ecole_note_regle', 'fk_modele', 'ReglesNiveaux'));
	public $listcolumns = array('ref', 'label', 'langue', 'style', 'couleur', 'simplifie', 'position', 'extra:apercu', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $langue = 'mixte';
	/** @var string Mise en page : classique, lignes, encadre */
	public $style = 'classique';
	/** @var string Couleur : bleu, vert, bordeaux, violet, orange, gris, aucune */
	public $couleur = 'bleu';
	/** @var string|null Style d'en-tête du PDF (vide = celui de la configuration du module Classes) */
	public $entete;
	public $simplifie = 0;
	public $aff_detail = 1;
	public $aff_coef = 1;
	public $aff_rang_matiere = 0;
	public $aff_moy_classe = 1;
	public $aff_min_max = 0;
	public $aff_rang = 1;
	public $aff_rappel = 1;
	public $aff_distinction = 1;
	public $aff_signature_parent = 1;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'langue' => array('type' => 'varchar(8)', 'label' => 'LangueBulletin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'mixte', 'position' => 40,
			'arrayofkeyval' => array('mixte' => 'LangueMixte', 'fr' => 'LangueToutFrancais', 'ar' => 'LangueToutArabe'), 'help' => 'LangueBulletinHelp'),
		'style' => array('type' => 'varchar(16)', 'label' => 'StyleBulletin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'classique', 'position' => 45,
			'arrayofkeyval' => array('classique' => 'MiseEnPageClassique', 'lignes' => 'MiseEnPageLignes', 'encadre' => 'MiseEnPageEncadre'), 'help' => 'StyleBulletinHelp'),
		'couleur' => array('type' => 'varchar(16)', 'label' => 'CouleurBulletin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'bleu', 'position' => 46,
			'arrayofkeyval' => array('bleu' => 'CouleurBleu', 'vert' => 'CouleurVert', 'bordeaux' => 'CouleurBordeaux', 'violet' => 'CouleurViolet', 'orange' => 'CouleurOrange', 'gris' => 'CouleurGris', 'aucune' => 'CouleurAucune'), 'help' => 'CouleurBulletinHelp'),
		'entete' => array('type' => 'varchar(32)', 'label' => 'EnteteBulletin', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 47,
			'arrayofkeyval' => array('noir_blanc' => 'EnteteNoirBlanc', 'bandeau_bleu' => 'PdfHeaderBandeauBleu', 'bandeau_sombre' => 'PdfHeaderBandeauSombre', 'minimal' => 'PdfHeaderMinimal', 'compact' => 'PdfHeaderCompact', 'classique' => 'PdfHeaderClassique', 'image' => 'PdfHeaderImage'), 'help' => 'EnteteBulletinHelp'),
		'simplifie' => array('type' => 'boolean', 'label' => 'ModeleSimplifie', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 50, 'help' => 'ModeleSimplifieHelp'),
		'aff_detail' => array('type' => 'boolean', 'label' => 'AffDetail', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 60),
		'aff_coef' => array('type' => 'boolean', 'label' => 'AffCoef', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 61),
		'aff_rang_matiere' => array('type' => 'boolean', 'label' => 'AffRangMatiere', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 62),
		'aff_moy_classe' => array('type' => 'boolean', 'label' => 'AffMoyClasse', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 63),
		'aff_min_max' => array('type' => 'boolean', 'label' => 'AffMinMax', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 64),
		'aff_rang' => array('type' => 'boolean', 'label' => 'AffRang', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 65),
		'aff_rappel' => array('type' => 'boolean', 'label' => 'AffRappel', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 66),
		'aff_distinction' => array('type' => 'boolean', 'label' => 'AffDistinction', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 67),
		'aff_signature_parent' => array('type' => 'boolean', 'label' => 'AffSignatureParent', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 68),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 80),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 90, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : mise en page, couleur et en-tête parmi les choix proposés (anciens styles « moderne » et
	 * « sobre » convertis en mise en page classique verte / sans couleur).
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		if ($this->style === 'moderne') {
			$this->style = 'classique';
			$this->couleur = 'vert';
		} elseif ($this->style === 'sobre') {
			$this->style = 'classique';
			$this->couleur = 'aucune';
		}
		if (empty($this->entete) || $this->entete === '-1') {
			$this->entete = null;
		}
		parent::validate();
		foreach (array('style', 'couleur', 'entete') as $k) {
			if ($this->$k !== null && !isset($this->fields[$k]['arrayofkeyval'][$this->$k])) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities($this->fields[$k]['label']));
			}
		}
		return $this->finishValidation();
	}

	/**
	 * Modèles actifs pour une liste de choix : id => libellé.
	 *
	 * @param  DoliDB $db Handler base
	 * @return array<int,string>
	 */
	public static function choix($db)
	{
		$out = array();
		$resql = $db->query("SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_bulletin_modele WHERE entity IN (".getEntity('ecole_bulletin_modele').") AND status = 1 ORDER BY position, ref");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = ecole_label($o);
		}
		return $out;
	}
}
