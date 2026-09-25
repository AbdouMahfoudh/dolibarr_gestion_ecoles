-- Emploi du temps des cours : une ligne = une classe, un jour, un créneau.
-- jour : 1 = lundi ... 7 = dimanche
CREATE TABLE llx_ecole_edt_cours
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_classe      INTEGER NOT NULL,
	jour           SMALLINT NOT NULL,
	fk_creneau     INTEGER NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	fk_user        INTEGER,                        -- enseignant (llx_user.rowid)
	fk_salle       INTEGER,                        -- llx_resource.rowid
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	UNIQUE KEY uk_ecole_edt_cours_slot (fk_classe, jour, fk_creneau),
	KEY idx_ecole_edt_cours_user (fk_user, jour, fk_creneau),
	KEY idx_ecole_edt_cours_salle (fk_salle, jour, fk_creneau),
	KEY idx_ecole_edt_cours_matiere (fk_matiere)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
