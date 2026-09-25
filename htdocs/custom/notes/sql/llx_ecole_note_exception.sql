-- Exception à la règle du niveau pour une matière d'une classe : calcul de la note de devoirs.
CREATE TABLE llx_ecole_note_exception
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_classe      INTEGER NOT NULL,
	fk_matiere     INTEGER NOT NULL,
	calcul_devoirs VARCHAR(10) NOT NULL,          -- 'moyenne' ou 'meilleure'
	date_creation  DATETIME NOT NULL,
	fk_user_creat  INTEGER,
	UNIQUE KEY uk_ecole_note_exception (fk_classe, fk_matiere)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
