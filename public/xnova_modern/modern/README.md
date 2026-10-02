# Pack d'images de la skin `xnova_modern`

Jeu d'images **vectorielles** (SVG) écrites à la main, livrées avec la skin
`public/xnova_modern/` — copie allégée de `public/xnova/`.

## Pourquoi du SVG

La skin historique portait des fonds matriciels très lourds, dont aucun n'était
référencé :

| Fichier de `public/xnova/` | Poids | Sort |
|---|---|---|
| `img/background2.jpg` | 384 Ko | **retiré** (jamais référencé) |
| `img/background1.jpg` | 88 Ko | **retiré** (jamais référencé) |
| `img/bg1.gif` | 49 Ko | **retiré**, remplacé par un dégradé CSS (`td.c`) |

Le pack entier ci-dessous **tient en quelques kilo-octets**, sans perte de netteté :
un SVG se redessine à la taille demandée au lieu d'être agrandi.

## Contenu

| Fichier | Usage | Taille conseillée |
|---|---|---|
| `emblem.svg` | emblème du jeu (planète annelée), en-tête, page de connexion | 32 à 128 px |
| `banniere.svg` | bandeau large pour un en-tête de page | pleine largeur, ratio 5,6:1 |
| `fond-etoiles.svg` | fond d'espace **répétable sans couture** (motif) | pavage libre |
| `planete-rocheuse.svg` | portrait de planète | 128 px |
| `planete-gazeuse.svg` | portrait de planète (annelée) | 128 px |
| `planete-glace.svg` | portrait de planète | 128 px |
| `planete-volcanique.svg` | portrait de planète | 128 px |

## Utilisation

Le dossier `modern/` vit **dans** la skin : la feuille `formate.css` y accède par
un chemin relatif (`url(modern/fond-etoiles.svg)`), les gabarits par `{dpath}` —
qui vaut `/public/xnova_modern/` quand le compte a choisi cette skin
(`users.dpath`, champ « Skins » de la page Options).

```html
<!-- en-tête -->
<img src="{dpath}modern/emblem.svg" width="48" height="48" alt="{servername}">

<!-- bandeau -->
<img src="{dpath}modern/banniere.svg" class="img-fluid rounded" alt="">
```

Le fond étoilé, lui, est **déjà posé** par `formate.css` :

```css
body.xnova-body {
  background-image: url(modern/fond-etoiles.svg);
  background-repeat: repeat;
}
```

## Deux précautions

- **Les identifiants internes sont préfixés** (`xe-`, `xb-`, `xf-`, `xr-`, `xg-`,
  `xi-`, `xv-`) : un SVG inséré *en ligne* dans la page partage l'espace des
  identifiants, et deux `id` identiques feraient que le second dégradé écraserait
  le premier. Utilisés en `<img src>`, il n'y a aucun risque.
- **Aucune dépendance externe** : ni police, ni image bitmap, ni script. Les
  fichiers s'affichent seuls, hors ligne, et s'impriment.
