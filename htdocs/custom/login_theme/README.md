# Module Login Theme (custom/login_theme)

Module Dolibarr custom pour l'**habillage visuel du cadre d'authentification** de la page de connexion (`core/tpl/login.tpl.php`).

## Rôle de ce dossier

Fournit une surcharge CSS chargée par le core (voir le lien statique déjà ajouté dans `core/tpl/login.tpl.php` juste après l'ouverture de `<body>`) qui restyle uniquement le cadre `.login_table` (logo, champs identifiant / mot de passe, bouton de connexion, liens "mot de passe oublié" / aide).

Aucun fichier core n'est modifié en dehors du lien `<link>` déjà présent. Ce module n'est qu'une feuille de style statique : pas de PHP, pas de base de données, pas de menu.

| Fichier | Rôle |
|---------|------|
| `login_custom.css` | Surcharge visuelle du cadre de login (carte "verre dépoli", champs arrondis, bouton dégradé, liens, messages d'erreur) |

## Ce que le style change

- Le cadre `.login_table` devient une carte "verre dépoli" (fond semi-transparent + flou, coins arrondis, ombre douce, légère animation d'apparition).
- Les champs identifiant / mot de passe sont arrondis avec un effet de focus (halo bleu).
- Le bouton "Connexion" devient un bouton dégradé avec effet de survol.
- Les liens (mot de passe oublié, aide) et les messages d'erreur/avertissement sont restylés pour rester lisibles sur la carte.

## Ce que le style NE change PAS

- Le fond de page (`body.bodylogin`) et le conteneur `.login_center` ne sont **jamais** touchés : l'image de fond configurée dans Dolibarr (paramètre `MAIN_LOGIN_BACKGROUND`, ou dégradé par défaut du thème) reste intacte et visible autour du cadre.

## Pour personnaliser

Les couleurs principales sont centralisées en haut du fichier CSS via les variables `--lc-accent`, `--lc-accent-dark`, `--lc-radius`, `--lc-text` : il suffit de changer ces valeurs pour adapter le style à une nouvelle charte graphique.

## Cache navigateur

Le lien vers ce fichier est versionné (`login_custom.css?v=1`). Après une modification, incrémenter ce paramètre `v=` dans `core/tpl/login.tpl.php` pour forcer le rechargement côté navigateurs.

le lien a ajouter dans la ligne 223
<!-- AJOUT LOGIN CUSTOM : habillage personnalisé de la page de connexion (Rabii) -->
<!-- Fichier statique, ne modifie aucun autre style de l'application -->
<link rel="stylesheet" type="text/css" href="<?php echo DOL_URL_ROOT; ?>/custom/login_theme/login_custom.css?v=1" />
<!-- FIN AJOUT LOGIN CUSTOM (lien) -->