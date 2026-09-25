-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_salaire ADD COLUMN mois VARCHAR(7) AFTER date_fin;
ALTER TABLE llx_ecole_salaire ADD COLUMN numero_compte VARCHAR(64) AFTER reference_paiement;
ALTER TABLE llx_ecole_salaire ADD INDEX idx_ecole_salaire_mois (fk_employe, mois);
UPDATE llx_ecole_salaire SET mois = LEFT(date_fin, 7) WHERE mois IS NULL;
