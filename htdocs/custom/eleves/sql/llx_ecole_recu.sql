-- Reçu de paiement : un encaissement (un élève ou toute une fratrie), avec sa numérotation propre.
-- En coulisses, chaque élève du reçu a une facture Dolibarr déjà payée (comptabilité) ; jamais affichée côté école.
-- status : 1 valide, 0 annulé (le reçu annulé reste visible, barré, avec son motif).
CREATE TABLE llx_ecole_recu
(
	rowid               INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity              INTEGER NOT NULL DEFAULT 1,
	ref                 VARCHAR(32) NOT NULL,
	date_recu           DATE NOT NULL,
	fk_responsable      INTEGER,
	fk_mode             INTEGER NOT NULL,           -- llx_c_paiement.id (mode de paiement Dolibarr)
	fk_bank_account     INTEGER,                    -- compte de trésorerie Dolibarr
	reference_paiement  VARCHAR(64),                -- n° de transaction mobile money, de chèque...
	montant             DOUBLE(24,8) NOT NULL DEFAULT 0,
	note                VARCHAR(255),
	motif_annulation    VARCHAR(255),
	date_annulation     DATETIME,
	fk_user_annulation  INTEGER,
	date_creation       DATETIME NOT NULL,
	tms                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat       INTEGER,
	fk_user_modif       INTEGER,
	status              SMALLINT NOT NULL DEFAULT 1,
	UNIQUE KEY uk_ecole_recu_ref (entity, ref),
	KEY idx_ecole_recu_date (date_recu),
	KEY idx_ecole_recu_responsable (fk_responsable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
