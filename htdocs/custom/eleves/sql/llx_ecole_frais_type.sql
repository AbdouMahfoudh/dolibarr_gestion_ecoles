-- Autres frais encaissés depuis la fiche élève (uniforme, fournitures, transport, cantine...), liste configurable.
-- montant = prix proposé par défaut (modifiable à l'encaissement). Pas de suivi d'impayés pour ces frais.
CREATE TABLE llx_ecole_frais_type
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	montant        DOUBLE(24,8) NOT NULL DEFAULT 0,
	position       INTEGER NOT NULL DEFAULT 0,
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_frais_type_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
