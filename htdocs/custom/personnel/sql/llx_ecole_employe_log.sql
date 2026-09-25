-- Historique des modifications sensibles d'une fiche employé (salaire, prix de l'heure, mode de paie,
-- catégories, compte utilisateur relié...) : qui, quand, avant, après.
-- Un nouveau salaire ou prix de l'heure s'applique à tous les mois dont le salaire n'est pas encore payé.
CREATE TABLE llx_ecole_employe_log
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_employe     INTEGER NOT NULL,
	champ          VARCHAR(32) NOT NULL,
	avant          VARCHAR(255),
	apres          VARCHAR(255),
	motif          VARCHAR(255),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_employe_log_emp (fk_employe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
