-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_eleve ADD COLUMN mois_debut VARCHAR(7) AFTER fk_soc;
ALTER TABLE llx_ecole_eleve ADD COLUMN reduction_type VARCHAR(8) AFTER mois_debut;
ALTER TABLE llx_ecole_eleve ADD COLUMN reduction_valeur DOUBLE(24,8) AFTER reduction_type;
ALTER TABLE llx_ecole_eleve ADD COLUMN reduction_motif VARCHAR(255) AFTER reduction_valeur;
ALTER TABLE llx_ecole_eleve ADD COLUMN frais_inscription_du DOUBLE(24,8) AFTER reduction_motif;
ALTER TABLE llx_ecole_eleve ADD COLUMN numero_appel INTEGER AFTER fk_classe;
