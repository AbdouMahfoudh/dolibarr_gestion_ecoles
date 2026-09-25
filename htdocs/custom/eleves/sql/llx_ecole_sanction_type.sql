-- Types de sanctions (avertissement, blâme, retenue, exclusions), liste configurable.
-- exclusion : 0 = aucune, 1 = exclusion temporaire (date de fin demandée, élève « exclu » à l'appel),
--             2 = exclusion définitive (statut de l'élève passé à « exclu »).
CREATE TABLE llx_ecole_sanction_type
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	exclusion      SMALLINT NOT NULL DEFAULT 0,
	position       INTEGER NOT NULL DEFAULT 0,
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_sanction_type_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
