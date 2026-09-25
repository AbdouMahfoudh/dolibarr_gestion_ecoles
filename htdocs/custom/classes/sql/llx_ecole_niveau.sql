-- Niveaux (cycles) de l'établissement : maternelle, primaire, collège, lycée...
-- avec_sections = 1 : les classes de ce niveau doivent choisir une section (ex. lycée)
CREATE TABLE llx_ecole_niveau
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	avec_sections  TINYINT NOT NULL DEFAULT 0,
	bareme_defaut  VARCHAR(8) NOT NULL DEFAULT '20',  -- barème proposé pour les matières : '20' ou 'coef20'
	position       INTEGER NOT NULL DEFAULT 0,
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_niveau_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
