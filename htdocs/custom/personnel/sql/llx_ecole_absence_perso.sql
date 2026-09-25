-- Absences du personnel (tous les employés), saisies par la direction ou le secrétariat.
-- Présent par défaut : seules les absences sont enregistrées. Une journée ou une période ;
-- duree : 'jour' (journée entière), 'matin', 'apresmidi' (demi-journée, pour une seule date).
-- Pour un enseignant, ses cours pendant l'absence comptent comme non faits.
-- Annulation (erreur de saisie) : status = 0 avec motif, la ligne est gardée.
CREATE TABLE llx_ecole_absence_perso
(
	rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity            INTEGER NOT NULL DEFAULT 1,
	fk_employe        INTEGER NOT NULL,
	date_debut        DATE NOT NULL,
	date_fin          DATE NOT NULL,
	duree             VARCHAR(10) NOT NULL DEFAULT 'jour',
	justifiee         SMALLINT NOT NULL DEFAULT 0,
	fk_motif          INTEGER,
	note              VARCHAR(255),
	motif_annulation  VARCHAR(255),
	date_annulation   DATETIME,
	fk_user_annul     INTEGER,
	date_creation     DATETIME NOT NULL,
	tms               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat     INTEGER,
	fk_user_modif     INTEGER,
	status            SMALLINT NOT NULL DEFAULT 1,
	KEY idx_ecole_absence_perso_emp (fk_employe, date_debut),
	KEY idx_ecole_absence_perso_date (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
