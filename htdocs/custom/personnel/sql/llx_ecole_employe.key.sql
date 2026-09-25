-- Mise à jour des installations existantes (erreur « colonne déjà existante » ignorée par l'installeur Dolibarr).
-- saisie_notes : 1 = l'enseignant peut saisir les notes dans son espace, 0 = bloqué par la direction
-- modif_profil : NULL = réglage général, 1 = peut modifier sa photo et son dossier, 0 = ne peut pas
-- envoi_whatsapp : 1 = le surveillant a les boutons WhatsApp « Prévenir les parents » dans son espace, 0 = bloqué
ALTER TABLE llx_ecole_employe ADD COLUMN saisie_notes SMALLINT DEFAULT 1 AFTER observations;
ALTER TABLE llx_ecole_employe ADD COLUMN modif_profil SMALLINT AFTER saisie_notes;
ALTER TABLE llx_ecole_employe ADD COLUMN envoi_whatsapp SMALLINT DEFAULT 1 AFTER modif_profil;
-- Règles de paie propres à l'employé (NULL = règle générale de la configuration) :
-- regle_retenue : aucune | prorata ; regle_retard : aucun | minutes | seuil ; regle_hsup : 1 payées / 0 non ;
-- regle_remplacement : taux | forfait | aucun
ALTER TABLE llx_ecole_employe ADD COLUMN regle_retenue VARCHAR(8) AFTER envoi_whatsapp;
ALTER TABLE llx_ecole_employe ADD COLUMN regle_retard VARCHAR(8) AFTER regle_retenue;
ALTER TABLE llx_ecole_employe ADD COLUMN regle_hsup SMALLINT AFTER regle_retard;
ALTER TABLE llx_ecole_employe ADD COLUMN regle_remplacement VARCHAR(8) AFTER regle_hsup;
