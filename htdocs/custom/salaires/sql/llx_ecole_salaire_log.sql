-- Historique d'un bulletin de paie : création, recalcul, lignes modifiées, validation, réouverture, paiement,
-- annulation du paiement, annulation (qui, quand, motif).
CREATE TABLE llx_ecole_salaire_log
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	fk_salaire     INTEGER NOT NULL,
	action         VARCHAR(16) NOT NULL,
	detail         VARCHAR(255),
	motif          VARCHAR(255),
	fk_user        INTEGER,
	date_creation  DATETIME NOT NULL,
	KEY idx_ecole_salaire_log (fk_salaire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
