-- Matières (catalogue de l'école ; le coefficient est défini par classe)
CREATE TABLE llx_ecole_matiere
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	code           VARCHAR(32),
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	langue         VARCHAR(2) NOT NULL DEFAULT 'fr',  -- langue d'enseignement : 'fr' ou 'ar' (bulletin bilingue)
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_matiere_ref (entity, ref),
	UNIQUE KEY uk_ecole_matiere_code (entity, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
