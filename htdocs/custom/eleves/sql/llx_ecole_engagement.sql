-- Engagements signés par le responsable à l inscription (règlement intérieur, paiement...), liste configurable.
-- Textes en français et en arabe avec des variables remplacées à l impression ([responsable], [eleve], [classe]...).
CREATE TABLE llx_ecole_engagement
(
	rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity           INTEGER NOT NULL DEFAULT 1,
	ref              VARCHAR(32) NOT NULL,
	label_fr         VARCHAR(128) NOT NULL,
	label_ar         VARCHAR(128),
	texte_fr         TEXT,
	texte_ar         TEXT,
	signature_eleve  SMALLINT NOT NULL DEFAULT 0,   -- 1 = signature de l élève aussi (collège et lycée)
	position         INTEGER NOT NULL DEFAULT 0,
	date_creation    DATETIME NOT NULL,
	tms              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat    INTEGER,
	fk_user_modif    INTEGER,
	status           SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_engagement_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
