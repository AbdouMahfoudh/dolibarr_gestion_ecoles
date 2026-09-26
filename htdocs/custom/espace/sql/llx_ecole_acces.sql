-- Accès à l'espace parents / élèves : un compte utilisateur Dolibarr (sans aucun droit) par responsable ou par élève.
-- Identifiant = code du responsable (R00004) ou matricule de l'élève (E00004).
-- L'accès est coupé automatiquement quand l'élève (ou tous les enfants du responsable) n'est plus inscrit ou suspendu.
CREATE TABLE llx_ecole_acces
(
	rowid                   INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity                  INTEGER NOT NULL DEFAULT 1,
	type                    VARCHAR(8) NOT NULL,              -- 'parent' ou 'eleve'
	fk_cible                INTEGER NOT NULL,                 -- llx_ecole_responsable.rowid ou llx_ecole_eleve.rowid
	fk_user                 INTEGER NOT NULL,
	annee                   INTEGER NOT NULL DEFAULT 0,       -- année scolaire du code (expire au passage à l'année suivante)                 -- compte utilisateur Dolibarr
	mdp_provisoire          SMALLINT NOT NULL DEFAULT 1,      -- 1 = mot de passe donné par l'école, à changer à la première connexion
	mdp_fiche               VARCHAR(255),                     -- mot de passe provisoire chiffré (réimpression de la fiche), effacé dès qu'il est changé
	langue                  VARCHAR(8),                       -- langue choisie dans l'espace (NULL = celle de la configuration)
	date_derniere_connexion DATETIME,
	nb_connexions           INTEGER NOT NULL DEFAULT 0,
	date_reinit             DATETIME,                         -- dernière réinitialisation du mot de passe par l'école
	fk_user_reinit          INTEGER,
	date_creation           DATETIME NOT NULL,
	fk_user_creat           INTEGER,
	tms                     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	status                  SMALLINT NOT NULL DEFAULT 1,      -- 1 = actif, 0 = désactivé par l'école
	UNIQUE KEY uk_ecole_acces_cible (entity, type, fk_cible),
	UNIQUE KEY uk_ecole_acces_user (fk_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
