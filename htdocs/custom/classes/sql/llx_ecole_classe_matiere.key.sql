-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_classe_matiere ADD COLUMN langue VARCHAR(2) AFTER note_max;
