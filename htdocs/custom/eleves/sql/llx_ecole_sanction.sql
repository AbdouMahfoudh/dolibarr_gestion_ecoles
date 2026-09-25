-- Sanctions : date, type, motif (date de fin pour une exclusion temporaire).
-- Jamais effacée : une erreur se corrige par une annulation avec motif (status 0), la ligne reste barrée.
CREATE TABLE llx_ecole_sanction
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_eleve       INTEGER NOT NULL,
	fk_classe      INTEGER,
	fk_type        INTEGER NOT NULL,
	date_sanction  DATE NOT NULL,
	date_fin       DATE,
	motif          TEXT,
	status         SMALLINT NOT NULL DEFAULT 1,
	motif_annulation VARCHAR(255),
	date_annulation DATETIME,
	fk_user_annul  INTEGER,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	KEY idx_ecole_sanction_eleve (fk_eleve, date_sanction),
	KEY idx_ecole_sanction_type (fk_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
