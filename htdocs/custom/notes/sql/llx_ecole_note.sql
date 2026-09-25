-- Note d'un élève à une évaluation. valeur vide + absence : 'ABS' (absent) ou 'DISP' (dispensé).
-- Pas de ligne = note pas encore saisie.
CREATE TABLE llx_ecole_note
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_evaluation  INTEGER NOT NULL,
	fk_eleve       INTEGER NOT NULL,
	valeur         DOUBLE(8,2),
	absence        VARCHAR(4),
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	UNIQUE KEY uk_ecole_note (fk_evaluation, fk_eleve),
	KEY idx_ecole_note_eleve (fk_eleve)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
