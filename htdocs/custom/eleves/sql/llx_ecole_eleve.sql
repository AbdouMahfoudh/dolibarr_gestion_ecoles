-- Dossier de l'élève. ref = matricule automatique (jamais modifié). rip = identifiant du ministère (facultatif, unique).
-- status : 0 pré-inscrit, 1 inscrit, 2 liste d'attente, 3 suspendu, 4 parti/transféré, 5 abandon, 6 exclu.
-- fk_classe = classe actuelle (l'historique est dans llx_ecole_eleve_classe) ; fk_soc = Tiers Dolibarr créé automatiquement.
CREATE TABLE llx_ecole_eleve
(
	rowid                    INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity                   INTEGER NOT NULL DEFAULT 1,
	ref                      VARCHAR(32) NOT NULL,
	rip                      VARCHAR(32),
	nom_fr                   VARCHAR(255) NOT NULL,
	nom_ar                   VARCHAR(255),
	date_naissance           DATE,
	lieu_naissance           VARCHAR(128),
	sexe                     VARCHAR(1),
	fk_nationalite           INTEGER,               -- llx_c_country.rowid
	photo                    VARCHAR(255),
	date_inscription         DATE NOT NULL,
	ecole_origine            VARCHAR(255),
	fk_classe                INTEGER NOT NULL,
	numero_appel             INTEGER,                   -- numéro de l élève dans sa classe (1, 2, 3...)
	observations             TEXT,
	groupe_sanguin           VARCHAR(4),
	allergies                TEXT,
	maladies                 TEXT,
	urgence_nom              VARCHAR(255),
	urgence_telephone        VARCHAR(32),
	urgence_lien             VARCHAR(64),
	fk_responsable           INTEGER NOT NULL,
	fk_soc                   INTEGER,
	mois_debut               VARCHAR(7),           -- premier mois dû (AAAA-MM), vide = mois d'inscription
	reduction_type           VARCHAR(8),           -- montant | pourcent (fratrie, bourse, gratuité : 100 %)
	reduction_valeur         DOUBLE(24,8),
	reduction_motif          VARCHAR(255),
	frais_inscription_du     DOUBLE(24,8),         -- vide = frais d'inscription de la classe
	exo_type                 VARCHAR(8),           -- exonération des frais inscription : montant | pourcent (vide = aucune)
	exo_valeur               DOUBLE(24,8),
	fk_motif_exo             INTEGER,              -- llx_ecole_motif_exoneration
	exo_note                 VARCHAR(255),
	exo_fk_user              INTEGER,              -- qui a accordé l exonération
	exo_date                 DATETIME,             -- quand
	frais_inscription_payes  SMALLINT NOT NULL DEFAULT 0,  -- ancienne case de l'étape 1 (remplacée par les paiements)
	date_frais_inscription   DATE,
	fk_user_frais            INTEGER,
	date_statut              DATE,
	date_validation          DATETIME,
	fk_user_valid            INTEGER,
	date_creation            DATETIME NOT NULL,
	tms                      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat            INTEGER,
	fk_user_modif            INTEGER,
	import_key               VARCHAR(14),
	status                   SMALLINT NOT NULL DEFAULT 0,
	UNIQUE KEY uk_ecole_eleve_ref (entity, ref),
	UNIQUE KEY uk_ecole_eleve_rip (entity, rip),
	KEY idx_ecole_eleve_classe (fk_classe),
	KEY idx_ecole_eleve_responsable (fk_responsable),
	KEY idx_ecole_eleve_soc (fk_soc),
	KEY idx_ecole_eleve_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
