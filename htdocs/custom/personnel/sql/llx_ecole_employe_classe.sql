-- Classes où un enseignant déclare enseigner (information de la fiche, sans blocage : il peut aussi être placé
-- dans une autre classe de l'emploi du temps). Les MATIÈRES, elles, sont contrôlées (llx_ecole_employe_matiere).
CREATE TABLE llx_ecole_employe_classe
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_employe     INTEGER NOT NULL,
	fk_classe      INTEGER NOT NULL,
	UNIQUE KEY uk_ecole_employe_classe (fk_employe, fk_classe),
	KEY idx_ecole_employe_classe_cl (fk_classe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
