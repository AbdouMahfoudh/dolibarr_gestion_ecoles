-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_salaire_lot ADD COLUMN mois VARCHAR(7) AFTER date_fin;
UPDATE llx_ecole_salaire_lot SET mois = LEFT(date_fin, 7) WHERE mois IS NULL;
