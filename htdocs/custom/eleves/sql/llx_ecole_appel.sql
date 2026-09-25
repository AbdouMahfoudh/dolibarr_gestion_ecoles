-- Appel fait pour une classe, un jour et un créneau (une ligne = appel validé).
-- Un créneau sans appel : tous les élèves sont considérés présents (sauf les exclus).
CREATE TABLE llx_ecole_appel
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_classe      INTEGER NOT NULL,
	date_appel     DATE NOT NULL,
	fk_creneau     INTEGER NOT NULL,
	fk_matiere     INTEGER,                        -- matière du cours (emploi du temps au moment de l appel)
	nb_absents     INTEGER NOT NULL DEFAULT 0,
	nb_retards     INTEGER NOT NULL DEFAULT 0,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	UNIQUE KEY uk_ecole_appel (entity, fk_classe, date_appel, fk_creneau),
	KEY idx_ecole_appel_date (date_appel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
