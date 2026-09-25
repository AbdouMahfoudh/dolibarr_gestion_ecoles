<?php
/**
 * Pièce à fournir pour le dossier d'un employé (copie de la carte d'identité, diplômes, contrat...).
 * Liste configurable par l'établissement ; une pièce peut ne concerner que certaines catégories
 * (ex. diplômes pour les enseignants) ; une pièce désactivée n'est plus demandée.
 *
 * Fichier : custom/personnel/class/ecole_employe_doc_type.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleEmployeDocType
 */
class EcoleEmployeDocType extends EcoleObject
{
	/** @var string */
	public $module = 'personnel';
	/** @var string */
	public $element = 'ecole_employe_doc_type';
	/** @var string */
	public $table_element = 'ecole_employe_doc_type';
	/** @var string */
	public $picto = 'fa-file-alt';
	/** @var string */
	public $dirpage = 'doc_type';

	public $required = array('ref', 'label_fr');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_employe_document', 'fk_doc_type', 'DossiersEmployes'),
	);
	public $listcolumns = array('ref', 'label', 'categories', 'position', 'status');
	public $sortdefault = 'position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $categories;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'categories' => array('type' => 'checkbox', 'label' => 'CategoriesConcernees', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 35, 'help' => 'CategoriesConcerneesHelp'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'help' => 'PositionHelp'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 50, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : catégories connues seulement.
	 *
	 * @return int
	 */
	public function validate()
	{
		$connues = ecole_categories_utilisateur();
		$cats = array();
		foreach (is_array($this->categories) ? $this->categories : explode(',', (string) $this->categories) as $c) {
			$c = trim($c);
			if (isset($connues[$c])) {
				$cats[$c] = $c;
			}
		}
		$this->categories = $cats ? implode(',', $cats) : null;
		return parent::validate();
	}

	/**
	 * Affichage : catégories en badges (« Tous les employés » si vide).
	 *
	 * @param array  $val       Définition du champ
	 * @param string $key       Nom du champ
	 * @param mixed  $value     Valeur
	 * @param string $moreparam Paramètres
	 * @param string $keysuffix Suffixe
	 * @param string $keyprefix Préfixe
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		global $langs;
		if ($key === 'categories') {
			return (string) $value === '' ? '<span class="opacitymedium">'.$langs->trans('TousLesEmployes').'</span>' : personnel_categories_badges((string) $value);
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Saisie : catégories en choix multiple (aucune choisie = tous les employés).
	 *
	 * @param array  $val         Définition du champ
	 * @param string $key         Nom du champ
	 * @param mixed  $value       Valeur
	 * @param string $moreparam   Paramètres HTML
	 * @param string $keysuffix   Suffixe
	 * @param string $keyprefix   Préfixe
	 * @param mixed  $morecss     CSS
	 * @param int    $nonewbutton Sans bouton « nouveau »
	 * @return string
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $langs, $form;
		if ($key === 'categories') {
			if (!is_object($form)) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
				$form = new Form($this->db);
			}
			$sel = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
			$out = $form->multiselectarray($keyprefix.$key.$keysuffix, personnel_categories_choix(), $sel, 0, 0, 'minwidth300', 0, '100%');
			return $out.'<br><span class="opacitymedium small">'.$langs->trans('AucuneCaseTous').'</span>';
		}
		return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
	}
}
