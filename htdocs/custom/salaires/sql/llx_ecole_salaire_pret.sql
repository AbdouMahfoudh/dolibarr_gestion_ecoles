-- Prêt au personnel : somme remboursée en plusieurs mensualités retenues sur les bulletins de paie, une par
-- bulletin à partir du premier mois choisi (montant modifiable sur le bulletin : le reste passe aux suivants).
-- Versement : écriture de sortie dans le compte de trésorerie du mode (fk_bank). Un prêt n'est pas un salaire.
-- Ce qui est déjà remboursé = somme des lignes « prêt » des bulletins non annulés (llx_ecole_salaire_ligne).
-- status : 1 = versé, 0 = annulé (seulement si rien n'a encore été retenu).
CREATE TABLE llx_ecole_salaire_pret
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	ref                VARCHAR(10) NOT NULL,
	fk_employe         INTEGER NOT NULL,
	date_pret          DATE NOT NULL,
	montant            DOUBLE(24,8) NOT NULL DEFAULT 0,
	nb_echeances       INTEGER NOT NULL DEFAULT 1,
	montant_echeance   DOUBLE(24,8) NOT NULL DEFAULT 0,
	premier_mois       VARCHAR(7) NOT NULL,
	fk_mode            INTEGER,
	fk_bank_account    INTEGER,
	reference_paiement VARCHAR(64),
	numero_compte      VARCHAR(64),
	fk_bank            INTEGER,
	note               VARCHAR(255),
	motif_annulation   VARCHAR(255),
	date_annulation    DATETIME,
	fk_user_annulation INTEGER,
	date_creation      DATETIME NOT NULL,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER,
	fk_user_modif      INTEGER,
	status             SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_salaire_pret_ref (entity, ref),
	KEY idx_ecole_salaire_pret_emp (fk_employe, date_pret)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
