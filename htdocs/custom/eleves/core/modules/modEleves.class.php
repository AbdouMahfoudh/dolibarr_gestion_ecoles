<?php
/**
 * Descripteur du module « Élèves ».
 *
 * Étape 1 : dossier de l'élève (5 blocs), responsables (fratries), matricule automatique,
 * compte comptable créé et mis à jour automatiquement, statuts et inscription, changement de
 * classe avec historique, pièces du dossier, champs supplémentaires.
 * Étape 2 : paiements (frais d'inscription, mensualités, autres frais), caisse, reçus, impayés,
 * attestation de solde. Les factures Dolibarr restent en coulisses (comptabilité).
 * Étape 3 : appel par créneau (absences, retards), justification, sanctions, élèves signalés, WhatsApp aux parents.
 *
 * Fichier : custom/eleves/core/modules/modEleves.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modEleves
 */
class modEleves extends DolibarrModules
{
	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 104700;
		$this->rights_class = 'eleves';
		$this->family = 'other';
		$this->module_position = '501';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Dossiers des élèves, responsables, inscriptions, pièces du dossier, paiements et impayés, absences et discipline";
		$this->descriptionlong = "Fiche élève (identité, scolarité, santé, documents, responsable), matricule automatique, compte comptable créé automatiquement, statuts d'inscription, changement de classe avec historique.";
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-user-graduate';
		$this->editor_name = 'Établissement scolaire';
		$this->editor_url = '';

		$this->dirs = array('/eleves/temp');
		$this->config_page_url = array("setup.php@eleves");

		$this->hidden = false;
		// Classes (classe de l'élève), Tiers (facturation), Factures (« Voir les factures »), Catégories (étiquette « Élève »)
		$this->depends = array('modClasses', 'modSociete', 'modFacture', 'modCategorie');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("eleves@eleves");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);

		// Format du matricule : préfixe + (année facultative) + compteur. Modifiable dans Configuration > Réglages.
		$this->const = array(
			array('ELEVES_MATRICULE_PREFIXE', 'chaine', 'E', 'Préfixe du matricule des élèves', 0, 'current', 0),
			array('ELEVES_MATRICULE_ANNEE', 'chaine', '', 'Année dans le matricule (vide, AA ou AAAA)', 0, 'current', 0),
			array('ELEVES_MATRICULE_LONGUEUR', 'chaine', '5', 'Nombre de chiffres du compteur du matricule', 0, 'current', 0),
			array('ELEVES_MATRICULE_DEBUT', 'chaine', '1', 'Premier numéro du compteur du matricule', 0, 'current', 0),
			// Paiements : mois payants (octobre à juin), jour limite, reçus (R000001, format A5)
			array('ELEVES_MOIS_PAYANTS', 'chaine', '10,11,12,1,2,3,4,5,6', 'Mois payants de l\'année scolaire', 0, 'current', 0),
			array('ELEVES_JOUR_LIMITE', 'chaine', '10', 'Jour limite de paiement de chaque mois', 0, 'current', 0),
			array('ELEVES_RECU_PREFIXE', 'chaine', 'R', 'Préfixe des numéros de reçu', 0, 'current', 0),
			array('ELEVES_RECU_LONGUEUR', 'chaine', '6', 'Nombre de chiffres des numéros de reçu', 0, 'current', 0),
			array('ELEVES_RECU_FORMAT', 'chaine', 'A5', 'Format des reçus (A5, A4, TICKET)', 0, 'current', 0),
		);

		// Absences et discipline : seuils d'alerte (0 = pas d'alerte), indicatif WhatsApp (messages : valeur par défaut dans discipline.lib.php)
		$this->const[] = array('ELEVES_SEUIL_ABSENCES', 'chaine', '10', 'Seuil d\'alerte : absences non justifiées (séances)', 0, 'current', 0);
		$this->const[] = array('ELEVES_SEUIL_RETARDS', 'chaine', '5', 'Seuil d\'alerte : retards non justifiés', 0, 'current', 0);
		$this->const[] = array('ELEVES_WHATSAPP_INDICATIF', 'chaine', '222', 'Indicatif du pays pour les numéros WhatsApp', 0, 'current', 0);

		// Année scolaire par défaut : celle de la rentrée en cours (modifiable dans Configuration > Paiements)
		$this->const[] = array('ELEVES_ANNEE_SCOLAIRE', 'chaine', (string) (((int) date('n') >= 8) ? (int) date('Y') : (int) date('Y') - 1), 'Année scolaire (année de la rentrée)', 0, 'current', 0);

		$this->boxes = array();
		$this->cronjobs = array();

		// Droits : une rubrique et une action par droit (ex. $user->hasRight('eleves', 'eleve', 'lire'))
		$this->rights = array();
		$r = 0;
		$defs = array(
			array(104701, 'Élèves : voir les dossiers et les listes', 'eleve', 'lire'),
			array(104702, 'Élèves : créer un élève (pré-inscription)', 'eleve', 'creer'),
			array(104703, 'Élèves : modifier le dossier', 'eleve', 'modifier'),
			array(104704, 'Élèves : supprimer un élève', 'eleve', 'supprimer'),
			array(104705, 'Élèves : exporter les listes (PDF / Excel)', 'eleve', 'exporter'),
			array(104706, 'Élèves : imprimer l\'attestation d\'inscription', 'eleve', 'attestation'),
			array(104711, 'Responsables : voir', 'responsable', 'lire'),
			array(104712, 'Responsables : créer', 'responsable', 'creer'),
			array(104713, 'Responsables : modifier', 'responsable', 'modifier'),
			array(104714, 'Responsables : supprimer', 'responsable', 'supprimer'),
			array(104721, 'Santé : voir les informations de santé et le contact d\'urgence', 'sante', 'lire'),
			array(104722, 'Santé : modifier les informations de santé et le contact d\'urgence', 'sante', 'modifier'),
			array(104731, 'Inscription : valider (pré-inscrit vers inscrit)', 'inscription', 'valider'),
			array(104732, 'Inscription : changer le statut (liste d\'attente, suspension, départ, abandon, exclusion, réintégration)', 'inscription', 'statut'),
			array(104741, 'Classe : changer un élève de classe', 'classe', 'changer'),
			array(104751, 'Documents : voir les pièces du dossier et les fichiers scannés', 'document', 'lire'),
			array(104752, 'Documents : cocher les pièces fournies et joindre les fichiers', 'document', 'modifier'),
			array(104753, 'Documents : supprimer un fichier scanné', 'document', 'supprimer'),
			array(104761, 'Configuration : matricule, paiements, autres frais, pièces à fournir, champs supplémentaires', 'config', 'gerer'),
			array(104771, 'Paiements : voir la situation financière, les paiements et les reçus', 'paiement', 'lire'),
			array(104772, 'Paiements : encaisser (fiche élève et caisse)', 'paiement', 'encaisser'),
			array(104773, 'Paiements : annuler un reçu (avec motif)', 'paiement', 'annuler'),
			array(104774, 'Paiements : régler les réductions, le premier mois dû et les frais d\'inscription d\'un élève', 'paiement', 'reduction'),
			array(104775, 'Paiements : voir la liste des impayés', 'paiement', 'impayes'),
			array(104776, 'Paiements : imprimer les reçus et les attestations de solde', 'paiement', 'recu'),
			array(104777, 'Paiements : exonérer un élève des frais d\'inscription (totalement ou en partie)', 'paiement', 'exonerer'),
			array(104781, 'Absences : voir les absences, les retards et les élèves signalés', 'absence', 'lire'),
			array(104782, 'Absences : faire l\'appel', 'absence', 'appel'),
			array(104783, 'Absences : corriger un appel déjà validé (avec historique)', 'absence', 'modifier'),
			array(104784, 'Absences : justifier une absence ou un retard', 'absence', 'justifier'),
			array(104791, 'Sanctions : voir', 'sanction', 'lire'),
			array(104792, 'Sanctions : enregistrer une sanction', 'sanction', 'creer'),
			array(104793, 'Sanctions : annuler une sanction (avec motif)', 'sanction', 'annuler'),
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
			'titre'    => 'Eleves',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'eleves',
			'leftmenu' => '',
			'url'      => '/eleves/index.php',
			'langs'    => 'eleves@eleves',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("eleves")',
			'perms'    => '$user->hasRight("eleves", "eleve", "lire")',
			'target'   => '',
			'user'     => 2,
		);

		// [leftmenu, parent leftmenu ('' = racine), titre, url, droit « rubrique.action », position]
		$left = array(
			array('eleves_list',     '',               'MenuEleves',            '/eleves/eleve/list.php',                        'eleve.lire',        10),
			array('eleves_new',      'eleves_list',    'MenuNouvelEleve',       '/eleves/eleve/card.php?action=create',          'eleve.creer',       11),
			array('eleves_preinsc',  'eleves_list',    'MenuPreinscriptions',   '/eleves/eleve/list.php?search_status=0',        'eleve.lire',        12),
			array('eleves_attente',  'eleves_list',    'MenuListeAttente',      '/eleves/eleve/list.php?search_status=2',        'eleve.lire',        13),
			array('eleves_manq',     'eleves_list',    'MenuPiecesManquantes',  '/eleves/eleve/list.php?manquantes=1',           'document.lire',     14),
			array('eleves_caisse',   '',               'MenuCaisse',            '/eleves/caisse.php',                            'paiement.encaisser', 15),
			array('eleves_impayes',  '',               'MenuImpayes',           '/eleves/impayes.php',                           'paiement.impayes',  16),
			array('eleves_recus',    '',               'MenuRecus',             '/eleves/recu/list.php',                         'paiement.lire',     17),
			array('eleves_appel',    '',               'MenuAppel',             '/eleves/appel.php',                             'absence.appel',     18),
			array('eleves_abs',      '',               'MenuAbsences',          '/eleves/absence/list.php',                      'absence.lire',      19),
			array('eleves_signal',   'eleves_abs',     'MenuElevesSignales',    '/eleves/signales.php',                          'absence.lire',      1),
			array('eleves_tabjour',  'eleves_abs',     'MenuTableauDuJour',     '/eleves/appel.php',                             'absence.lire',      2),
			array('eleves_sanct',    '',               'MenuSanctions',         '/eleves/sanction/list.php',                     'sanction.lire',     19),
			array('eleves_resp',     '',               'MenuResponsables',      '/eleves/responsable/list.php',                  'responsable.lire',  20),
			array('eleves_resp_n',   'eleves_resp',    'MenuNouveauResponsable', '/eleves/responsable/card.php?action=create',   'responsable.creer', 21),
			array('eleves_config',   '',               'MenuConfiguration',     '/eleves/admin/setup.php',                       'config.gerer',      30),
			array('eleves_reglages', 'eleves_config',  'MenuReglages',          '/eleves/admin/setup.php',                       'config.gerer',      31),
			array('eleves_cfgpaie',  'eleves_config',  'MenuConfigPaiements',   '/eleves/admin/paiements.php',                   'config.gerer',      32),
			array('eleves_frais',    'eleves_config',  'MenuAutresFrais',       '/eleves/frais_type/list.php',                   'config.gerer',      33),
			array('eleves_exo',      'eleves_config',  'MenuMotifsExoneration', '/eleves/motif_exoneration/list.php',            'config.gerer',      33),
			array('eleves_pieces',   'eleves_config',  'MenuPiecesAFournir',    '/eleves/document_type/list.php',                'config.gerer',      34),
			array('eleves_extra',    'eleves_config',  'MenuChampsSupp',        '/eleves/admin/eleve_extrafields.php',           'config.gerer',      35),
			array('eleves_cfgdisc',  'eleves_config',  'MenuConfigDiscipline',  '/eleves/admin/discipline.php',                  'config.gerer',      36),
			array('eleves_motifs',   'eleves_config',  'MenuMotifsAbsence',     '/eleves/motif_absence/list.php',                'config.gerer',      37),
			array('eleves_tsanct',   'eleves_config',  'MenuTypesSanction',     '/eleves/sanction_type/list.php',                'config.gerer',      38),
		);
		foreach ($left as $m) {
			$fk = 'fk_mainmenu=eleves';
			if ($m[1] !== '') {
				$fk .= ',fk_leftmenu='.$m[1];
			}
			$p = explode('.', $m[4]);
			$this->menu[$r++] = array(
				'fk_menu'  => $fk,
				'type'     => 'left',
				'titre'    => $m[2],
				'mainmenu' => 'eleves',
				'leftmenu' => $m[0],
				'url'      => $m[3],
				'langs'    => 'eleves@eleves',
				'position' => 1000 + $m[5],
				'enabled'  => 'isModEnabled("eleves")',
				'perms'    => '$user->hasRight("eleves", "'.$p[0].'", "'.$p[1].'")',
				'target'   => '',
				'user'     => 2,
			);
		}
	}

	/**
	 * Activation du module : crée les tables (sql/) et insère les pièces à fournir proposées.
	 * Rien n'est jamais supprimé ici : réactiver le module ne touche pas aux données.
	 *
	 * @param  string $options Options
	 * @return int             1 si OK, <=0 sinon
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/eleves/sql/');
		if ($result < 0) {
			return -1;
		}

		$e = (int) $conf->entity;
		$p = MAIN_DB_PREFIX;
		$now = "'".$this->db->idate(dol_now())."'";

		// Mise à jour : numéro d'appel des élèves qui n'en ont pas encore (à la suite dans leur classe, ordre alphabétique)
		$resql = $this->db->query("SELECT rowid, fk_classe FROM ".$p."ecole_eleve WHERE numero_appel IS NULL AND status IN (0,1,2,3) ORDER BY fk_classe, (status IN (1,3)) DESC, nom_fr, rowid");
		$todo = array();
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$todo[] = $o;
		}
		foreach ($todo as $o) {
			$r = $this->db->query("SELECT MAX(numero_appel) as m FROM ".$p."ecole_eleve WHERE fk_classe = ".((int) $o->fk_classe)." AND status IN (0,1,2,3)");
			$m = $r ? $this->db->fetch_object($r) : null;
			$this->db->query("UPDATE ".$p."ecole_eleve SET numero_appel = ".(($m ? (int) $m->m : 0) + 1)." WHERE rowid = ".((int) $o->rowid));
		}
		// Mise à jour : matière des appels déjà faits, d'après l'emploi du temps (jour de la semaine + créneau)
		$this->db->query("UPDATE ".$p."ecole_appel a INNER JOIN ".$p."ecole_edt_cours c ON c.fk_classe = a.fk_classe AND c.fk_creneau = a.fk_creneau AND c.jour = WEEKDAY(a.date_appel) + 1 SET a.fk_matiere = c.fk_matiere WHERE a.fk_matiere IS NULL");

		// Pièces à fournir proposées (modifiables / désactivables dans Configuration > Pièces à fournir)
		$sql = array();
		$pieces = array(
			array('ACTE', 'Extrait d\'acte de naissance', 'مستخرج من شهادة الميلاد', 10),
			array('PHOTO', 'Photos d\'identité', 'صور شمسية', 20),
			array('BULLETIN', 'Dernier bulletin de notes', 'آخر كشف للدرجات', 30),
			array('RADIATION', 'Certificat de radiation (école d\'origine)', 'شهادة المغادرة (المدرسة الأصلية)', 40),
			array('VACCIN', 'Carnet de vaccination', 'دفتر التلقيح', 50),
			array('CNI_RESP', 'Pièce d\'identité du responsable', 'بطاقة تعريف ولي الأمر', 60),
		);
		foreach ($pieces as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_document_type (entity, ref, label_fr, label_ar, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$now.", 1)";
		}

		// Motifs de justification et types de sanctions proposés (modifiables / désactivables dans la configuration)
		$motifs = array(
			array('MALADIE', 'Maladie', 'مرض', 10),
			array('FAMILLE', 'Raison familiale', 'ظرف عائلي', 20),
			array('MEDICAL', 'Rendez-vous médical', 'موعد طبي', 30),
			array('VOYAGE', 'Voyage', 'سفر', 40),
			array('AUTRE', 'Autre', 'أخرى', 90),
		);
		foreach ($motifs as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_motif_absence (entity, ref, label_fr, label_ar, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$now.", 1)";
		}
		// Motifs d'exonération des frais d'inscription proposés
		$motifsExo = array(
			array('REINSCR', 'Réinscription (élève de l\'an dernier)', 'إعادة التسجيل (تلميذ السنة الماضية)', 10),
			array('BOURSE', 'Bourse', 'منحة', 20),
			array('PERSONNEL', 'Enfant du personnel de l\'école', 'ابن أحد موظفي المدرسة', 30),
			array('FRATRIE', 'Frère ou sœur inscrit(e)', 'أخ أو أخت مسجل(ة)', 40),
			array('AUTRE', 'Autre', 'أخرى', 90),
		);
		foreach ($motifsExo as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_motif_exoneration (entity, ref, label_fr, label_ar, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$now.", 1)";
		}
		$types = array(
			array('AVERT', 'Avertissement', 'إنذار', 0, 10),
			array('BLAME', 'Blâme', 'توبيخ', 0, 20),
			array('RETENUE', 'Retenue', 'حجز', 0, 30),
			array('EXCL_TEMP', 'Exclusion temporaire', 'فصل مؤقت', 1, 40),
			array('EXCL_DEF', 'Exclusion définitive', 'فصل نهائي', 2, 50),
		);
		foreach ($types as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."ecole_sanction_type (entity, ref, label_fr, label_ar, exclusion, position, date_creation, status)"
				." VALUES (".$e.", '".$this->db->escape($d[0])."', '".$this->db->escape($d[1])."', '".$this->db->escape($d[2])."', ".$d[3].", ".$d[4].", ".$now.", 1)";
		}

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
