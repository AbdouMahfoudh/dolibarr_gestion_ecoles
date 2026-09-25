-- Lignes d'un bulletin de paie : gains (salaire de base, heures, heures supplémentaires, remplacements, ajouts)
-- et retenues (absences, retards, retenues ajoutées). Montants toujours positifs, le type donne le sens.
-- auto = 1 : ligne calculée depuis la présence (réécrite à chaque recalcul, sauf si modifie = 1) ;
-- auto = 0 : ligne ajoutée à la main par le comptable (jamais touchée par le recalcul).
-- cle / dcle / dargs : clés de traduction du libellé et du détail avec leurs paramètres (JSON), pour réécrire
-- la ligne dans la langue de la fiche de paie ; libelle / detail : textes au moment du calcul (ou saisis).
-- source_type / fk_source : avance ('avance') ou prêt ('pret') retenu par la ligne.
CREATE TABLE llx_ecole_salaire_ligne
(
	rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_salaire       INTEGER NOT NULL,
	type             VARCHAR(8) NOT NULL,
	code             VARCHAR(16) NOT NULL,
	libelle          VARCHAR(255) NOT NULL,
	detail           VARCHAR(255),
	cle              VARCHAR(64),
	dcle             VARCHAR(64),
	dargs            VARCHAR(255),
	source_type      VARCHAR(16),
	fk_source        INTEGER,
	montant          DOUBLE(24,8) NOT NULL DEFAULT 0,
	montant_calcule  DOUBLE(24,8),
	auto             SMALLINT NOT NULL DEFAULT 0,
	modifie          SMALLINT NOT NULL DEFAULT 0,
	position         INTEGER NOT NULL DEFAULT 0,
	date_creation    DATETIME NOT NULL,
	fk_user_creat    INTEGER,
	KEY idx_ecole_salaire_ligne (fk_salaire, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
