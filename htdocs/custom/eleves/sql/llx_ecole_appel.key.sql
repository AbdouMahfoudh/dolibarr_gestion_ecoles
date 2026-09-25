-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_appel ADD COLUMN fk_matiere INTEGER AFTER fk_creneau;
