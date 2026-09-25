-- Historique des classes d'un élève : une ligne par période (date_fin vide = classe actuelle).
-- Un changement de classe ferme la ligne en cours et en ouvre une nouvelle.
CREATE TABLE llx_ecole_eleve_classe
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_eleve       INTEGER NOT NULL,
	fk_classe      INTEGER NOT NULL,
	date_debut     DATE NOT NULL,
	date_fin       DATE,
	motif          VARCHAR(255),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_eleve_classe_eleve (fk_eleve),
	KEY idx_ecole_eleve_classe_classe (fk_classe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
