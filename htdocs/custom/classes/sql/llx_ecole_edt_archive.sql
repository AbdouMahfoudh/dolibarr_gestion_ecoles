-- Historique des emplois du temps : ancienne version conservée à chaque modification ou suppression.
-- donnees = copie JSON de la ligne d'avant (identifiants + libellés lisibles).
CREATE TABLE llx_ecole_edt_archive
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	type           VARCHAR(16) NOT NULL,           -- cours / examen
	action         VARCHAR(16) NOT NULL,           -- update / delete
	fk_classe      INTEGER NOT NULL,
	donnees        MEDIUMTEXT NOT NULL,
	fk_user        INTEGER,
	date_archive   DATETIME NOT NULL,
	KEY idx_ecole_edt_archive_classe (fk_classe, type),
	KEY idx_ecole_edt_archive_date (date_archive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
