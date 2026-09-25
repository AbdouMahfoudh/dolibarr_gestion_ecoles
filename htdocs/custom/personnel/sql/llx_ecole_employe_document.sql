-- Pièces du dossier d'un employé : case « fourni » + fichier scanné (facultatif) pour chaque pièce demandée.
CREATE TABLE llx_ecole_employe_document
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	fk_employe         INTEGER NOT NULL,
	fk_doc_type        INTEGER NOT NULL,
	fourni             SMALLINT NOT NULL DEFAULT 0,
	date_fourni        DATE,
	filename           VARCHAR(255),
	fk_user            INTEGER,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_ecole_employe_document (fk_employe, fk_doc_type),
	KEY idx_ecole_employe_document_type (fk_doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
