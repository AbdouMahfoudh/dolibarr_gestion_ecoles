-- Avance sur salaire : argent donné à l'employé avant la paie, retenu ensuite sur son prochain bulletin
-- (en une fois par défaut ; le comptable peut n'en retenir qu'une partie, le reste passe au bulletin suivant).
-- Versement : salaire natif de Dolibarr (« Avance sur salaire ») payé tout de suite, dans le compte du mode.
-- Ce qui est déjà retenu = somme des lignes « avance » des bulletins non annulés (llx_ecole_salaire_ligne).
-- status : 1 = versée, 0 = annulée (seulement si rien n'a encore été retenu).
CREATE TABLE llx_ecole_salaire_avance
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	ref                VARCHAR(10) NOT NULL,
	fk_employe         INTEGER NOT NULL,
	date_avance        DATE NOT NULL,
	montant            DOUBLE(24,8) NOT NULL DEFAULT 0,
	fk_mode            INTEGER,
	fk_bank_account    INTEGER,
	reference_paiement VARCHAR(64),
	numero_compte      VARCHAR(64),
	fk_salary          INTEGER,
	fk_payment_salary  INTEGER,
	note               VARCHAR(255),
	motif_annulation   VARCHAR(255),
	date_annulation    DATETIME,
	fk_user_annulation INTEGER,
	date_creation      DATETIME NOT NULL,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER,
	fk_user_modif      INTEGER,
	status             SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_salaire_avance_ref (entity, ref),
	KEY idx_ecole_salaire_avance_emp (fk_employe, date_avance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
