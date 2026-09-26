-- Permissions de l espace (personnel, parents, élèves) données par la direction, compte par compte.
-- Sans ligne pour un compte : modèle par défaut selon la catégorie (enseignant, surveillant... ; parent ; élève).
-- Pas d historique : seulement l état actuel.
CREATE TABLE llx_ecole_espace_perm
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER NOT NULL DEFAULT 1,
	type           VARCHAR(8) NOT NULL,              -- parent, eleve ou employe
	fk_cible       INTEGER NOT NULL,                 -- responsable, élève ou employé
	perms          TEXT,                             -- clés des permissions accordées, séparées par des virgules
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_modif  INTEGER,
	UNIQUE KEY uk_ecole_espace_perm (entity, type, fk_cible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
