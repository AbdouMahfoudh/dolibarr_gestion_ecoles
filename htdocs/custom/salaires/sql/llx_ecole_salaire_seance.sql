-- Séances d'un enseignant retenues pour un bulletin de paie (copie au moment du calcul, pour la fiche de paie) :
-- cours faits, déclarés, en retard, absents, remplacements faits. etat : present | declare | retard | absent | remplacement.
CREATE TABLE llx_ecole_salaire_seance
(
	rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_salaire       INTEGER NOT NULL,
	date_seance      DATE NOT NULL,
	heure_debut      VARCHAR(5),
	heure_fin        VARCHAR(5),
	creneau          VARCHAR(32),
	classe           VARCHAR(32),
	matiere          VARCHAR(255),
	etat             VARCHAR(16) NOT NULL,
	minutes          INTEGER NOT NULL DEFAULT 0,
	minutes_payees   INTEGER NOT NULL DEFAULT 0,
	retard           INTEGER NOT NULL DEFAULT 0,
	justifiee        SMALLINT NOT NULL DEFAULT 0,
	KEY idx_ecole_salaire_seance (fk_salaire, date_seance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
