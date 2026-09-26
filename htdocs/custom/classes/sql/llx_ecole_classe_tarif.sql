-- ============================================================================
-- Tarifs d'une classe pour une année scolaire (année de la rentrée).
-- Les tarifs de l'année en cours sont ceux de la fiche de la classe ; ils sont recopiés ici au passage
-- à l'année suivante. Les tarifs de l'année suivante peuvent être préparés à l'avance.
-- ============================================================================
CREATE TABLE llx_ecole_classe_tarif
(
	rowid               INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity              INTEGER NOT NULL DEFAULT 1,
	annee               INTEGER NOT NULL,
	fk_classe           INTEGER NOT NULL,
	mensualite          DOUBLE(24,8) NOT NULL DEFAULT 0,
	frais_inscription   DOUBLE(24,8) NOT NULL DEFAULT 0,
	frais_reinscription DOUBLE(24,8),                 -- vide = règle générale de la réinscription
	date_creation       DATETIME NOT NULL,
	tms                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat       INTEGER,
	fk_user_modif       INTEGER,
	UNIQUE KEY uk_ecole_classe_tarif (fk_classe, annee),
	KEY idx_ecole_classe_tarif_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
