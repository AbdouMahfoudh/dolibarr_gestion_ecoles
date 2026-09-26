-- Modèles de PDF paramétrables (listes, reçus de paiement, bulletins de paie), créés et dupliqués depuis l interface.
-- entete vide = style de la configuration ; filigrane : defaut (celui de la configuration), aucun, texte ou image.
CREATE TABLE llx_ecole_pdf_modele
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	ref            VARCHAR(32) NOT NULL,
	label_fr       VARCHAR(128) NOT NULL,
	label_ar       VARCHAR(128),
	type_doc       VARCHAR(8) NOT NULL DEFAULT 'liste',   -- liste, recu, paie
	entete         VARCHAR(32),
	couleur        VARCHAR(16) NOT NULL DEFAULT 'bleu',
	orientation    VARCHAR(4) NOT NULL DEFAULT 'auto',    -- auto (portrait si possible), P, L
	taille_police  DOUBLE(4,1) NOT NULL DEFAULT 8,
	filigrane      VARCHAR(8) NOT NULL DEFAULT 'defaut',  -- defaut, aucun, texte, image
	fil_texte      VARCHAR(64),
	fil_angle      INTEGER NOT NULL DEFAULT 35,
	fil_opacite    INTEGER NOT NULL DEFAULT 10,           -- en pour cent
	fil_taille     INTEGER NOT NULL DEFAULT 50,
	fil_couleur    VARCHAR(16) NOT NULL DEFAULT 'gris',
	style          VARCHAR(16) NOT NULL DEFAULT 'classique', -- classique, lignes, encadre
	langue         VARCHAR(4) NOT NULL DEFAULT 'auto',       -- auto (langue de l utilisateur), fr, ar
	colonnes_masquees VARCHAR(255),                         -- listes : titres des colonnes à ne pas imprimer
	opt_numeroter  SMALLINT NOT NULL DEFAULT 0,
	opt_total      SMALLINT NOT NULL DEFAULT 1,
	opt_date       SMALLINT NOT NULL DEFAULT 1,
	opt_signature  SMALLINT NOT NULL DEFAULT 0,
	opt_situation  SMALLINT NOT NULL DEFAULT 1,
	opt_caissier   SMALLINT NOT NULL DEFAULT 1,
	opt_signature_recu SMALLINT NOT NULL DEFAULT 1,
	opt_lettres_recu SMALLINT NOT NULL DEFAULT 0,
	opt_souche     SMALLINT NOT NULL DEFAULT 0,
	opt_seances    SMALLINT NOT NULL DEFAULT 1,
	opt_presence   SMALLINT NOT NULL DEFAULT 1,
	opt_avances    SMALLINT NOT NULL DEFAULT 1,
	opt_signatures_paie SMALLINT NOT NULL DEFAULT 1,
	opt_lettres_paie SMALLINT NOT NULL DEFAULT 0,
	par_defaut     SMALLINT NOT NULL DEFAULT 0,
	position       INTEGER NOT NULL DEFAULT 0,
	description    TEXT,
	date_creation  DATETIME NOT NULL,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  INTEGER,
	fk_user_modif  INTEGER,
	status         SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_pdf_modele_ref (entity, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
