-- Salles de l'établissement (salles de classe, laboratoires, salle informatique...).
-- La salle « attitrée » d'une classe est llx_ecole_classe.fk_salle ; l'occupation vient des emplois du temps.
CREATE TABLE llx_ecole_salle
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	type_salle     VARCHAR(16) NOT NULL DEFAULT 'classe',
	capacite       INTEGER,
	emplacement    VARCHAR(128),
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_salle_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
