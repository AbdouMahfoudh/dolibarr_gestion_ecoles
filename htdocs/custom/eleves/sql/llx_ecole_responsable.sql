-- Responsable d'un ou plusieurs élèves (père, mère, tuteur...). Frères et sœurs = élèves ayant le même responsable.
-- Ses coordonnées sont recopiées automatiquement sur le Tiers Dolibarr de chacun de ses enfants.
CREATE TABLE llx_ecole_responsable
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,             -- référence automatique (R00001)
	nom_fr         VARCHAR(255) NOT NULL,
	nom_ar         VARCHAR(255),
	lien_parente   VARCHAR(16),                      -- pere, mere, tuteur, grand_parent, oncle_tante, frere_soeur, autre
	telephone      VARCHAR(32) NOT NULL,
	whatsapp       VARCHAR(32),
	email          VARCHAR(128),
	adresse        TEXT,
	note           TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_responsable_ref (entity, ref),
	KEY idx_ecole_responsable_tel (telephone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
