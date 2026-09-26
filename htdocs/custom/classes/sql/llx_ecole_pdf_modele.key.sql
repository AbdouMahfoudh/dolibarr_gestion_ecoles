-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l installeur Dolibarr).
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN style VARCHAR(16) NOT NULL DEFAULT 'classique' AFTER type_doc;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN langue VARCHAR(4) NOT NULL DEFAULT 'auto' AFTER style;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN colonnes_masquees VARCHAR(255) AFTER langue;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_numeroter SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_total SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_date SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_signature SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_situation SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_caissier SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_signature_recu SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_lettres_recu SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_souche SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_seances SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_presence SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_avances SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_signatures_paie SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE llx_ecole_pdf_modele ADD COLUMN opt_lettres_paie SMALLINT NOT NULL DEFAULT 0;
