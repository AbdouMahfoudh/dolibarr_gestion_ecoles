-- Lignes d'un reçu : ce que chaque élève a payé (frais d'inscription, mensualité d'un mois, autres frais).
-- type : inscription | mensualite | autre ; periode = mois payé (AAAA-MM) pour une mensualité.
-- fk_facture / fk_paiement : facture et paiement Dolibarr créés en coulisses. status : 1 valide, 0 annulé.
CREATE TABLE llx_ecole_paiement
(
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER NOT NULL DEFAULT 1,
	fk_recu         INTEGER NOT NULL,
	fk_eleve        INTEGER NOT NULL,
	type            VARCHAR(16) NOT NULL,
	periode         VARCHAR(7),
	fk_frais_type   INTEGER,
	libelle         VARCHAR(255) NOT NULL,
	montant         DOUBLE(24,8) NOT NULL DEFAULT 0,
	fk_facture      INTEGER,
	fk_paiement     INTEGER,
	status          SMALLINT NOT NULL DEFAULT 1,
	KEY idx_ecole_paiement_recu (fk_recu),
	KEY idx_ecole_paiement_eleve (fk_eleve, type, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
