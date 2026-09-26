-- Conseil de classe : ce que la direction ajoute au bulletin d'un élève pour une période
-- (trimestre 1 à 3, 0 = année / relevé annuel) : observation, distinction choisie, décision.
-- distinction_forcee = 0 : distinction automatique ; 1 : fk_distinction choisi à la main (vide = aucune).
CREATE TABLE llx_ecole_bulletin
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	annee              INTEGER NOT NULL DEFAULT 0,  -- année scolaire (année de la rentrée)
	fk_eleve           INTEGER NOT NULL,
	fk_classe          INTEGER NOT NULL,
	trimestre          SMALLINT NOT NULL,
	observation        TEXT,
	distinction_forcee TINYINT NOT NULL DEFAULT 0,
	fk_distinction     INTEGER,
	decision           VARCHAR(16),               -- relevé annuel : 'admis', 'redouble' (vide = proposition automatique)
	date_creation      DATETIME NOT NULL,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER,
	fk_user_modif      INTEGER,
	UNIQUE KEY uk_ecole_bulletin (fk_eleve, trimestre, annee),
	KEY idx_ecole_bulletin_classe (fk_classe, trimestre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
