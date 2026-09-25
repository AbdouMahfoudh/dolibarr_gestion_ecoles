-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_niveau ADD COLUMN bareme_defaut VARCHAR(8) NOT NULL DEFAULT '20' AFTER avec_sections;
