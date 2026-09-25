-- Corrections d'un appel déjà validé : pour chaque élève modifié, qui, quand, avant et après.
CREATE TABLE llx_ecole_absence_log
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_appel       INTEGER NOT NULL,
	fk_eleve       INTEGER NOT NULL,
	avant          VARCHAR(32),
	apres          VARCHAR(32),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_absence_log_appel (fk_appel),
	KEY idx_ecole_absence_log_eleve (fk_eleve)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
