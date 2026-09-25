<?php
/**
 * Descripteur du module « Personnel ».
 *
 * Étape 1 : fiche employé (tous les employés : enseignants, surveillants, secrétariat, comptable, agents,
 * chauffeurs...), matricule automatique, compte utilisateur créé et mis à jour automatiquement,
 * catégories, matières enseignables, contrat et paie (fixe ou à l'heure), pièces du dossier, statuts avec motifs.
 * Étape 2 : présence — cours des enseignants faits par défaut (déclaration, absent / en retard pendant l'appel),
 * remplacements (direction), absences du personnel, jours sans cours, heures du mois et estimation.
 * Étape 3 : espace du personnel (dans l'espace du module Espace, connexion avec le matricule) : cours et déclaration,
 * emploi du temps, saisie des notes, classes, examens (enseignant) ; appel (surveillant) ; heures, paie, absences.
 * Le calcul et le paiement des salaires se feront dans un module séparé.
 *
 * Fichier : custom/personnel/core/modules/modPersonnel.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modPersonnel
 */
class modPersonnel extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 105000;
		$this->rights_class = 'personnel';
		$this->family = 'other';
		$this->module_position = '502';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Fiches des employés (enseignants et autres), présence des enseignants, remplacements, absences du personnel, heures du mois";
		$this->descriptionlong = "Fiche employé bilingue, matricule automatique, compte utilisateur créé automatiquement, statuts, pièces du dossier, présence des enseignants et absences du personnel.";
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-id-badge';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array('/personnel/temp');
		$this->config_page_url = array("setup.php@personnel");

		$this->hidden = false;
		// Classes (emploi du temps, matières), Utilisateurs (compte de chaque employé)
		$this->depends = array('modClasses', 'modUser');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("personnel@personnel");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		// Réglages (modifiables dans Configuration)
		$this->const = array(
			array('PERSONNEL_MATRICULE_PREFIXE', 'chaine', 'P', 'Préfixe du matricule des employés', 0, 'current', 0),
			array('PERSONNEL_MATRICULE_LONGUEUR', 'chaine', '5', 'Nombre de chiffres du compteur du matricule', 0, 'current', 0),
			array('PERSONNEL_MATRICULE_DEBUT', 'chaine', '1', 'Premier numéro du compteur du matricule', 0, 'current', 0),
			// Règles de présence et de paie (utilisées pour l'estimation du mois et, plus tard, par le module Salaires)
			array('PERSONNEL_RETARD_EFFET', 'chaine', 'aucun', 'Effet d\'un retard : aucun | minutes | seuil', 0, 'current', 0),
			array('PERSONNEL_RETARD_SEUIL', 'chaine', '15', 'Retard au-delà duquel le cours compte comme absent (minutes)', 0, 'current', 0),
			array('PERSONNEL_REMPLACEMENT_PAIE', 'chaine', 'taux', 'Paiement des remplacements : taux | forfait | aucun', 0, 'current', 0),
			array('PERSONNEL_REMPLACEMENT_MONTANT', 'chaine', '0', 'Montant forfaitaire par séance de remplacement', 0, 'current', 0),
			array('PERSONNEL_RETENUE_ABSENCE', 'chaine', 'aucune', 'Retenue pour absence non justifiée des salariés au fixe : aucune | prorata', 0, 'current', 0),
			array('PERSONNEL_HEURE_MIDI', 'chaine', '12:00', 'Heure qui sépare le matin de l\'après-midi (demi-journées)', 0, 'current', 0),
			array('PERSONNEL_HEURES_SUP', 'chaine', 'oui', 'Heures supplémentaires des salariés au fixe : oui (payées) | non', 0, 'current', 0),
			array('PERSONNEL_SUSPENSION_PAYEE', 'chaine', 'oui', 'Suspension / congé des salariés au fixe : oui (salaire maintenu) | non (réduit au prorata)', 0, 'current', 0),
		);

		$this->boxes = array();
		$this->cronjobs = array();

		// Droits : une rubrique et une action par droit (ex. $user->hasRight('personnel', 'employe', 'lire'))
		$this->rights = array();
		$r = 0;
		$defs = array(
			array(105001, 'Employés : voir les fiches et les listes', 'employe', 'lire'),
			array(105002, 'Employés : créer un employé', 'employe', 'creer'),
			array(105003, 'Employés : modifier la fiche', 'employe', 'modifier'),
			array(105004, 'Employés : supprimer un employé', 'employe', 'supprimer'),
			array(105005, 'Employés : exporter les listes (PDF / Excel)', 'employe', 'exporter'),
			array(105011, 'Paie : voir le salaire, le prix de l\'heure, la banque et l\'estimation du mois', 'paie', 'lire'),
			array(105012, 'Paie : modifier le mode de paie, le salaire et le prix de l\'heure', 'paie', 'modifier'),
			array(105021, 'Statut : changer le statut (période d\'essai, suspension / congé, départ, reprise)', 'statut', 'changer'),
			array(105031, 'Documents : voir les pièces du dossier et les fichiers scannés', 'document', 'lire'),
			array(105032, 'Documents : cocher les pièces fournies et joindre les fichiers', 'document', 'modifier'),
			array(105033, 'Documents : supprimer un fichier scanné', 'document', 'supprimer'),
			array(105041, 'Présence : voir les cours faits, les absences et les heures des enseignants', 'presence', 'lire'),
			array(105042, 'Présence : marquer un enseignant absent ou en retard, déclarer ou corriger un cours', 'presence', 'gerer'),
			array(105043, 'Présence : enregistrer les remplacements', 'presence', 'remplacer'),
			array(105044, 'Présence : déclarer ses propres cours', 'presence', 'declarer'),
			array(105051, 'Absences du personnel : voir', 'absence', 'lire'),
			array(105052, 'Absences du personnel : enregistrer, justifier, annuler', 'absence', 'gerer'),
			array(105091, 'Configuration : matricule, règles de présence et de paie, motifs, pièces, jours sans cours, champs supplémentaires', 'config', 'gerer'),
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
		$this->menu[$r++] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'Personnel',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'personnel',
			'leftmenu' => '',
			'url'      => '/personnel/index.php',
			'langs'    => 'personnel@personnel',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("personnel")',
			'perms'    => '$user->hasRight("personnel", "employe", "lire")',
			'target'   => '',
			'user'     => 2,
		);

		// [leftmenu, parent leftmenu ('' = racine), titre, url, droit « rubrique.action », position]
		$left = array(
			array('pers_list',     '',             'MenuEmployes',          '/personnel/employe/list.php',                         'employe.lire',     10),
			array('pers_new',      'pers_list',    'MenuNouvelEmploye',     '/personnel/employe/card.php?action=create',           'employe.creer',    11),
			array('pers_ens',      'pers_list',    'MenuEnseignants',       '/personnel/employe/list.php?search_extra_categories=enseignant', 'employe.lire', 12),
			array('pers_manq',     'pers_list',    'MenuPiecesManquantes',  '/personnel/employe/list.php?search_extra_pieces=manquantes', 'document.lire',   13),
			array('pers_partis',   'pers_list',    'MenuAnciensEmployes',   '/personnel/employe/list.php?search_status=4',          'employe.lire',     14),
			array('pers_pres',     '',             'MenuPresenceEnseignants', '/personnel/presence.php',                           'presence.lire',    20),
			array('pers_cours',    'pers_pres',    'MenuCoursSignales',     '/personnel/cours/list.php',                           'presence.lire',    21),
			array('pers_remp',     'pers_pres',    'MenuRemplacements',     '/personnel/cours/list.php?f_type=remplacement',       'presence.lire',    22),
			array('pers_abs',      '',             'MenuAbsencesPersonnel', '/personnel/absence/list.php',                         'absence.lire',     30),
			array('pers_heures',   '',             'MenuHeuresMois',        '/personnel/heures.php',                               'presence.lire',    40),
			array('pers_monesp',   '',             'MenuMonEspace',         '/personnel/monespace.php',                            '',                 50),
			array('pers_config',   '',             'MenuConfiguration',     '/personnel/admin/setup.php',                          'config.gerer',     90),
			array('pers_reglages', 'pers_config',  'MenuReglages',          '/personnel/admin/setup.php',                          'config.gerer',     91),
			array('pers_regles',   'pers_config',  'MenuReglesPresence',    '/personnel/admin/regles.php',                         'config.gerer',     92),
			array('pers_motifs',   'pers_config',  'MenuMotifs',            '/personnel/motif/list.php',                           'config.gerer',     93),
			array('pers_pieces',   'pers_config',  'MenuPiecesAFournir',    '/personnel/doc_type/list.php',                        'config.gerer',     94),
			array('pers_jsc',      'pers_config',  'MenuJoursSansCours',    '/personnel/jour_sans_cours/list.php',                 'config.gerer',     95),
			array('pers_extra',    'pers_config',  'MenuChampsSupp',        '/personnel/admin/employe_extrafields.php',            'config.gerer',     96),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=personnel';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$p = explode('.', $m[4]);
			// « Mon espace » : tout utilisateur connecté (la page vérifie qu'il a une fiche employé)
			$perms = ($m[4] === '') ? '$user->id > 0' : '$user->hasRight("personnel", "'.$p[0].'", "'.$p[1].'")';
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'mainmenu' => 'personnel',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'personnel@personnel',
				'position' => 1000 + $m[5],
				'enabled'  => ($m[4] === '') ? 'isModEnabled("personnel") && isModEnabled("espace")' : 'isModEnabled("personnel")',
				'perms'    => $perms,
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation du module : crée les tables (sql/), insère les motifs et les pièces proposés, puis crée une fiche
	 * employé pour chaque utilisateur déjà classé dans une catégorie de l'école ou déjà enseignant dans l'emploi du temps.
	 * Rien n'est jamais supprimé ici : réactiver le module ne touche pas aux données.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		global $conf, $user;

		$result = $this->_load_tables('/personnel/sql/');
		if ($result < 0) {
			return -1;
		}

		$e = (int) $conf->entity;
		$p = MAIN_DB_PREFIX;
		$now = "'".$this->db->idate(dol_now())."'";
		$sql = array();

		// Motifs proposés (modifiables / désactivables dans Configuration > Motifs)
		$motifs = array(
			array('ABS_MAL', 'Maladie', 'مرض', 'absence', 10),
			array('ABS_FAM', 'Raison familiale', 'ظرف عائلي', 'absence', 20),
			array('ABS_AUT', 'Absence autorisée par la direction', 'غياب مرخص من الإدارة', 'absence', 30),
			array('ABS_AUTRE', 'Autre', 'أخرى', 'absence', 90),
			array('SUS_MAL', 'Congé maladie', 'عطلة مرضية', 'suspension', 10),
			array('SUS_MAT', 'Congé de maternité', 'عطلة أمومة', 'suspension', 20),
			array('SUS_SSOLDE', 'Congé sans solde', 'عطلة بدون راتب', 'suspension', 30),
			array('SUS_DISC', 'Suspension disciplinaire', 'توقيف تأديبي', 'suspension', 40),
			array('SUS_FORM', 'Formation', 'تكوين', 'suspension', 50),
			array('SUS_AUTRE', 'Autre', 'أخرى', 'suspension', 90),
			array('DEP_DEM', 'Démission', 'استقالة', 'depart', 10),
			array('DEP_FIN', 'Fin de contrat', 'نهاية العقد', 'depart', 20),
			array('DEP_LIC', 'Licenciement', 'فصل', 'depart', 30),
			array('DEP_RET', 'Retraite', 'تقاعد', 'depart', 40),
			array('DEP_DECES', 'Décès', 'وفاة', 'depart', 50),
			array('DEP_AUTRE', 'Autre', 'أخرى', 'depart', 90),
		);
		foreach ($motifs as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_employe_motif (entity, ref, label_fr, label_ar, usage_motif, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', '".$d[3]."', ".$d[4].", ".$now.", 1)";
		}

		// Pièces à fournir proposées (modifiables / désactivables dans Configuration > Pièces à fournir)
		$pieces = array(
			array('CNI', 'Copie de la carte d\'identité', 'نسخة من بطاقة التعريف', '', 10),
			array('PHOTO', 'Photos d\'identité', 'صور شمسية', '', 20),
			array('CV', 'Curriculum vitae', 'السيرة الذاتية', '', 30),
			array('DIPLOME', 'Copie des diplômes', 'نسخة من الشهادات', '', 40),
			array('CONTRAT', 'Contrat signé', 'العقد الموقع', '', 50),
			array('CASIER', 'Extrait de casier judiciaire', 'مستخرج من السجل العدلي', '', 60),
		);
		foreach ($pieces as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_employe_doc_type (entity, ref, label_fr, label_ar, categories, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".($d[3] !== '' ? "'".$d[3]."'" : "NULL").", ".$d[4].", ".$now.", 1)";
		}

		$res = $this->_init($sql, $options);
		if ($res <= 0) {
			return $res;
		}

		// Utilisateurs déjà présents : fiche employé créée et reliée (catégorie de l'école ou cours dans l'emploi du temps)
		dol_include_once('/personnel/class/ecole_employe.class.php');
		if (class_exists('EcoleEmploye') && is_object($user)) {
			EcoleEmploye::importerUtilisateurs($this->db, $user);
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
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
