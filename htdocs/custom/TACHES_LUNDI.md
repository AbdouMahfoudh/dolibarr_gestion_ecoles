# Tâches programmées — lundi 2026-09-28

Réponses aux QCM du 2026-09-25/26. **Les 8 tâches sont codées (2026-09-26)**, voir « État » en fin de fichier.

## 1. Permissions dédiées de l'espace
- Attribution par MODÈLE selon la catégorie, puis ajustable compte par compte par la direction (onglet « Accès espace »).
- Permission retirée = la rubrique disparaît. Pas d'historique. Pas d'interrupteur global « suspendre ».
- Remplace `espace_rubriques_employe()` (catégorie ⇒ rubriques) et les interrupteurs `saisie_notes`, `envoi_whatsapp`, `modif_profil`.
- **Enseignant** : 1 permission par rubrique (cours du jour, EDT, notes, mes classes, examens) + notes : consulter ≠ saisir + cours : consulter ≠ déclarer.
- **Surveillant** : 1 permission par rubrique (appel, mes appels, examens) + corriger un appel déjà fait + envoi WhatsApp aux parents + voir les élèves signalés.
- **Tous les employés** (contrôlables) : heures et paie, mes salaires (bulletins PDF), mes absences, modifier mon dossier. L'accueil reste toujours visible.
- **Parents / élèves** : chaque rubrique contrôlée + actions sensibles séparées (voir / télécharger reçus, certificat, bulletins PDF).
  Modèle parent = tout ; modèle élève = notes, bulletins, EDT, absences (PAS paiements ni reçus).

## 2. Examens : une épreuve par matière
Une seule épreuve par matière, par classe et par session (trimestre). Doublon = enregistrement BLOQUÉ avec message
(dans `EcoleEdtExamen::validate()`).

## 3. Noms selon la langue — partout
Une SEULE langue partout, y compris les documents officiels (attestation, certificat, bulletin).
Repli sur l'autre langue si le nom manque. Corriger `ecole_label()` : en français, pas de repli vers l'arabe aujourd'hui.

## 4. Fiche matière
Enseignants qui l'enseignent + classes avec nombre de cours par semaine, calculés depuis `llx_ecole_edt_cours`.

## 5. Installation par défaut
- Installer : matières (21), séries C/D/A, créneaux S1-S4, classes (22), types d'autres frais (uniforme, fournitures,
  transport, cantine), mention « P.Tr 8 », réglages par défaut (mois payants oct→juin, jour limite 10, reçu A5,
  indicatif 222, seuils 10/5, jours ouvrables lun→sam).
- Modes de paiement Bankily, Masrvi, Sedad (+ un compte pour chacun) et un compte « Caisse espèces ».
- Jours fériés fixes : 1er janvier, 1er mai, 25 mai, 10 juillet, 28 novembre.
- NE PAS installer : salles (vide), coefficients, données de test, URL de l'espace.
- NE PAS toucher à la devise / pays / langue. PAS de groupes de droits Dolibarr automatiques.

## 6. Modèles de PDF multiples
- Modèles PARAMÉTRABLES créés/dupliqués dans l'interface (comme les bulletins de notes) pour listes, reçus, bulletins de paie :
  mise en page, couleurs, en-tête, colonnes, langue, filigrane, orientation, aperçu, modèle par défaut.
- En-tête par IMAGE complète, choix par type de document / modèle (pas d'image de pied de page, une seule image FR/AR).
- Filigrane (texte ou image, angle, transparence, taille, position, couleur) : réglage GLOBAL, remplaçable/désactivable par modèle.
  Regrouper les filigranes existants (bulletins `bulletin_pdf.lib.php:1223`, reçus `recu_pdf.lib.php:93`).
- Orientation : AUTO, portrait en priorité ; paysage seulement si ça ne tient pas ; option portrait/paysage/auto par modèle.

## 7. Attestation d'inscription
- Contenu : identité FR/AR (dans la langue du document, cf. tâche 3) + matricule, classe, année scolaire, date d'inscription,
  signature + cachet (image téléversable), frais d'inscription payés (ou « exonéré »).
- Bouton sur la fiche élève après validation de l'inscription, droit dédié, réimpression libre sans trace.
- Nouveau fichier distinct de `eleves/eleve/attestation.php` (qui est l'attestation de SOLDE).

## 8. Exonération des frais d'inscription
- Totale ou partielle (montant ou %), motif dans une liste configurable (réinscription, bourse, enfant du personnel, frère/sœur…),
  qui/quand enregistrés.
- Choix MANUEL (pas de détection automatique des réinscrits).
- Droit Dolibarr dédié « Exonérer les frais d'inscription ».
- Inscription validable sans paiement si exonération totale ; visible dans l'onglet Paiements et les rapports, pas compté comme impayé.

## État (2026-09-26) — tout est codé, à tester sur la vraie base

À faire une fois après la mise à jour du code : **désactiver puis réactiver** les modules Classes, Élèves, Notes,
Personnel et Espace (Accueil > Configuration > Modules). Cela crée les nouvelles tables et colonnes
(`llx_ecole_pdf_modele`, `llx_ecole_motif_exoneration`, `llx_ecole_espace_perm`, colonnes `exo_*` des élèves)
et installe les données par défaut. Sans cette réactivation, la fiche élève ne peut plus être enregistrée
(colonnes d'exonération absentes).

| # | Tâche | Où la voir |
|---|---|---|
| 1 | Permissions de l'espace | Fiche responsable / élève / employé > onglet « Accès espace » > Permissions de l'espace |
| 2 | Une épreuve par matière | Emploi du temps des examens d'une classe (message si doublon) |
| 3 | Noms dans une seule langue | Partout (listes, bannières, appel, notes, espaces, exports) |
| 4 | Fiche matière | Classes > Matières > une matière (classes, cours et heures / semaine, enseignants) |
| 5 | Installation par défaut | À l'activation des modules ; fichier SQL autonome : `donnees_par_defaut.sql` |
| 6 | Modèles de PDF, en-tête image, filigrane, portrait | Classes > Configuration > Modèles de PDF ; Classes > Configuration > Réglages |
| 7 | Attestation d'inscription | Fiche élève (inscription validée) > bouton « Attestation d'inscription » |
| 8 | Exonération des frais d'inscription | Fiche élève > onglet Paiements > « Exonérer des frais d'inscription » |

Nouveaux droits Dolibarr à donner : Élèves > « imprimer l'attestation d'inscription » (104706) et
« exonérer un élève des frais d'inscription » (104777).

Points à vérifier par l'école : horaires des créneaux S1-S4 (08:00-10:00, 10:15-12:15, 15:00-17:00, 17:00-19:00),
liste des 21 matières et 22 classes (liste standard, pas celle de la base de test), montants des autres frais (0).
Le mode « bilingue » des modèles de bulletin de notes est gardé (choix explicite de l'école dans le modèle).
