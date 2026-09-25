-- Présence de l'enseignant à un cours de l'emploi du temps (une classe, un jour, un créneau).
-- PAS de ligne = cours fait (présent par défaut). Une ligne est créée quand :
--   - l'enseignant déclare son cours (declare = 1, sujet facultatif) ;
--   - le surveillant le marque absent ou en retard pendant l'appel (source 'appel') ;
--   - la direction enregistre une absence, un retard, un remplacement ou une correction (source 'direction').
-- etat : 0 = présent, 1 = absent, 2 = en retard (minutes_retard). Absent : l'enseignant ne peut plus déclarer.
-- fk_remplacant = employé qui a fait le cours à la place du titulaire (enregistré par la direction).
CREATE TABLE llx_ecole_cours_prof
(
	rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity            INTEGER NOT NULL DEFAULT 1,
	date_cours        DATE NOT NULL,
	fk_creneau        INTEGER NOT NULL,
	fk_classe         INTEGER NOT NULL,
	fk_matiere        INTEGER,
	fk_employe        INTEGER,
	etat              SMALLINT NOT NULL DEFAULT 0,
	minutes_retard    INTEGER,
	declare_cours     SMALLINT NOT NULL DEFAULT 0,
	date_declaration  DATETIME,
	fk_user_declare   INTEGER,
	sujet             VARCHAR(255),
	fk_remplacant     INTEGER,
	justifiee         SMALLINT NOT NULL DEFAULT 0,
	fk_motif          INTEGER,
	note              VARCHAR(255),
	source            VARCHAR(16),
	date_creation     DATETIME NOT NULL,
	tms               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat     INTEGER,
	fk_user_modif     INTEGER,
	UNIQUE KEY uk_ecole_cours_prof (entity, date_cours, fk_creneau, fk_classe),
	KEY idx_ecole_cours_prof_emp (fk_employe, date_cours),
	KEY idx_ecole_cours_prof_remp (fk_remplacant, date_cours)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
