-- Matières qu'un enseignant peut enseigner (catalogue des matières du module Classes).
CREATE TABLE llx_ecole_employe_matiere
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_employe     INTEGER NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	UNIQUE KEY uk_ecole_employe_matiere (fk_employe, fk_matiere),
	KEY idx_ecole_employe_matiere_mat (fk_matiere)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
