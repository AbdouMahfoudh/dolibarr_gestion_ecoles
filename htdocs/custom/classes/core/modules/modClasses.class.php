<?php
/**
 * Descripteur du module « Classes ».
 *
 * Gère les niveaux, sections, classes, matières (avec coefficient par classe),
 * la mensualité de chaque classe et les emplois du temps (cours et examens).
 *
 * Fichier : custom/classes/core/modules/modClasses.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modClasses
 */
class modClasses extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 104600;
		$this->rights_class = 'classes';
		$this->family = 'other';
		$this->module_position = '500';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Classes, matières, coefficients, mensualités et emplois du temps de l'établissement";
		$this->descriptionlong = "Gestion des niveaux, sections, classes et matières (coefficient par classe), mensualité de chaque classe, emploi du temps des cours et emploi du temps des périodes d'examens.";
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-chalkboard';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array();
		$this->config_page_url = array("setup.php@classes");

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("classes@classes");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		// Jours ouvrables par défaut : lundi à samedi (1 = lundi ... 7 = dimanche)
		$this->const = array(
			array('ECOLE_JOURS_OUVRABLES', 'chaine', '1,2,3,4,5,6', 'Jours ouvrables de l\'école', 0, 'current', 0),
		);

		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$r = 0;
		$defs = array(
			array(104601, 'Consulter les classes, matières et emplois du temps', 'lire'),
			array(104602, 'Créer / modifier les classes et les matières', 'ecrire'),
			array(104603, 'Supprimer les classes et les matières', 'supprimer'),
			array(104604, 'Gérer les emplois du temps (cours et examens)', 'edt'),
			array(104605, 'Configurer niveaux, sections, créneaux et sessions d\'examen', 'config'),
		);
		foreach ($defs as $d) {
			$this->rights[$r][0] = $d[0];
			$this->rights[$r][1] = $d[1];
			$this->rights[$r][3] = 0;
			$this->rights[$r][4] = $d[2];
			$this->rights[$r][5] = '';
			$r++;
		}

		// Menus
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'Classes',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'classes',
			'leftmenu' => '',
			'url'      => '/classes/index.php',
			'langs'    => 'classes@classes',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("classes")',
			'perms'    => '$user->hasRight("classes", "lire")',
			'target'   => '',
			'user'     => 2,
		);

		// [leftmenu, parent leftmenu ('' = racine), titre, url, permission, position]
		$left = array(
			array('ecole_classes',   '',               'MenuClasses',        '/classes/classe/list.php',                 'lire',      10),
			array('ecole_classes_n', 'ecole_classes',  'MenuNouvelleClasse', '/classes/classe/card.php?action=create',   'ecrire',    11),
			array('ecole_edt',       '',               'MenuEmploisDuTemps', '/classes/classe/emplois.php',              'lire',      15),
			array('ecole_matieres',  '',               'MenuMatieres',       '/classes/matiere/list.php',                'lire',      20),
			array('ecole_matieres_n', 'ecole_matieres', 'MenuNouvelleMatiere', '/classes/matiere/card.php?action=create', 'ecrire',   21),
			array('ecole_salles',    '',               'MenuSalles',         '/classes/salle/list.php',                  'lire',      25),
			array('ecole_salles_n',  'ecole_salles',   'MenuNouvelleSalle',  '/classes/salle/card.php?action=create',    'ecrire',    26),
			array('ecole_salles_d',  'ecole_salles',   'MenuDisponibilite',  '/classes/salle/disponibilite.php',         'lire',      27),
			array('ecole_config',    '',               'MenuConfiguration',  '/classes/niveau/list.php',                 'config',    30),
			array('ecole_niveaux',   'ecole_config',   'MenuNiveaux',        '/classes/niveau/list.php',                 'config',    31),
			array('ecole_sections',  'ecole_config',   'MenuSections',       '/classes/section/list.php',                'config',    32),
			array('ecole_creneaux',  'ecole_config',   'MenuCreneaux',       '/classes/creneau/list.php',                'config',    33),
			array('ecole_sessions',  'ecole_config',   'MenuSessions',       '/classes/session/list.php',                'config',    34),
			array('ecole_reglages',  'ecole_config',   'MenuReglages',       '/classes/admin/setup.php',                 'config',    35),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=classes';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'mainmenu' => 'classes',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'classes@classes',
				'position' => 1000 + $m[5],
				'enabled'  => 'isModEnabled("classes")',
				'perms'    => '$user->hasRight("classes", "'.$m[4].'")',
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation du module : crée les tables (sql/) et insère les données de départ.
	 * Rien n'est jamais supprimé ici : réactiver le module ne touche pas aux données.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/classes/sql/');
		if ($result < 0) {
			return -1;
		}

		$e = (int) $conf->entity;
		$p = MAIN_DB_PREFIX;
		$now = "'".$this->db->idate(dol_now())."'";

		// Données de départ (INSERT IGNORE : sans effet si elles existent déjà)
		$sql = array();
		// [ref, français, arabe, avec sections, position, barème par défaut des matières]
		$niveaux = array(
			array('MAT', 'Maternelle', 'الروضة', 0, 10, 'coef20'),
			array('PRI', 'Primaire', 'الابتدائي', 0, 20, 'coef20'),
			array('COL', 'Collège', 'الإعدادي', 0, 30, '20'),
			array('LYC', 'Lycée', 'الثانوي', 1, 40, '20'),
		);
		foreach ($niveaux as $n) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_niveau (entity, ref, label_fr, label_ar, avec_sections, bareme_defaut, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($n[0])."', '".$this->db->escape($n[1])."', '".$this->db->escape($n[2])."', ".$n[3].", '".$n[5]."', ".$n[4].", ".$now.", 1)";
		}
		for ($t = 1; $t <= 3; $t++) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_session (entity, ref, label_fr, label_ar, trimestre, date_creation, status)"
				." VALUES (".$e.", 'T".$t."', 'Compositions du trimestre ".$t."', 'امتحانات الفصل ".$t."', ".$t.", ".$now.", 1)";
		}

		// Catégorie de l'utilisateur (champ natif Dolibarr sur la fiche utilisateur, plusieurs choix possibles).
		// Les options restent modifiables dans Configuration > Utilisateurs > Attributs supplémentaires.
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$extrafields->fetch_name_optionals_label('user');
		if (empty($extrafields->attributes['user']['type']['ecole_categorie'])) {
			// Libellés affichés tels quels par Dolibarr (non traduits) : on les écrit donc en français et en arabe
			$catoptions = array(
				'direction' => 'Direction - الإدارة',
				'enseignant' => 'Enseignant - أستاذ',
				'surveillant' => 'Surveillant - مراقب',
				'secretariat' => 'Secrétariat - السكرتارية',
				'comptable' => 'Comptable - محاسب',
				'agent' => 'Agent d\'entretien / gardien - عامل / حارس',
				'chauffeur' => 'Chauffeur - سائق',
				'autre' => 'Autre - أخرى',
			);
			$extrafields->addExtraField('ecole_categorie', 'CategorieEcole', 'checkbox', 100, '', 'user', 0, 0, '', array('options' => $catoptions), 1, '', '1', 'CategorieEcoleHelp', '', '', 'classes@classes', 'isModEnabled("classes")');
		}

		// Données par défaut d'une nouvelle école (listes installées seulement si la table est encore vide)
		dol_include_once('/classes/core/lib/installation.lib.php');
		$db = $this->db;
		$vide = function ($table) use ($db, $e) {
			$r = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX.$table." WHERE entity = ".$e);
			$o = $r ? $db->fetch_object($r) : null;
			return $o && (int) $o->nb === 0;
		};
		$esc = array($this->db, 'escape');
		$sql = array_merge($sql, ecole_defaut_sql('classes', $p, $e, $now, $esc, $vide));
		ecole_defaut_poser_reglages($this->db);

		return $this->_init($sql, $options);
	}

	/**
	 * Désactivation du module (ne supprime aucune donnée).
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
