-- Modèles de bulletin : langue (mixte = chaque matière dans sa langue d'enseignement, fr, ar),
-- style visuel du PDF et ce qui est affiché. Le modèle par défaut d'un niveau se choisit dans les
-- règles de calcul ; on peut en choisir un autre à l'impression.
CREATE TABLE llx_ecole_bulletin_modele
(
	rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity           INTEGER NOT NULL DEFAULT 1,
	ref              VARCHAR(32) NOT NULL,
	label_fr         VARCHAR(128) NOT NULL,
	label_ar         VARCHAR(128),
	langue           VARCHAR(8) NOT NULL DEFAULT 'mixte',
	style            VARCHAR(16) NOT NULL DEFAULT 'classique',   -- mise en page : classique, lignes, encadre
	couleur          VARCHAR(16) NOT NULL DEFAULT 'bleu',        -- bleu, vert, bordeaux, violet, orange, gris, aucune (noir et blanc)
	entete           VARCHAR(32),                                -- style d'en-tête du PDF (vide = celui de la configuration)
	simplifie        TINYINT NOT NULL DEFAULT 0,   -- notes complètes (ex. 28/30), sans coefficient ni détail
	aff_detail       TINYINT NOT NULL DEFAULT 1,   -- colonnes devoirs et composition
	aff_coef         TINYINT NOT NULL DEFAULT 1,   -- coefficient et total des points
	aff_rang_matiere TINYINT NOT NULL DEFAULT 0,
	aff_moy_classe   TINYINT NOT NULL DEFAULT 1,   -- moyenne de la classe par matière
	aff_min_max      TINYINT NOT NULL DEFAULT 0,   -- plus faible / plus forte note de la classe par matière
	aff_rang         TINYINT NOT NULL DEFAULT 1,   -- rang général
	aff_rappel       TINYINT NOT NULL DEFAULT 1,   -- rappel des moyennes des trimestres précédents
	aff_distinction  TINYINT NOT NULL DEFAULT 1,
	aff_signature_parent TINYINT NOT NULL DEFAULT 1,
	position         INTEGER NOT NULL DEFAULT 0,
	description      TEXT,
	date_creation    DATETIME NOT NULL,
	tms              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat    INTEGER,
	fk_user_modif    INTEGER,
	status           SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_bulletin_modele_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
