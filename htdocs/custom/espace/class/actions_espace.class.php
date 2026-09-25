<?php
/**
 * Crochets du module « Espace parents et élèves ».
 *
 * afterLogin (contexte « login ») : un compte de l'espace (parent ou élève) qui se connecte par la page de
 * connexion de l'interface de gestion n'y entre pas : sa session Dolibarr est fermée et il est connecté
 * directement à l'espace. Ces comptes n'ont de toute façon aucun droit.
 *
 * Fichier : custom/espace/class/actions_espace.class.php
 */

/**
 * Class ActionsEspace
 */
class ActionsEspace
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var string[] */
	public $errors = array();
	/** @var array */
	public $results = array();
	/** @var string */
	public $resprints = '';

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Après une connexion réussie à l'interface de gestion.
	 *
	 * @param  array  $parameters Paramètres
	 * @param  User   $object     Utilisateur connecté
	 * @param  string $action     Action
	 * @param  object $hookmanager Gestionnaire
	 * @return int
	 */
	public function afterLogin($parameters, &$object, &$action, $hookmanager)
	{
		if (!is_object($object) || empty($object->id) || !isModEnabled('espace')) {
			return 0;
		}
		dol_include_once('/espace/core/lib/espace.lib.php');
		dol_include_once('/espace/core/lib/portail.lib.php');
		$acces = espace_acces_par_user($this->db, (int) $object->id);
		if (!$acces) {
			return 0;
		}
		if ($acces->type === ESPACE_EMPLOYE) {
			$emp = espace_cible($this->db, ESPACE_EMPLOYE, (int) $acces->fk_cible);
			if (!empty($object->admin) || ($emp && espace_employe_gestion($this->db, $emp))) {
				return 0; // garde l'interface de gestion (son espace s'ouvre par « Mon espace »)
			}
		}
		// Valide la mise à jour de la date de connexion faite par Dolibarr, puis quitte l'interface de gestion
		$this->db->commit();
		foreach (array_keys($_SESSION) as $k) {
			if (strpos($k, 'dol_') === 0) {
				unset($_SESSION[$k]);
			}
		}
		if (espace_etat($this->db, $acces) === 'actif') {
			espace_session_ouvrir($this->db, $acces);
			header('Location: '.espace_page_url(''));
		} else {
			header('Location: '.espace_page_url('connexion', array('refus' => 1)));
		}
		exit;
	}
}
