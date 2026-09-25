-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
-- source_type / fk_source : avance ou prêt retenu par la ligne (NULL pour les autres lignes)
ALTER TABLE llx_ecole_salaire_ligne ADD COLUMN source_type VARCHAR(16) AFTER dargs;
ALTER TABLE llx_ecole_salaire_ligne ADD COLUMN fk_source INTEGER AFTER source_type;
ALTER TABLE llx_ecole_salaire_ligne ADD INDEX idx_ecole_salaire_ligne_source (source_type, fk_source);
