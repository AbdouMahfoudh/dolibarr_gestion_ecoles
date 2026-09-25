-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_salaire_pret ADD COLUMN numero_compte VARCHAR(64) AFTER reference_paiement;
