-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_bulletin_modele ADD COLUMN couleur VARCHAR(16) NOT NULL DEFAULT 'bleu' AFTER style;
ALTER TABLE llx_ecole_bulletin_modele ADD COLUMN entete VARCHAR(32) AFTER couleur;
