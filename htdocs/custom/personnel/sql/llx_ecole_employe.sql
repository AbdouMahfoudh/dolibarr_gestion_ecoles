-- Fiche d'un employé (enseignants, surveillants, secrétariat, comptable, agents, chauffeurs...).
-- ref = matricule automatique (P00001...), jamais modifié. Un utilisateur Dolibarr (fk_user) est créé
-- et mis à jour automatiquement depuis cette fiche (sens unique fiche → utilisateur).
-- Statuts : 1 = actif, 2 = en période d'essai, 3 = suspendu / en congé, 4 = parti.
-- Tous les champs d'information sont facultatifs (sauf le nom).
CREATE TABLE llx_ecole_employe
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	ref                VARCHAR(10) NOT NULL,
	nom_fr             VARCHAR(255) NOT NULL,
	nom_ar             VARCHAR(255),
	photo              VARCHAR(255),
	categories         VARCHAR(255),
	-- Identité
	date_naissance     DATE,
	lieu_naissance     VARCHAR(128),
	sexe               VARCHAR(1),
	fk_nationalite     INTEGER,
	nni                VARCHAR(32),
	situation_familiale VARCHAR(16),
	nb_enfants         INTEGER,
	adresse            TEXT,
	-- Contact et urgence
	telephone          VARCHAR(32),
	whatsapp           VARCHAR(32),
	email              VARCHAR(128),
	urgence_nom        VARCHAR(255),
	urgence_telephone  VARCHAR(32),
	urgence_lien       VARCHAR(64),
	-- Contrat
	poste              VARCHAR(128),
	date_embauche      DATE,
	type_contrat       VARCHAR(16),
	date_fin_contrat   DATE,
	date_fin_essai     DATE,
	-- Paie (droit « paie ») : salaire fixe ou paiement à l'heure
	mode_paie          VARCHAR(8),
	salaire_base       DOUBLE(24,8),
	taux_horaire       DOUBLE(24,8),
	heures_semaine     DOUBLE(24,8),
	taux_heure_sup     DOUBLE(24,8),
	banque_nom         VARCHAR(128),
	banque_compte      VARCHAR(64),
	mobile_money       VARCHAR(64),
	-- Diplômes et expérience
	diplome            VARCHAR(255),
	specialite         VARCHAR(255),
	experience         INTEGER,
	observations       TEXT,
	-- Espace du personnel (réglages de la direction)
	saisie_notes       SMALLINT DEFAULT 1,
	modif_profil       SMALLINT,
	envoi_whatsapp     SMALLINT DEFAULT 1,
	-- Règles de paie propres à l'employé (NULL = règle générale de la configuration)
	regle_retenue      VARCHAR(8),
	regle_retard       VARCHAR(8),
	regle_hsup         SMALLINT,
	regle_remplacement VARCHAR(8),
	-- Géré par le programme
	fk_user            INTEGER,
	date_statut        DATE,
	date_creation      DATETIME NOT NULL,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER,
	fk_user_modif      INTEGER,
	status             SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_employe_ref (entity, ref),
	KEY idx_ecole_employe_user (fk_user),
	KEY idx_ecole_employe_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
