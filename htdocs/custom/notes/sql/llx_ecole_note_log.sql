-- Historique complet des notes : chaque saisie, modification ou effacement (qui, quand, avant, après).
-- avant / apres : '' (vide), nombre ('12.5'), 'ABS' ou 'DISP'. motif : obligatoire après clôture du trimestre.
CREATE TABLE llx_ecole_note_log
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_evaluation  INTEGER NOT NULL,
	fk_eleve       INTEGER NOT NULL,
	avant          VARCHAR(16),
	apres          VARCHAR(16),
	motif          VARCHAR(255),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_note_log_eval (fk_evaluation),
	KEY idx_ecole_note_log_eleve (fk_eleve),
	KEY idx_ecole_note_log_date (date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
