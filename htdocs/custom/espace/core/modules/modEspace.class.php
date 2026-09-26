<?php
/**
 * Descripteur du module « Espace parents et élèves ».
 *
 * Pages simples (faites d'abord pour le téléphone, sans les menus Dolibarr) où le responsable voit tous ses
 * enfants inscrits et où l'élève se voit lui-même : notes, bulletins, emploi du temps, absences, sanctions,
 * paiements et impayés, pièces manquantes, certificat de scolarité.
 * Chaque accès est un compte utilisateur Dolibarr SANS AUCUN DROIT (identifiant = code du responsable ou
 * matricule), créé par l'école depuis la fiche avec un mot de passe provisoire et une fiche d'accès à imprimer.
 * Un tel compte ne peut pas ouvrir l'interface de gestion : la connexion y est renvoyée vers l'espace.
 *
 * Fichier : custom/espace/core/modules/modEspace.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modEspace
 */
class modEspace extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 104900;
		$this->rights_class = 'espace';
		$this->family = 'other';
		$this->module_position = '503';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Espace parents et élèves : notes, bulletins, emploi du temps, absences, paiements, certificat";
		$this->descriptionlong = "Accès des responsables (tous leurs enfants) et des élèves à des pages simples adaptées au téléphone, en arabe ou en français. Comptes créés par l'école avec mot de passe provisoire et fiche d'accès imprimée.";
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-house-user';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array();
		$this->config_page_url = array("setup.php@espace");

		// Crochet « afterLogin » : un compte de l'espace qui se connecte à l'interface de gestion est renvoyé vers l'espace
		$this->module_parts = array('hooks' => array('data' => array('login'), 'entity' => '0'));

		$this->hidden = false;
		$this->depends = array('modClasses', 'modEleves');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("espace@espace");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		$this->const = array(
			array('ESPACE_LANGUE', 'chaine', 'ar_SA', 'Langue par défaut de l\'espace parents (ar_SA ou fr_FR)', 0, 'current', 0),
			array('ESPACE_ACCES_ELEVES', 'chaine', '1', 'Les élèves peuvent avoir leur propre accès (1 = oui)', 0, 'current', 0),
		);

		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$defs = array(
			array(104901, 'Espace parents : voir les accès (fiches, liste, dernières connexions)', 'acces', 'lire'),
			array(104902, 'Espace parents : créer un accès, réinitialiser le mot de passe, désactiver ou réactiver', 'acces', 'gerer'),
			array(104991, 'Espace parents : configuration (langue, message WhatsApp, accès des élèves)', 'config', 'gerer'),
		);
		foreach ($defs as $d) {
			$this->rights[$r][0] = $d[0];
			$this->rights[$r][1] = $d[1];
			$this->rights[$r][3] = 0;
			$this->rights[$r][4] = $d[2];
			$this->rights[$r][5] = $d[3];
			$r++;
		}

		// Menus : dans le menu « Élèves »
		$this->menu = array();
		$r = 0;
		$left = array(
			array('espace_acces',  '',             'MenuEspaceParents',  '/espace/list.php',              '$user->hasRight("espace", "acces", "lire")', 25),
			array('espace_dash',   'espace_acces', 'EdTableauDeBord',    '/espace/index.php',             '$user->hasRight("espace", "acces", "lire")', 0),
			array('espace_jamais', 'espace_acces', 'MenuJamaisConnectes', '/espace/list.php?search_etat=jamais', '$user->hasRight("espace", "acces", "lire")', 1),
			array('espace_config', 'espace_acces', 'MenuConfigEspace',   '/espace/admin/setup.php',       '$user->hasRight("espace", "config", "gerer")', 3),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=eleves';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'mainmenu' => 'eleves',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'espace@espace',
				'position' => 1000 + $m[5],
				'enabled'  => 'isModEnabled("espace")',
				'perms'    => $m[4],
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation du module : crée la table des accès et installe la règle des adresses propres. Rien n'est supprimé en réactivant.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/espace/sql/');
		if ($result < 0) {
			return -1;
		}
		$res = $this->_init(array(), $options);
		// Adresses propres de l'espace : bloc du module dans le fichier .htaccess de la racine (aucun fichier de Dolibarr modifié)
		if ($res > 0) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
			dol_include_once('/espace/core/lib/espace.lib.php');
			espace_htaccess_installer($this->db);
		}
		return $res;
	}

	/**
	 * Désactivation du module (les comptes et la table restent).
	 *
	 * @param  string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
