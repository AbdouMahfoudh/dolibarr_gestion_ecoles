-- Créneaux horaires fixes, communs à toute l'école (ex. 08:00 - 09:00)
CREATE TABLE llx_ecole_creneau
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	heure_debut    VARCHAR(5) NOT NULL,           -- HH:MM
	heure_fin      VARCHAR(5) NOT NULL,           -- HH:MM
	position       INTEGER NOT NULL DEFAULT 0,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_creneau_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
