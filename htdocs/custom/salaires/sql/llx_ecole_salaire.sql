-- Bulletin de paie d'un employé pour une période libre (date_debut → date_fin, jamais deux bulletins non annulés
-- sur des dates communes pour le même employé).
-- Statuts : 0 = brouillon (recalculable, modifiable), 1 = validé (visible par l'employé), 2 = payé, 9 = annulé.
-- Les éléments de paie de l'employé (mode, salaire, prix de l'heure...) sont copiés au moment du calcul : un bulletin
-- validé ne change plus si la fiche de l'employé change ensuite.
-- mois : mois du salaire (AAAA-MM), un seul bulletin non annulé par employé et par mois ; la période peut déborder
-- sur le mois d'avant (ex. du 25/09 au 25/10 = salaire d'octobre). numero_compte : compte mobile (Bankily, Sedad...).
-- Paiement : salaire natif de Dolibarr (llx_salary + llx_payment_salary + écriture dans le compte du mode).
CREATE TABLE llx_ecole_salaire
(
	rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER NOT NULL DEFAULT 1,
	ref                VARCHAR(10) NOT NULL,
	fk_employe         INTEGER NOT NULL,
	fk_lot             INTEGER,
	date_debut         DATE NOT NULL,
	date_fin           DATE NOT NULL,
	mois               VARCHAR(7),
	-- Éléments de paie copiés au calcul
	mode_paie          VARCHAR(8),
	salaire_base       DOUBLE(24,8),
	taux_horaire       DOUBLE(24,8),
	heures_semaine     DOUBLE(24,8),
	taux_heure_sup     DOUBLE(24,8),
	-- Résumé de la présence (minutes, jours)
	minutes_prevues    INTEGER DEFAULT 0,
	minutes_faites     INTEGER DEFAULT 0,
	minutes_payees     INTEGER DEFAULT 0,
	minutes_absent_nj  INTEGER DEFAULT 0,
	minutes_absent_j   INTEGER DEFAULT 0,
	minutes_retard     INTEGER DEFAULT 0,
	minutes_hsup       INTEGER DEFAULT 0,
	minutes_rempl      INTEGER DEFAULT 0,
	nb_remplacements   INTEGER DEFAULT 0,
	jours_ouvrables    INTEGER DEFAULT 0,
	jours_absent_nj    DOUBLE(24,8) DEFAULT 0,
	jours_absent_j     DOUBLE(24,8) DEFAULT 0,
	calcul             TEXT,
	-- Totaux
	total_gains        DOUBLE(24,8) NOT NULL DEFAULT 0,
	total_retenues     DOUBLE(24,8) NOT NULL DEFAULT 0,
	net                DOUBLE(24,8) NOT NULL DEFAULT 0,
	note_public        TEXT,
	note_private       TEXT,
	date_calcul        DATETIME,
	date_validation    DATETIME,
	fk_user_valid      INTEGER,
	-- Paiement
	date_paiement      DATE,
	fk_mode            INTEGER,
	fk_bank_account    INTEGER,
	reference_paiement VARCHAR(64),
	numero_compte      VARCHAR(64),
	fk_salary          INTEGER,
	fk_payment_salary  INTEGER,
	fk_user_paie       INTEGER,
	-- Annulation
	motif_annulation   VARCHAR(255),
	date_annulation    DATETIME,
	fk_user_annulation INTEGER,
	date_creation      DATETIME NOT NULL,
	tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER,
	fk_user_modif      INTEGER,
	status             SMALLINT NOT NULL DEFAULT 0,
	UNIQUE KEY uk_ecole_salaire_ref (entity, ref),
	KEY idx_ecole_salaire_emp (fk_employe, date_debut),
	KEY idx_ecole_salaire_lot (fk_lot),
	KEY idx_ecole_salaire_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
