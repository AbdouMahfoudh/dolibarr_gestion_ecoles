-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_matiere ADD COLUMN langue VARCHAR(2) NOT NULL DEFAULT 'fr' AFTER label_ar;
