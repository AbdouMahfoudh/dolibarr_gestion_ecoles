-- Évaluations : devoirs (autant que l'enseignant veut) et composition (une par matière et par trimestre).
-- type : 1 = devoir, 2 = composition. note_max : maximum au moment de la création (sur 20 ou coefficient x 20).
-- status : 1 = active, 0 = supprimée (gardée pour l'historique, ne compte plus).
CREATE TABLE llx_ecole_evaluation
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	annee          INTEGER NOT NULL DEFAULT 0,       -- année scolaire (année de la rentrée)
	fk_classe      INTEGER NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	trimestre      SMALLINT NOT NULL,
	type           SMALLINT NOT NULL DEFAULT 1,
	numero         SMALLINT NOT NULL DEFAULT 1,
	label          VARCHAR(128),
	date_eval      DATE,
	note_max       DOUBLE(8,2) NOT NULL DEFAULT 20,
	fk_session     INTEGER,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	date_suppression DATETIME,
	fk_user_suppression INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	KEY idx_ecole_evaluation_classe (fk_classe, fk_matiere, trimestre),
	KEY idx_ecole_evaluation_matiere (fk_matiere),
	KEY idx_ecole_evaluation_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
