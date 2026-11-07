# La carte des lieux

La page `/carte` montre sur une carte les lieux de naissance, de décès et de
mariage de la famille. Chaque point mène à une fiche qui liste les personnes
rattachées à ce lieu.

**Rien n'est demandé à Internet.** La carte ne fait aucun appel à un service
extérieur : ni fond de carte téléchargé, ni bibliothèque JavaScript, ni tuile
Google/OpenStreetMap, ni police. Le dessin est fabriqué **par le serveur**
(PHP) et envoyé au navigateur comme une simple image SVG.

## Pourquoi ce choix

Le site tourne sur un Raspberry Pi 3B+, sans dépendance à installer, et le
visiteur n'est jamais pisté. Une carte « classique » (Leaflet, OpenStreetMap,
Google) impose :

- une connexion à un serveur tiers à chaque affichage (donc des données
  personnelles transmises : adresse IP, pages vues) ;
- du JavaScript, alors que le site fonctionne sans ;
- un fond de carte soumis à des conditions d'utilisation variables.

Ici, le fond est un **fichier livré avec le site** et le calcul se fait en PHP.

## Ce que contient le dépôt

| Fichier | Rôle | Taille |
| --- | --- | --- |
| `app/data/carte_fond.php` | contours des terres, deux vues (France, monde) | 37 Ko |
| `app/data/communes-fr.tsv` | communes et quartiers de France, avec coordonnées | 278 Ko |
| `app/lib/carte.php` | projection, agrégation, cache, dessin SVG | code |
| `app/migrations/012_carte.sql` | table `places` (un lieu = un nom + des coordonnées) | migration |

Les deux fichiers de données sont **engendrés** : ils ne se modifient pas à la
main. Ensemble, ils pèsent moins de 320 Ko et sont lus une seule fois par
requête.

## Origine et licence du fond de carte

- **Source** : [Natural Earth](https://www.naturalearthdata.com/) 4.1.0,
  contours « land » au 1:110 000 000 et pays au 1:50 000 000.
- **Licence** : **domaine public**. Natural Earth est publié sans restriction
  d'usage (« no permission is needed to use Natural Earth »). Aucune mention
  obligatoire, aucune redevance ; la mention est conservée ici par respect du
  travail des auteurs.
- **Redistribution** : les contours ont été obtenus à partir des fichiers
  TopoJSON de Natural Earth (paquet [`world-atlas`](https://github.com/topojson/world-atlas),
  Michael Bostock, **licence ISC**), puis **projetés en équirectangulaire**,
  **coupés au méridien 180** et **simplifiés une fois pour toutes**, hors du
  site, par [`tools/carte-fond.py`](../tools/carte-fond.py). Le tracé final
  n'est donc pas le fichier d'origine : c'est une version allégée, dessinée
  pour un écran de 1 000 points de large. Le serveur ne fait aucune conversion
  à l'affichage : il recopie le tracé dans le SVG.
- **Refaire le fond** (rarement utile) : sur un ordinateur, `npm pack
  world-atlas@2`, `pip install shapely`, puis
  `python3 tools/carte-fond.py --source package`. Le script est documenté et ne
  sert qu'à cela ; le Raspberry Pi n'a besoin ni de Python ni de Node.

La projection utilisée est la même des deux côtés (fabrication du fond et
placement des points) : une simple équirectangulaire,

    x = (lon − lon_min) / (lon_max − lon_min) × largeur
    y = (lat_max − lat) / (lat_max − lat_min) × hauteur

définie pour chaque vue dans `app/data/carte_fond.php` :

| Vue | Longitudes | Latitudes | Dessin |
| --- | --- | --- | --- |
| `france` | −5,6 → 9,9 | 41,0 → 51,4 | 1 000 × 671 |
| `monde` | −180 → 180 | −58 → 84 | 1 000 × 394 |

Un point hors de la vue n'est pas dessiné (une personne née à Montréal
n'apparaît pas dans la vue France : elle apparaît dans la vue Monde).

## Origine de la liste des communes

`app/data/communes-fr.tsv` contient **8 942 lignes**, au format :

    nom<TAB>latitude<TAB>longitude<TAB>département

Exemple : `Toulouse	43.6043	1.4437	31`.

- **Contenu** : des noms de communes, de villes et de quartiers de France
  (métropole et outre-mer), avec le **centre** de la commune comme coordonnée
  (précision d'environ 100 m, largement suffisante pour une carte de famille).
- **Nature** : ce sont des **faits géographiques** — le nom d'une commune et la
  position de son centre ne sont pas une œuvre protégée. La liste est donc
  fournie avec le site **sans restriction d'usage**, comme le fond de carte.
  Elle ne contient **aucune donnée personnelle** : aucun habitant, aucune
  adresse, aucun code postal.
- **Rôle** : elle sert uniquement à **proposer** une commune quand
  l'administrateur place un lieu, dans **Administration → Lieux à placer**.
  Rien n'est deviné : c'est un choix parmi une liste, ou une saisie à la main.
- **Remplacer la liste** : le fichier est du texte brut, quatre colonnes
  séparées par des tabulations. On peut y substituer la base officielle des
  communes (INSEE, data.gouv.fr, Licence Ouverte 2.0) en gardant ce format ;
  aucun autre fichier n'est à modifier. Le test `carte : la liste des communes
  est embarquée` vérifie qu'il reste lisible.

## Comment un lieu entre sur la carte

1. Les fiches gardent leurs textes de lieu **tels qu'ils sont écrits**
   (`persons.birth_place`, `persons.death_place`,
   `families.marriage_place`). La carte ne réécrit jamais ces textes : c'est ce
   qui permet d'importer un GEDCOM sans rien perdre.
2. **Administration → Lieux à placer** liste les textes qui n'ont pas encore de
   coordonnées. On cherche la commune (deux lettres suffisent), on clique sur
   « Placer », c'est fini. Pour un hameau ou un lieu étranger, on saisit la
   latitude et la longitude à la main.
3. La correspondance entre un texte et un lieu placé ignore la casse, les
   accents et la ponctuation : « Saint-Étienne », « SAINT ETIENNE » et
   « saint-étienne » désignent le même point. Un texte de lieu **inconnu est
   simplement ignoré** sur la carte (la fiche, elle, ne change pas).
4. Retirer un lieu ne touche à aucune fiche : il disparaît de la carte et
   revient dans la liste « À placer ».

## Droits : on ne voit que ce qu'on a le droit de voir

La carte suit **exactement** la règle de visibilité du site
(`visibility_clause('person', …)`), sans exception.

Depuis la **1.31.0**, la carte n'est plus publique : `/carte` et `/lieu/{id}`
demandent la connexion et renvoient un visiteur vers `/connexion`, comme
`/arbre`. La règle de visibilité est donc celle des comptes connectés :

- un compte de famille voit le public, les contenus « toutes les familles » et
  ceux de sa branche ;
- un administrateur voit tout.

Si la carte redevenait publique un jour, la règle « visiteur = personnes
publiques seulement » est toujours dans le code (`visibility_clause()`), et le
cache a déjà son groupe de droits « visiteur ».

Conséquences :

- un lieu dont **aucune** personne n'est visible pour la personne qui regarde
  n'apparaît pas du tout ;
- sa fiche `/lieu/{id}` répond alors **404 avec exactement le même texte** que
  pour un lieu qui n'existe pas : on ne peut pas deviner son existence ;
- les noms de personnes ne figurent jamais dans le cache (voir plus bas) ; ils
  ne sont lus que pour la page demandée.

## Cache

Le calcul est mis en cache **5 minutes** dans `storage/cache/carte/`, un
fichier par :

- **groupe de droits** (`groupe_droits()` : visiteur, famille, branche N, tout) ;
- **filtres** (branche, décennie) ;
- **version des données** (date de dernière modification et nombre de
  personnes et de lieux) : dès qu'une fiche change, le cache est ignoré.

Le fichier ne contient que des noms de lieux publics et des **nombres** de
personnes. Un lieu ou un texte de fiche modifié vide le dossier
(`carte_vider_cache()`), et `?recalculer=1` force un recalcul.

Sur le Raspberry Pi, l'affichage reste sous les **300 ms** avec 2 000
personnes : le cache n'est lu qu'une fois, le dessin est une concaténation de
chaînes (aucune bibliothèque, aucune image à charger).

## Sécurité et accessibilité

- Le SVG ne contient **aucun attribut `style`**, aucun `<script>`, aucune
  image, aucune adresse `http(s)://`. Les couleurs viennent des **variables
  CSS** du site : la carte suit donc la palette choisie (papier ancien, bleu
  d'origine, personnalisée).
- Chaque point est un lien `<a>` avec un `<title>` : au survol (ou au toucher
  sur un téléphone) s'affiche « Toulouse — 12 personnes ». Les liens sont
  atteignables au clavier.
- Un **tableau** sous la carte donne exactement la même information : il sert
  aux lecteurs d'écran, à l'impression, et aux navigateurs qui n'affichent pas
  le SVG.
- Le formulaire de filtres est un simple `GET` : aucune JavaScript, l'adresse
  obtenue peut être mise en favori.

## Vérifier que tout va bien

```bash
cd ~/histoiredefamille
php app/cli/tests.php carte
```

Les tests de la carte vérifient la projection (bords, points hors cadre,
coordonnées impossibles), les suggestions de communes, les filtres, les droits
(un visiteur ne compte pas une personne privée), le cache, et le fait que le
SVG ne contient ni `style`, ni script, ni ressource extérieure.
