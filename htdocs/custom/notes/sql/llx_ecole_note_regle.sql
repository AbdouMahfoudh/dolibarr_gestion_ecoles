-- Règles de calcul des notes d'un niveau (une ligne par niveau ; niveau sans ligne = règles par défaut).
-- calcul_devoirs       : 'moyenne' (moyenne des devoirs) ou 'meilleure' (meilleure note de devoir)
-- poids_devoirs/compo  : note du trimestre = (devoirs x poids_devoirs + composition x poids_compo) / (somme des poids)
-- devoir_absent        : 'ignore' (le devoir manqué ne compte pas) ou 'zero'
-- compo_absent         : 'zero' (composition manquée = 0) ou 'devoirs' (note du trimestre = note de devoirs seule)
-- trimestre_sans_compo : 'exclure' (absent à toutes les compositions : trimestre non compté dans l'année) ou 'garder'
-- poids_t1/t2/t3       : poids de chaque trimestre dans la moyenne annuelle
-- rang_exaequo         : 'meme' (même rang) ou 'compo' (départage par la moyenne des compositions)
-- decision             : 'aucune', 'manuelle' ou 'auto' (proposée selon seuil_passage, modifiable)
-- fk_modele            : modèle de bulletin proposé pour ce niveau (vide = premier modèle actif)
CREATE TABLE llx_ecole_note_regle
(
	rowid                INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity               INTEGER NOT NULL DEFAULT 1,
	fk_niveau            INTEGER NOT NULL,
	calcul_devoirs       VARCHAR(10) NOT NULL DEFAULT 'moyenne',
	poids_devoirs        DOUBLE(8,2) NOT NULL DEFAULT 1,
	poids_compo          DOUBLE(8,2) NOT NULL DEFAULT 2,
	devoir_absent        VARCHAR(10) NOT NULL DEFAULT 'ignore',
	compo_absent         VARCHAR(10) NOT NULL DEFAULT 'zero',
	trimestre_sans_compo VARCHAR(10) NOT NULL DEFAULT 'exclure',
	poids_t1             DOUBLE(8,2) NOT NULL DEFAULT 1,
	poids_t2             DOUBLE(8,2) NOT NULL DEFAULT 1,
	poids_t3             DOUBLE(8,2) NOT NULL DEFAULT 1,
	rang_exaequo         VARCHAR(10) NOT NULL DEFAULT 'meme',
	decision             VARCHAR(10) NOT NULL DEFAULT 'auto',
	seuil_passage        DOUBLE(8,2) NOT NULL DEFAULT 10,
	fk_modele            INTEGER,                  -- modèle de bulletin par défaut du niveau (llx_ecole_bulletin_modele)
	date_creation        DATETIME NOT NULL,
	tms                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat        INTEGER,
	fk_user_modif        INTEGER,
	UNIQUE KEY uk_ecole_note_regle_niveau (entity, fk_niveau)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
