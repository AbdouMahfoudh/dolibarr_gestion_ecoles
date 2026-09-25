# `custom/lib/` — Classes UI réutilisables (tout le système)

Bibliothèque **transverse** : ces classes sont réutilisées par **tous** les modules
de la plateforme scolaire (établissement, élèves, enseignants, notes, …).
Ne pas dupliquer de HTML de bouton ou de select ailleurs — passer par ces classes.

| Fichier | Rôle |
|---|---|
| `ButtonAbdou.php` | Générateur de boutons homogènes. Types : `primary, secondary, success, danger, warning, info, light, dark, link, ghost`. Tailles : `small, medium, large`. API fluide + fabriques statiques. Rendu `<button>` ou `<a>` (si `setHref`). Classe CSS de sortie : `dolibarr-btn <type> <size>` (stylée dans `custom/css/<theme>/buttons.css`). |
| `SelectDolibarr.php` | Select stylé façon `selectarray` de Dolibarr (dropdown custom + recherche + multi-sélection + tags). Rend un `<input type="hidden" name="…">` submittable + le widget. Assets CSS/JS inline chargés une seule fois. |

## ButtonAbdou — exemples

```php
require_once DOL_DOCUMENT_ROOT.'/custom/lib/ButtonAbdou.php';

$b = ButtonAbdou::primary($langs->trans("Save"));
$b->setTypeAttr('submit')->setIcon('💾');
print $b;                       // __toString() => render()

$lien = ButtonAbdou::danger($langs->trans("Delete"));
$lien->setSize('small')
     ->setHref($url.'?action=confirm_delete&id='.$id.'&token='.newToken(), '_self')
     ->setOnclick("return confirm('".dol_escape_js($langs->trans("ConfirmDelete"))."')");
print $lien;

print ButtonAbdou::group([$b, $lien]);   // groupe .dolibarr-btn-group
```

Méthodes : `setType, setSize, setClass, setId, setName, setValue, setIcon($icon,$pos),
setDisabled, setLoading, setFullWidth, setOutline, setRounded, setHref($href,$target),
setOnclick, setTypeAttr('button'|'submit'|'reset'), setAttribute($k,$v)`.

## SelectDolibarr — exemples

```php
require_once DOL_DOCUMENT_ROOT.'/custom/lib/SelectDolibarr.php';

$s = new SelectDolibarr('langue_defaut');          // name (+ id optionnel)
$s->ajouterOption('fr', 'Français', true);         // (value, label, selected)
$s->ajouterOption('ar', 'العربية', false);
$s->setWidth('260px');
print $s;                                          // __toString() => render()

// depuis un tableau associatif
$s2 = new SelectDolibarr('devise');
$s2->ajouterOptions(['MRU'=>'Ouguiya','EUR'=>'Euro','USD'=>'Dollar']);
$s2->setSelected('MRU')->setSearchable(true)->setAllowClear(true);
print $s2;
```

Méthodes : `ajouterOption($v,$l,$sel,$group), ajouterOptions($assoc,$group),
ajouterGroupe($label,$assoc), setPlaceholder, setClass, setRequired, setDisabled,
setSelected, setMultiple($bool,$max), setWidth, setMaxHeight, setIcon,
setAllowClear, setSearchable`.

> La valeur envoyée au serveur est celle du `<input type="hidden">` → à lire
> normalement avec `GETPOST('langue_defaut', 'alphanohtml')`. En multi-sélection
> le name devient `langue_defaut[]`.
