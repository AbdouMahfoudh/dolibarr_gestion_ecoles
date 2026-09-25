-- Absences et retards relevés à l appel. type : 1 = absent, 2 = en retard (heure d arrivée facultative),
-- 3 = renvoyé du cours par le professeur (compté comme une absence simple, ce n est pas une sanction).
-- Justification : case + motif (liste configurable) + remarque facultative. Outil de suivi seulement.
CREATE TABLE llx_ecole_absence
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_appel       INTEGER NOT NULL,
	fk_eleve       INTEGER NOT NULL,
	fk_classe      INTEGER NOT NULL,
	date_appel     DATE NOT NULL,
	fk_creneau     INTEGER NOT NULL,
	type           SMALLINT NOT NULL DEFAULT 1,
	heure_arrivee  VARCHAR(5),
	justifiee      SMALLINT NOT NULL DEFAULT 0,
	fk_motif       INTEGER,
	justif_note    VARCHAR(255),
	fk_user_justif INTEGER,
	date_justif    DATETIME,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	UNIQUE KEY uk_ecole_absence (fk_appel, fk_eleve),
	KEY idx_ecole_absence_eleve (fk_eleve, date_appel),
	KEY idx_ecole_absence_classe (fk_classe, date_appel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
