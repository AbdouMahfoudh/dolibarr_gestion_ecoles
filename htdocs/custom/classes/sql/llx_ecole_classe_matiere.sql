-- Liaison matière <-> classe avec son coefficient.
-- bareme = '20'    : la note est sur 20
-- bareme = 'coef20': la note est sur coefficient x 20 (ex. coef 2,5 -> sur 50)
-- note_max est calculé par l'application à partir du barème.
CREATE TABLE llx_ecole_classe_matiere
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_classe      INTEGER NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	coefficient    DOUBLE(8,2) NOT NULL DEFAULT 1,
	bareme         VARCHAR(8) NOT NULL DEFAULT '20',
	note_max       DOUBLE(8,2) NOT NULL DEFAULT 20,
	langue         VARCHAR(2),                     -- langue d'enseignement dans cette classe (vide = celle de la matière)
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	UNIQUE KEY uk_ecole_classe_matiere (fk_classe, fk_matiere),
	KEY idx_ecole_classe_matiere_matiere (fk_matiere)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
