<?php
/**
 * Responsable d'un ou plusieurs élèves (père, mère, tuteur...).
 * Frères et sœurs = élèves ayant le même responsable. Toute modification est recopiée
 * sur le Tiers Dolibarr de chacun de ses enfants.
 *
 * Fichier : custom/eleves/class/ecole_responsable.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcoleResponsable
 */
class EcoleResponsable extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_responsable';
	/** @var string */
	public $table_element = 'ecole_responsable';
	/** @var string */
	public $picto = 'fa-user-friends';
	/** @var string */
	public $dirpage = 'responsable';

	public $required = array('nom_fr', 'telephone');
	public $unique = array('ref');
	public $usage = array(
		array('ecole_eleve', 'fk_responsable', 'EcoleElevesLies'),
	);
	public $listcolumns = array('ref', 'label', 'lien_parente', 'telephone', 'whatsapp', 'extra:enfants', 'status');
	public $searchcolumns = array('ref', 'label', 'lien_parente', 'telephone');
	public $sortdefault = 'nom_fr';
	public $labelfields = array('nom_fr', 'nom_ar');
	public $labeltitle = 'NomComplet';

	public $ref;
	public $nom_fr;
	public $nom_ar;
	public $lien_parente;
	public $telephone;
	public $whatsapp;
	public $email;
	public $adresse;
	public $note;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 5, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'help' => 'RefAutoHelp'),
		'nom_fr' => array('type' => 'varchar(255)', 'label' => 'NomFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300', 'autofocusoncreate' => 1),
		'nom_ar' => array('type' => 'varchar(255)', 'label' => 'NomAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'lien_parente' => array('type' => 'varchar(16)', 'label' => 'LienParente', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40, 'css' => 'minwidth200',
			'arrayofkeyval' => array('pere' => 'ParentePere', 'mere' => 'ParenteMere', 'tuteur' => 'ParenteTuteur', 'grand_parent' => 'ParenteGrandParent', 'oncle_tante' => 'ParenteOncleTante', 'frere_soeur' => 'ParenteFrereSoeur', 'autre' => 'ParenteAutre')),
		'telephone' => array('type' => 'phone', 'label' => 'Telephone', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 50, 'searchall' => 1, 'css' => 'minwidth200'),
		'whatsapp' => array('type' => 'phone', 'label' => 'WhatsApp', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'css' => 'minwidth200', 'help' => 'WhatsAppHelp'),
		'email' => array('type' => 'mail', 'label' => 'Email', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70, 'css' => 'minwidth300'),
		'adresse' => array('type' => 'text', 'label' => 'Adresse', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 80, 'css' => 'minwidth300'),
		'note' => array('type' => 'text', 'label' => 'Observations', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 90, 'css' => 'minwidth300'),
	);

	/**
	 * Création : référence automatique (R00001, R00002...).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = id, <0 = erreur
	 */
	public function create(User $user, $notrigger = 0)
	{
		if (empty($this->ref)) {
			$this->ref = $this->getNextRef();
		}
		return parent::create($user, $notrigger);
	}

	/**
	 * Modification : les coordonnées sont recopiées sur le Tiers de chaque enfant.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = OK, <0 = erreur
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = parent::update($user, $notrigger);
		if ($res > 0) {
			dol_include_once('/eleves/class/ecole_eleve.class.php');
			foreach ($this->getEnfants() as $enfant) {
				if ($enfant->syncTiers($user) < 0) {
					$this->error = $enfant->error;
					$this->errors = $enfant->errors;
					$res = -1;
					break;
				}
			}
		}
		if ($res > 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}
		return $res;
	}

	/**
	 * Validation : e-mail valide si saisi.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		$langs->load('eleves@eleves');
		parent::validate();
		if (!empty($this->email) && !isValidEmail($this->email)) {
			$this->errors[] = $langs->trans('ErrorBadEMail', $this->email);
		}
		if (!empty($this->lien_parente) && !isset($this->fields['lien_parente']['arrayofkeyval'][$this->lien_parente])) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('LienParente'));
		}
		return $this->finishValidation();
	}

	/**
	 * Prochaine référence libre : « R » + 5 chiffres.
	 *
	 * @return string
	 */
	public function getNextRef()
	{
		$sql = "SELECT MAX(CAST(SUBSTRING(ref, 2) AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '^R[0-9]+$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return 'R'.sprintf('%05d', ($o ? (int) $o->maxi : 0) + 1);
	}

	/**
	 * Enfants de ce responsable (tous statuts), triés par nom.
	 *
	 * @return EcoleEleve[]
	 */
	public function getEnfants()
	{
		dol_include_once('/eleves/class/ecole_eleve.class.php');
		if (empty($this->id)) {
			return array();
		}
		$eleve = new EcoleEleve($this->db);
		$list = $eleve->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array('fk_responsable' => (int) $this->id));
		return is_array($list) ? $list : array();
	}

	/**
	 * Texte court pour les listes de choix : « Nom — téléphone ».
	 *
	 * @return string
	 */
	public function getChoiceLabel()
	{
		return ecole_label($this).($this->telephone ? ' — '.$this->telephone : '');
	}
}
