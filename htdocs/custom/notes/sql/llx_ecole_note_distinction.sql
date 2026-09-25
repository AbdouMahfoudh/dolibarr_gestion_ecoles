-- Distinctions (félicitations, tableau d'honneur, encouragements, avertissement...) proposées
-- automatiquement selon la moyenne générale (entre seuil_min et seuil_max, vides = sans limite),
-- dans l'ordre de position ; la direction peut les changer au conseil de classe.
CREATE TABLE llx_ecole_note_distinction
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	seuil_min      DOUBLE(8,2),
	seuil_max      DOUBLE(8,2),
	position       INTEGER NOT NULL DEFAULT 0,
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_note_distinction_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
