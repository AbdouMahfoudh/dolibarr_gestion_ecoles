-- Classes (ex. 6ème A). Une classe appartient à un niveau (et à une section si le niveau en utilise).
-- La mensualité est le prix mensuel de la scolarité pour un élève de cette classe ; frais_inscription, le montant de l'inscription.
CREATE TABLE llx_ecole_classe
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	fk_niveau      INTEGER NOT NULL,
	fk_section     INTEGER,
	effectif_max   INTEGER NOT NULL DEFAULT 100,   -- nombre maximum d'élèves (100 par défaut)
	fk_salle       INTEGER,                        -- llx_resource.rowid (module Ressources)
	mensualite     DOUBLE(24,8) NOT NULL DEFAULT 0,
	frais_inscription DOUBLE(24,8) NOT NULL DEFAULT 0,  -- montant des frais d'inscription de la classe
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_classe_ref (entity, ref),
	KEY idx_ecole_classe_niveau (fk_niveau),
	KEY idx_ecole_classe_section (fk_section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
