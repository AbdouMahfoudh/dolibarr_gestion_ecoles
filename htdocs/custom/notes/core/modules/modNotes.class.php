<?php
/**
 * Descripteur du module « Notes et bulletins ».
 *
 * Étape A : règles de calcul par niveau (et exceptions par matière d'une classe), évaluations
 * (devoirs en nombre libre, une composition par matière et par trimestre), saisie des notes en grille
 * classe × matière, historique complet des notes, clôture des trimestres par la direction.
 * Étape B : calculs (moyennes, rangs, statistiques), mentions et distinctions configurables, conseil de classe
 * (observations, distinctions, décisions), tableau des résultats, bulletins PDF (plusieurs modèles), relevé annuel,
 * certificat de scolarité.
 * Étape C : élèves en difficulté (WhatsApp aux parents), onglets « Notes » des fiches élève et classe.
 *
 * Fichier : custom/notes/core/modules/modNotes.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modNotes
 */
class modNotes extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 104800;
		$this->rights_class = 'notes';
		$this->family = 'other';
		$this->module_position = '502';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Notes des élèves, bulletins trimestriels, relevés annuels";
		$this->descriptionlong = "Règles de calcul par niveau, devoirs et compositions, saisie des notes par classe et matière, historique des notes, clôture des trimestres.";
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-marker';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array('/notes/temp');
		$this->config_page_url = array("regles.php@notes");

		$this->hidden = false;
		// Classes (matières, coefficients, emplois du temps), Élèves (les élèves notés)
		$this->depends = array('modClasses', 'modEleves');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("notes@notes");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		// Élèves en difficulté : moyenne générale en dessous de ce seuil (message WhatsApp : valeur par défaut dans la lib)
		$this->const = array(
			array('NOTES_SEUIL_DIFFICULTE', 'chaine', '10', 'Seuil des élèves en difficulté (moyenne sur 20)', 0, 'current', 0),
		);
		$this->boxes = array();
		$this->cronjobs = array();

		// Droits : une rubrique et une action par droit (ex. $user->hasRight('notes', 'note', 'saisir'))
		$this->rights = array();
		$r = 0;
		$defs = array(
			array(104801, 'Notes : voir les notes de toutes les classes', 'note', 'lire'),
			array(104802, 'Notes : saisir les notes de ses classes et matières (enseignant, d\'après l\'emploi du temps)', 'note', 'saisir'),
			array(104803, 'Notes : saisir les notes de toutes les classes (secrétariat, direction)', 'note', 'saisirtout'),
			array(104804, 'Notes : modifier les notes d\'un trimestre clôturé (avec motif, gardé dans l\'historique)', 'note', 'corriger'),
			array(104811, 'Clôture : clôturer et rouvrir un trimestre', 'cloture', 'gerer'),
			array(104821, 'Résultats : voir les moyennes, les rangs et les élèves en difficulté ; imprimer bulletins, relevés et certificats', 'bulletin', 'lire'),
			array(104822, 'Conseil de classe : observations, distinctions et décisions de fin d\'année', 'bulletin', 'conseil'),
			array(104891, 'Configuration : règles de calcul, mentions, distinctions, modèles de bulletin', 'config', 'gerer'),
		);
		foreach ($defs as $d) {
			$this->rights[$r][0] = $d[0];
			$this->rights[$r][1] = $d[1];
			$this->rights[$r][3] = 0;
			$this->rights[$r][4] = $d[2];
			$this->rights[$r][5] = $d[3];
			$r++;
		}

		// Menus
		$this->menu = array();
		$r = 0;
		$voir = '($user->hasRight("notes", "note", "lire") || $user->hasRight("notes", "note", "saisir") || $user->hasRight("notes", "note", "saisirtout") || $user->hasRight("notes", "bulletin", "lire"))';
		$saisie = '($user->hasRight("notes", "note", "lire") || $user->hasRight("notes", "note", "saisir") || $user->hasRight("notes", "note", "saisirtout"))';
		$resultats = '$user->hasRight("notes", "bulletin", "lire")';
		$config = '$user->hasRight("notes", "config", "gerer")';

		$this->menu[$r++] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'MenuNotes',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'notes',
			'leftmenu' => '',
			'url'      => '/notes/index.php',
			'langs'    => 'notes@notes',
			'position' => 1100 + $r,
			'enabled'  => 'isModEnabled("notes")',
			'perms'    => $voir,
			'target'   => '',
			'user'     => 2,
		);

		// [leftmenu, parent leftmenu ('' = racine), titre, url, droit (expression), position]
		$left = array(
			array('notes_saisie',  '',             'MenuSaisieNotes',     '/notes/saisie.php',        $saisie, 10),
			array('notes_result',  '',             'MenuResultats',       '/notes/resultats.php',     $resultats, 15),
			array('notes_conseil', '',             'MenuConseil',         '/notes/conseil.php',       '$user->hasRight("notes", "bulletin", "conseil")', 16),
			array('notes_diff',    '',             'MenuDifficultes',     '/notes/difficultes.php',   $resultats, 17),
			array('notes_cloture', '',             'MenuCloture',         '/notes/cloture.php',       '($user->hasRight("notes", "note", "lire") || $user->hasRight("notes", "cloture", "gerer"))', 20),
			array('notes_histo',   '',             'MenuHistoriqueNotes', '/notes/historique.php',    $saisie, 30),
			array('notes_config',  '',             'MenuConfiguration',   '/notes/admin/regles.php',  $config, 90),
			array('notes_regles',  'notes_config', 'MenuReglesCalcul',    '/notes/admin/regles.php',  $config, 91),
			array('notes_mentions', 'notes_config', 'MenuMentions',       '/notes/mention/list.php',  $config, 92),
			array('notes_distinc', 'notes_config', 'MenuDistinctions',    '/notes/distinction/list.php', $config, 93),
			array('notes_modeles', 'notes_config', 'MenuModelesBulletin', '/notes/modele/list.php',   $config, 94),
			array('notes_apercu', 'notes_config', 'ApercuModeles',      '/notes/modele/apercu.php', $config, 94),
			array('notes_cfgsuivi', 'notes_config', 'MenuConfigSuivi',    '/notes/admin/suivi.php',   $config, 95),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=notes';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'mainmenu' => 'notes',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'notes@notes',
				'position' => 1100 + $m[5],
				'enabled'  => 'isModEnabled("notes")',
				'perms'    => $m[4],
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation du module : crée les tables (sql/). Rien n'est jamais supprimé ici.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/notes/sql/');
		if ($result < 0) {
			return -1;
		}

		$e = (int) $conf->entity;
		$p = MAIN_DB_PREFIX;
		$now = "'".$this->db->idate(dol_now())."'";
		$sql = array();

		// Mentions proposées (modifiables / désactivables dans Configuration > Mentions)
		$mentions = array(
			array('TB', 'Très bien', 'جيد جدا', 16),
			array('B', 'Bien', 'جيد', 14),
			array('AB', 'Assez bien', 'مستحسن', 12),
			array('P', 'Passable', 'مقبول', 10),
			array('INS', 'Insuffisant', 'ضعيف', 0),
		);
		foreach ($mentions as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_note_mention (entity, ref, label_fr, label_ar, seuil, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$now.", 1)";
		}
		// Distinctions proposées : [ref, français, arabe, minimum, maximum, position]
		$distinctions = array(
			array('FELIC', 'Félicitations', 'تهنئة', '16', 'NULL', 10),
			array('HONNEUR', 'Tableau d\'honneur', 'لوحة الشرف', '14', '16', 20),
			array('ENCOUR', 'Encouragements', 'تشجيع', '12', '14', 30),
			array('AVERT', 'Avertissement (travail)', 'إنذار (العمل)', 'NULL', '8', 40),
		);
		foreach ($distinctions as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_note_distinction (entity, ref, label_fr, label_ar, seuil_min, seuil_max, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$d[4].", ".$d[5].", ".$now.", 1)";
		}
		// Anciens styles (avant les mises en page et couleurs) : même aspect qu'avant
		$sql[] = "UPDATE ".$p."ecole_bulletin_modele SET style = 'classique', couleur = 'vert' WHERE style = 'moderne'";
		$sql[] = "UPDATE ".$p."ecole_bulletin_modele SET style = 'classique', couleur = 'aucune' WHERE style = 'sobre'";
		// Modèles de bulletin proposés : [ref, français, arabe, langue, mise en page, couleur, simplifié, détail, coef, position]
		$modeles = array(
			array('BILINGUE', 'Bulletin bilingue', 'كشف ثنائي اللغة', 'mixte', 'classique', 'bleu', 0, 1, 1, 10),
			array('ARABE', 'Bulletin en arabe', 'كشف بالعربية', 'ar', 'classique', 'bleu', 0, 1, 1, 20),
			array('FRANCAIS', 'Bulletin en français', 'كشف بالفرنسية', 'fr', 'classique', 'vert', 0, 1, 1, 30),
			array('SIMPLE', 'Bulletin simplifié (maternelle et primaire)', 'كشف مبسط (الروضة والابتدائي)', 'mixte', 'classique', 'vert', 1, 0, 0, 40),
		);
		foreach ($modeles as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_bulletin_modele (entity, ref, label_fr, label_ar, langue, style, couleur, simplifie, aff_detail, aff_coef, position, date_creation, status)"
				." VALUES (".$e.", '".$d[0]."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', '".$d[3]."', '".$d[4]."', '".$d[5]."', ".$d[6].", ".$d[7].", ".$d[8].", ".$d[9].", ".$now.", 1)";
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
		$sql = array_merge($sql, ecole_defaut_sql('notes', $p, $e, $now, $esc, $vide));

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
		return $this->_remove(array(), $options);
	}
}
