-- Historique des statuts d'un employé (actif, essai, suspendu / en congé, parti) : qui, quand, pourquoi.
-- fk_motif = motif de la liste configurable ; motif = commentaire libre ; date_fin_prevue = fin prévue d'une suspension.
CREATE TABLE llx_ecole_employe_statut
(
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER NOT NULL DEFAULT 1,
	fk_employe      INTEGER NOT NULL,
	status_old      SMALLINT,
	status_new      SMALLINT NOT NULL,
	date_statut     DATE NOT NULL,
	date_fin_prevue DATE,
	fk_motif        INTEGER,
	motif           VARCHAR(255),
	fk_user         INTEGER,
	date_creation   DATETIME NOT NULL,
	KEY idx_ecole_employe_statut_emp (fk_employe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
