-- Historique des statuts d'un élève (pré-inscrit, inscrit, suspendu, parti...) : qui, quand, pourquoi.
CREATE TABLE llx_ecole_eleve_statut
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_eleve       INTEGER NOT NULL,
	status_old     SMALLINT,
	status_new     SMALLINT NOT NULL,
	date_statut    DATE NOT NULL,
	motif          VARCHAR(255),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_eleve_statut_eleve (fk_eleve)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
