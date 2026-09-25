-- Corrections de la présence d'un enseignant à un cours : qui, quand, avant, après (codes P, D, A, R|minutes, >remplaçant).
CREATE TABLE llx_ecole_cours_prof_log
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_cours       INTEGER NOT NULL,
	avant          VARCHAR(64),
	apres          VARCHAR(64),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_cours_prof_log (fk_cours)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
