<?php
/**
 * Descripteur du module « Salaires » (bulletins de paie du personnel de l'école).
 *
 * Étape 1 : bulletin de paie par employé et par période libre (jamais deux bulletins sur des dates communes),
 * calcul depuis la présence du module Personnel (salaire fixe ou heures faites, heures supplémentaires,
 * remplacements, retenues selon les règles), lignes ajoutées à la main, validation, réouverture et annulation
 * avec motif, paiement par le salaire natif de Dolibarr (compte de trésorerie du mode de paiement),
 * annulation du paiement, fiche de paie PDF (résumé + détail des séances), salaires d'un lot d'employés.
 * Étape 2 : avances sur salaire (retenues sur le bulletin suivant) et prêts au personnel (mensualités retenues sur
 * les bulletins), reçus à signer ; menu principal « Salaires » dans la barre du haut.
 * À venir : primes, fiches de paie dans l'espace du personnel.
 *
 * Fichier : custom/salaires/core/modules/modSalaires.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modSalaires
 */
class modSalaires extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 105100;
		$this->rights_class = 'salaires';
		$this->family = 'other';
		$this->module_position = '503';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Bulletins de paie du personnel de l'école : calcul depuis la présence, validation, paiement, fiches de paie";
		$this->descriptionlong = "Salaire fixe ou heures faites, heures supplémentaires, remplacements, retenues selon les règles, paiement par le module Salaires natif de Dolibarr, fiche de paie PDF.";
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-money-check-alt';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array('/salaires/temp');
		$this->config_page_url = array("setup.php@salaires");

		$this->hidden = false;
		// Personnel (employés, présence, règles de paie), Salaires natif (paiement), Banques (trésorerie)
		$this->depends = array('modPersonnel', 'modSalaries', 'modBanque');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("salaires@salaires");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		$this->const = array(
			array('SALAIRES_REF_PREFIXE', 'chaine', 'BP', 'Préfixe des numéros de bulletin de paie', 0, 'current', 0),
			array('SALAIRES_REF_LONGUEUR', 'chaine', '6', 'Nombre de chiffres du numéro de bulletin', 0, 'current', 0),
			array('SALAIRES_PDF_SEANCES', 'chaine', '1', 'Fiche de paie : ajouter le détail des séances (enseignants)', 0, 'current', 0),
			array('SALAIRES_AVANCE_MAX_PCT', 'chaine', '0', 'Avances en cours au plus égales à ce % du salaire (0 = pas de plafond)', 0, 'current', 0),
			array('SALAIRES_PRET_ECHEANCES_MAX', 'chaine', '24', 'Nombre de mensualités d\'un prêt au plus', 0, 'current', 0),
			array('SALAIRES_PRET_UN_SEUL', 'chaine', '1', 'Un seul prêt en cours par employé (1 = oui)', 0, 'current', 0),
			array('SALAIRES_PRET_MAX_PCT', 'chaine', '0', 'Mensualité d\'un prêt au plus égale à ce % du salaire (0 = pas de plafond)', 0, 'current', 0),
		);

		$this->boxes = array();
		$this->cronjobs = array();

		// Droits : une rubrique et une action par droit (ex. $user->hasRight('salaires', 'bulletin', 'lire'))
		$this->rights = array();
		$r = 0;
		$defs = array(
			array(105101, 'Salaires : voir les bulletins de paie et les lots', 'bulletin', 'lire'),
			array(105102, 'Salaires : créer, recalculer et modifier un bulletin en brouillon, préparer les salaires d\'un lot', 'bulletin', 'creer'),
			array(105103, 'Salaires : valider un bulletin', 'bulletin', 'valider'),
			array(105104, 'Salaires : rouvrir un bulletin validé (retour en brouillon, avec motif)', 'bulletin', 'rouvrir'),
			array(105105, 'Salaires : annuler un bulletin non payé (avec motif)', 'bulletin', 'annuler'),
			array(105106, 'Salaires : exporter les listes et imprimer les fiches de paie', 'bulletin', 'exporter'),
			array(105111, 'Paiement : payer un salaire', 'paiement', 'payer'),
			array(105112, 'Paiement : annuler le paiement d\'un salaire (avec motif)', 'paiement', 'annuler'),
			array(105121, 'Règles de paie propres à un employé (retenues, retards, heures supplémentaires, remplacements)', 'regle', 'modifier'),
			array(105131, 'Avances et prêts : voir', 'avance', 'lire'),
			array(105132, 'Avances et prêts : donner une avance ou un prêt (l\'argent sort de la trésorerie)', 'avance', 'donner'),
			array(105133, 'Avances et prêts : annuler (avec motif) une avance ou un prêt dont rien n\'est encore retenu', 'avance', 'annuler'),
			array(105191, 'Configuration : numérotation, comptes de trésorerie, fiche de paie', 'config', 'gerer'),
		);
		foreach ($defs as $d) {
			$this->rights[$r][0] = $d[0];
			$this->rights[$r][1] = $d[1];
			$this->rights[$r][3] = 0;
			$this->rights[$r][4] = $d[2];
			$this->rights[$r][5] = $d[3];
			$r++;
		}

		// Menus : un menu « Salaires » avec son icône dans la barre du haut
		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'MenuSalaires',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'salaires',
			'leftmenu' => '',
			'url'      => '/salaires/bulletin/list.php?sortfield=t.rowid&sortorder=DESC',
			'langs'    => 'salaires@salaires',
			'position' => 1002,
			'enabled'  => 'isModEnabled("salaires")',
			'perms'    => '$user->hasRight("salaires", "bulletin", "lire") || $user->hasRight("salaires", "avance", "lire")',
			'target'   => '',
			'user'     => 2,
		);
		// [leftmenu, parent leftmenu ('' = racine), titre, url, droit « rubrique.action », position, icône]
		$left = array(
			array('sal_bul',     '',         'BulletinsDePaie',       '/salaires/bulletin/list.php?sortfield=t.rowid&sortorder=DESC', 'bulletin.lire', 10, 'fa-money-check-alt'),
			array('sal_new',     'sal_bul',  'MenuNouveauBulletin',   '/salaires/bulletin/card.php?action=create',                    'bulletin.creer', 11, ''),
			array('sal_avalid',  'sal_bul',  'MenuAValider',          '/salaires/bulletin/list.php?search_status=0',                  'bulletin.lire', 12, ''),
			array('sal_apayer',  'sal_bul',  'MenuAPayer',            '/salaires/bulletin/list.php?search_status=1',                  'bulletin.lire', 13, ''),
			array('sal_lots',    '',         'MenuLotsSalaires',      '/salaires/lot/list.php?sortfield=t.rowid&sortorder=DESC',      'bulletin.lire', 20, 'fa-layer-group'),
			array('sal_lotnew',  'sal_lots', 'MenuPreparerSalaires',  '/salaires/lot/card.php?action=create',                         'bulletin.creer', 21, ''),
			array('sal_av',      '',         'AvancesSurSalaire',     '/salaires/avance/list.php?sortfield=t.rowid&sortorder=DESC',   'avance.lire', 30, 'fa-hand-holding-usd'),
			array('sal_avnew',   'sal_av',   'NouvelleAvance',        '/salaires/avance/card.php?action=create',                      'avance.donner', 31, ''),
			array('sal_avcours', 'sal_av',   'MenuEnCours',           '/salaires/avance/list.php?search_extra_etat=encours',           'avance.lire', 32, ''),
			array('sal_pr',      '',         'PretsPersonnel',        '/salaires/pret/list.php?sortfield=t.rowid&sortorder=DESC',     'avance.lire', 40, 'fa-piggy-bank'),
			array('sal_prnew',   'sal_pr',   'NouveauPret',           '/salaires/pret/card.php?action=create',                        'avance.donner', 41, ''),
			array('sal_prcours', 'sal_pr',   'MenuEnCours',           '/salaires/pret/list.php?search_extra_etat=encours',             'avance.lire', 42, ''),
			array('sal_config',  '',         'MenuConfigSalaires',    '/salaires/admin/setup.php',                                    'config.gerer', 90, 'fa-cog'),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=salaires';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$p = explode('.', $m[4]);
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'prefix'   => $m[6] !== '' ? img_picto('', $m[6], 'class="pictofixedwidth paddingright"') : '',
				'mainmenu' => 'salaires',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'salaires@salaires',
				'position' => 1000 + $m[5],
				'enabled'  => 'isModEnabled("salaires")',
				'perms'    => '$user->hasRight("salaires", "'.$p[0].'", "'.$p[1].'")',
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation : crée les tables (sql/) ; comptes de trésorerie proposés = ceux des paiements des élèves.
	 * Rien n'est jamais supprimé ici : réactiver le module ne touche pas aux données.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/salaires/sql/');
		if ($result < 0) {
			return -1;
		}
		$res = $this->_init(array(), $options);
		if ($res <= 0) {
			return $res;
		}
		// Comptes de trésorerie par mode : repris des paiements des élèves s'ils ne sont pas encore réglés
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		$resql = $this->db->query("SELECT name, value FROM ".$this->db->prefix()."const WHERE name LIKE 'ELEVES\\_COMPTE\\_MODE\\_%' AND entity IN (0, ".((int) $conf->entity).")");
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$nom = 'SALAIRES_COMPTE_MODE_'.substr($o->name, strlen('ELEVES_COMPTE_MODE_'));
			if (!getDolGlobalString($nom) && (int) $o->value > 0) {
				dolibarr_set_const($this->db, $nom, (int) $o->value, 'chaine', 0, '', $conf->entity);
			}
		}
		return $res;
	}

	/**
	 * Désactivation du module (ne supprime aucune donnée).
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
