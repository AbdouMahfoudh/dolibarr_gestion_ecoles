-- Champs supplémentaires de la fiche élève, ajoutés par l'établissement depuis la configuration du module.
CREATE TABLE llx_ecole_eleve_extrafields
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_object      INTEGER NOT NULL,
	import_key     VARCHAR(14),
	UNIQUE KEY uk_ecole_eleve_extrafields (fk_object)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
