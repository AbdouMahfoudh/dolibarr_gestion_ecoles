-- Emploi du temps de la période d'examens : une ligne = une épreuve d'une classe dans une session.
CREATE TABLE llx_ecole_edt_examen
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_classe      INTEGER NOT NULL,
	fk_session     INTEGER NOT NULL,
	date_examen    DATE NOT NULL,
	heure_debut    VARCHAR(5) NOT NULL,            -- HH:MM
	heure_fin      VARCHAR(5) NOT NULL,            -- HH:MM
	fk_matiere     INTEGER NOT NULL,
	fk_salle       INTEGER,                        -- llx_resource.rowid
	fk_user        INTEGER,                        -- surveillant (llx_user.rowid)
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	KEY idx_ecole_edt_examen_classe (fk_classe, fk_session),
	KEY idx_ecole_edt_examen_date (date_examen),
	KEY idx_ecole_edt_examen_matiere (fk_matiere),
	KEY idx_ecole_edt_examen_session (fk_session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
