-- Clôture d'un trimestre pour une classe (par la direction) : notes verrouillées, bulletins imprimables.
-- status : 1 = clôturé, 0 = rouvert (la réouverture est gardée : qui, quand, motif).
CREATE TABLE llx_ecole_note_cloture
(
	rowid               INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity              INTEGER NOT NULL DEFAULT 1,
	annee               INTEGER NOT NULL DEFAULT 0,  -- année scolaire (année de la rentrée)
	fk_classe           INTEGER NOT NULL,
	trimestre           SMALLINT NOT NULL,
	status              SMALLINT NOT NULL DEFAULT 1,
	date_cloture        DATETIME,
	fk_user_cloture     INTEGER,
	date_reouverture    DATETIME,
	fk_user_reouverture INTEGER,
	motif_reouverture   VARCHAR(255),
	UNIQUE KEY uk_ecole_note_cloture (fk_classe, trimestre, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
