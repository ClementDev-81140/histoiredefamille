# L'arbre « chêne gravé » de la page `/arbre` (1.33.0)

La page `/arbre` montre l'arbre généalogique de la famille **comme un chêne
gravé** : les ancêtres dans les branches, la personne choisie (et son conjoint)
sur le tronc, les descendants dans les racines. Tout est écrit par le serveur,
en HTML et en CSS ; aucun cadre JavaScript n'est nécessaire pour l'afficher.

## Ce qui est affiché

| Partie | Contenu | Profondeur |
|---|---|---|
| Les ancêtres | parents, grands-parents, arrière-grands-parents | **3 générations** |
| Le tronc | la personne centrale, et son ou ses conjoints | — |
| Les descendants | enfants, petits-enfants | **2 générations** |

Le choix de la personne centrale se fait par le sélecteur en haut de page
(`GET /arbre?racine={id}`) ou par le lien « Arbre » de la liste des personnes.
Sans choix, la page prend **la personne visible la plus récemment modifiée qui a
de la famille autour d'elle** — une fiche isolée donnerait un arbre vide, ce qui
n'est pas une bonne première vue.

Au-delà de **huit personnes** dans une même génération, la page affiche
« Voir les N autres personnes de la famille » plutôt que de tasser les cartes
(`GET /arbre?racine={id}&plus=1`). Le « voir plus » ne compte que des personnes
visibles.

## Droits (inchangés)

- `/arbre` reste **réservé aux comptes connectés** (décision 1.31.0) : un
  visiteur est renvoyé vers `/connexion`, avec ou sans `?racine=`.
- Chaque personne passe par `filter_visible_persons()` (donc `can_view()` et
  `content_branch_ids()`) : une personne privée n'apparaît **ni en nom, ni en
  lien, ni en nombre**.
- Une personne invisible demandée en `?racine=` ne remplace pas la personne par
  défaut, et la page ne dit pas qu'elle existe : même réponse qu'une adresse
  sans choix.
- Le sélecteur ne propose que des personnes visibles.

## La gravure

Le dessin de fond est une **gravure de chêne** servie par le site :

- `public/assets/arbre-chene.webp` — 860 × 1 290, **environ 260 Ko** ;
- `public/assets/arbre-chene.jpg` — le **repli** pour les navigateurs sans WebP.

Sur **grand écran** (≥ 64 rem), la gravure est le fond du dessin, dans un cadre
aux proportions de l'image (2 pour 3) : elle le remplit exactement, sans être
coupée ni étirée. Sur **téléphone**, elle devient un **bandeau décoratif
au-dessus** de l'arbre, et les cartes s'empilent en colonne (ancêtres, tronc,
descendants). À l'impression, elle est retirée (encre) : il ne reste que les
cartes et leurs filets.

### Remplacer la gravure par la vôtre

Le fichier de départ n'est pas dans le dépôt (il pesait plus de 2 Mo). Pour
mettre une autre gravure, il suffit de la reconvertir avec les mêmes noms :

```bash
convert gravure-source.png -strip -resize 860x -quality 70 public/assets/arbre-chene.webp
convert gravure-source.png -strip -resize 860x -sampling-factor 4:2:0 -quality 68 \
        public/assets/arbre-chene.jpg
ls -l public/assets/arbre-chene.*      # viser 300 Ko au plus pour le WebP
```

Aucun code ne dépend du dessin : seule l'adresse des deux fichiers compte. Si
la nouvelle image n'a pas les proportions 2 / 3, ajuster `aspect-ratio` dans la
règle `.arbre-chene__dessin` (bloc « 1.33.0 » de `public/assets/style.css`).

> **Provenance.** La gravure livrée ici a été dessinée pour ce site : le dépôt
> de référence indiqué dans la demande
> (`ClementDev-81140/gazette-of-family-branches`) n'est pas accessible
> (réponse 404 depuis le 7 novembre 2026), l'image d'origine n'a donc pas pu
> être reprise telle quelle. Le dessin est dans le même esprit (chêne, hachures,
> papier ancien), il ne contient aucune donnée personnelle, et il se remplace en
> deux commandes (ci-dessus).

## Classes et dispositions

Toutes les positions viennent de **classes prédéfinies**, jamais d'un attribut
`style` :

- `.arbre-chene__partie--branches` / `--tronc` / `--racines` : les trois zones ;
- `.arbre-chene__rangee--1` à `--8` : la largeur des cartes selon leur nombre
  dans la rangée (1/1, 1/2, 1/3 … 1/8) ; c'est ce qui garantit l'absence de
  superposition ;
- `.arbre-chene__noeud` : une carte (papier opaque, filet, filet d'accent en
  haut), toujours un lien `<a href="/arbre/{id}">` — donc atteignable au
  clavier, avec un contour visible au focus ;
- `.arbre-chene__legende`, `.arbre-chene__compte` : les étiquettes de génération
  et leur compte, posées sur un morceau de papier pour rester lisibles sur les
  hachures de la gravure.

## Essais

```bash
# Essais PHP (famille « arbre ») : rendu, accès, droits, rangées, propreté.
php app/cli/tests.php arbre

# Contrôle « vrai navigateur » (Chromium), 38 contrôles par largeur et palette.
CAPTURES=/tmp node tools/navigateur/actions-arbre-chene.mjs 1280
CAPTURES=/tmp node tools/navigateur/actions-arbre-chene.mjs 390
```

Les captures de `docs/captures/` (`ordinateur-arbre-*`, `mobile-arbre-*`)
viennent de ces deux commandes, sur deux jeux d'essai : **un enfant** et
**huit enfants**.
