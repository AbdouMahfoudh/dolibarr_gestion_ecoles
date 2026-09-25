-- Pièces du dossier d'un élève : case « fourni » + fichier scanné (facultatif) pour chaque pièce demandée.
CREATE TABLE llx_ecole_eleve_document
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	fk_eleve           INTEGER NOT NULL,
	fk_document_type   INTEGER NOT NULL,
	fourni             SMALLINT NOT NULL DEFAULT 0,
	date_fourni        DATE,
	filename           VARCHAR(255),
	fk_user            INTEGER,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_ecole_eleve_document (fk_eleve, fk_document_type),
	KEY idx_ecole_eleve_document_type (fk_document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
