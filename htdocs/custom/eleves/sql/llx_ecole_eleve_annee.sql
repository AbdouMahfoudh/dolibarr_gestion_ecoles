-- ============================================================================
-- Dossier d'un élève pour une année scolaire passée (gardé au passage à l'année suivante) :
-- classe, statut et réglages de paiement de l'année, décision de passage, montants dus et payés,
-- arriéré (reste à payer reporté sur les années suivantes).
-- ============================================================================
CREATE TABLE llx_ecole_eleve_annee
(
	rowid                INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity               INTEGER NOT NULL DEFAULT 1,
	annee                INTEGER NOT NULL,
	fk_eleve             INTEGER NOT NULL,
	fk_classe            INTEGER,
	numero_appel         INTEGER,
	status               SMALLINT NOT NULL,            -- statut à la fin de l'année
	decision             VARCHAR(16),                  -- admis, redouble, non_repris, sorti
	fk_classe_suivante   INTEGER,                      -- classe proposée pour l'année suivante
	date_inscription     DATE,
	mois_debut           VARCHAR(7),
	reduction_type       VARCHAR(8),
	reduction_valeur     DOUBLE(24,8),
	reduction_motif      VARCHAR(255),
	frais_inscription_du DOUBLE(24,8),
	exo_type             VARCHAR(8),
	exo_valeur           DOUBLE(24,8),
	fk_motif_exo         INTEGER,
	total_du             DOUBLE(24,8) NOT NULL DEFAULT 0,
	total_paye           DOUBLE(24,8) NOT NULL DEFAULT 0,
	arriere              DOUBLE(24,8) NOT NULL DEFAULT 0,
	date_creation        DATETIME NOT NULL,
	fk_user_creat        INTEGER,
	UNIQUE KEY uk_ecole_eleve_annee (fk_eleve, annee),
	KEY idx_ecole_eleve_annee_annee (annee, fk_classe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
