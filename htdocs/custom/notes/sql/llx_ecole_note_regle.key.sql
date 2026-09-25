-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_note_regle ADD COLUMN fk_modele INTEGER AFTER seuil_passage;
