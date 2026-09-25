-- Lot de salaires : les bulletins de plusieurs employés préparés ensemble pour la même période
-- (valider, payer, imprimer tout le lot en une fois).
CREATE TABLE llx_ecole_salaire_lot
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(10) NOT NULL,
	libelle        VARCHAR(255),
	date_debut     DATE NOT NULL,
	date_fin       DATE NOT NULL,
	mois           VARCHAR(7),
	note           TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_salaire_lot_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
