-- ============================================================================
-- Emploi du temps des cours d'une année scolaire passée (copie faite au passage à l'année suivante).
-- Les heures du créneau sont recopiées : les créneaux peuvent changer ensuite.
-- ============================================================================
CREATE TABLE llx_ecole_edt_annee
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	annee          INTEGER NOT NULL,
	fk_classe      INTEGER NOT NULL,
	jour           SMALLINT NOT NULL,
	fk_creneau     INTEGER NOT NULL,
	heure_debut    VARCHAR(5) NOT NULL,
	heure_fin      VARCHAR(5) NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	fk_user        INTEGER,
	fk_salle       INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_edt_annee_classe (annee, fk_classe),
	KEY idx_ecole_edt_annee_user (annee, fk_user),
	KEY idx_ecole_edt_annee_salle (annee, fk_salle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
