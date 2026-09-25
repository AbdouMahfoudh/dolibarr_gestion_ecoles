-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
ALTER TABLE llx_ecole_classe ADD COLUMN frais_inscription DOUBLE(24,8) NOT NULL DEFAULT 0 AFTER mensualite;
-- Effectif maximum : 100 par défaut (les classes sans valeur passent à 100).
UPDATE llx_ecole_classe SET effectif_max = 100 WHERE effectif_max IS NULL OR effectif_max <= 0;
ALTER TABLE llx_ecole_classe MODIFY effectif_max INTEGER NOT NULL DEFAULT 100;
