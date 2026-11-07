# Captures d'écran

Elles servent de référence visuelle pour le README et pour comparer deux
versions. Elles sont **engendrées** : aucune n'est retouchée à la main.

| Fichier | Page | Largeur |
|---|---|---|
| `ordinateur-accueil.png` / `mobile-accueil.png` | page publique `/` (deux portes : Consulter, Se connecter, et « Nous écrire ») | 1280 / 390 |
| `ordinateur-accueil-bleu.png` / `mobile-accueil-bleu.png` | la même page d'accueil en palette **bleu** | 1280 / 390 |
| `ordinateur-connexion.png` / `mobile-connexion.png` | `/connexion` : « Mot de passe oublié ou besoin d'un compte ? » et l'adresse d'assistance | 1280 / 390 |
| `mobile-connexion-bleu.png` | la même page en palette **bleu** | 390 |
| `ordinateur-mon-compte.png` / `mobile-mon-compte.png` | « Mon compte » d'un administrateur : « Besoin d'aide ? » et l'adresse d'assistance, **sans** section « Mes courriels » (envoi coupé) | 1280 / 390 |
| `mobile-mon-compte-bleu.png` | la même page en palette **bleu** | 390 |
| `ordinateur-accueil-dans-la-famille.png` / `mobile-accueil-dans-la-famille.png` | la même page `/` vue par un lecteur (1.29.0) : la une d'abord, puis « Dans la famille » — colonne à droite en 1280, replié sous la une en 390 | 1280 / 390 |
| `ordinateur-accueil-connecte.png` / `mobile-accueil-connecte.png` | accueil d'un lecteur (`/famille`) : nouveautés de la famille, une, encarts | 1280 / 390 |
| `ordinateur-accueil-journaliste.png` / `mobile-accueil-journaliste.png` | accueil d'un journaliste : « Proposer un article » + « Mes propositions » | 1280 / 390 |
| `ordinateur-mes-propositions.png` / `mobile-mes-propositions.png` | `/propositions` | 1280 / 390 |
| `ordinateur-a-valider.png` / `mobile-a-valider.png` | `/admin/a-valider` (file des propositions) | 1280 / 390 |
| `ordinateur-article-nouveau.png` | formulaire « Nouvel article », visibilité « Privé » cochée | 1280 |
| `ordinateur-documents.png` | `/documents` | 1280 |

### Administration (1.25.2) — refaites en 1265 px, palette bleu d'origine

Le menu de gauche ayant changé pour **toutes** les pages d'administration, les
captures qui le montrent ont été refaites, avec la palette **bleu d'origine**
— celle des captures d'administration existantes — quelle que soit la palette
choisie sur le site :

| Fichier | Page |
|---|---|
| `ordinateur-admin-tableau.png` | tableau de bord : une carte « Contenus », « dont publics : N » |
| `ordinateur-admin-articles.png` / `ordinateur-admin-documents.png` / `ordinateur-admin-videos.png` | listes avec le filtre « Visibilité » |
| `ordinateur-admin-album.png` / `ordinateur-admin-article-fiche.png` / `ordinateur-admin-recette.png` | fiches et formulaires |
| `ordinateur-admin-video-fiche.png` / `ordinateur-admin-video-formulaire.png` | fiche et formulaire vidéo |
| `ordinateur-admin-personnes-photo.png` | fiche de personne |
| `ordinateur-videos-admin.png` | ancienne page « Vidéos » de l'administration |
| `ordinateur-reglages-tchat.png` / `ordinateur-tchat-admin.png` | réglages et tchat vus par un administrateur |

Les autres fichiers (albums publics, articles, arbre, calendrier, frise, tchat,
vidéos, recettes) datent des versions précédentes : les pages correspondantes
n'ont ni menu d'administration ni changement depuis.

### 1.29.0 — la une dans le premier écran

L'accueil d'un compte connecté a changé d'ordre : la une passe devant le bloc
« Dans la famille ». Les captures `-avant` montrent la page telle qu'elle était
en 1.28.1 (phrase d'accueil, chiffres, « Nous écrire », puis « Dans la
famille », et la une **hors de l'écran**) ; les `-apres` la même page en 1.29.0.
Toutes sont prises **fenêtre de 800 px de haut**, sans défilement : c'est le
« premier écran » dont parle la règle.

| Fichier | Ce que l'on y voit |
|---|---|
| `ordinateur-accueil-une-avant.png` / `ordinateur-accueil-une-apres.png` | 1280 × 800, palette papier : le titre de la une commence à 1026 px avant, à 439 px après (photo entièrement visible) |
| `mobile-accueil-une-avant.png` / `mobile-accueil-une-apres.png` | 390 × 800, palette papier : le titre commence à 1616 px avant, à 647 px après, et tient entièrement dans l'écran |
| `ordinateur-accueil-une-avant-bleu.png` / `ordinateur-accueil-une-apres-bleu.png` | les mêmes, palette **bleu d'origine** |
| `mobile-accueil-une-avant-bleu.png` / `mobile-accueil-une-apres-bleu.png` | les mêmes, palette **bleu d'origine** |
| `ordinateur-accueil-dans-la-famille.png` / `mobile-accueil-dans-la-famille.png` | refaites : page entière, nouvel ordre |

Elles sont engendrées par `tools/navigateur/capture.mjs` et mesurées par
`tools/navigateur/essai-une.mjs` (voir `tools/navigateur/README.md`), avec la
base de démonstration et un **compte lecteur** (compte 4, Élise).

### 1.28.0 — adresses affichées, écrans de courriel masqués

La 1.28.0 est visible sur **toutes** les pages (une ligne « Une question ? »
avec l'adresse de contact dans le pied de page), sur l'accueil (« Nous écrire :
… »), la connexion, « Mon compte », les pages d'erreur, la gazette et les
réglages. Les captures refaites :

| Fichier | Ce que l'on y voit |
|---|---|
| `ordinateur-accueil.png`, `mobile-accueil.png` (+ versions `-bleu`) | « Nous écrire : contact@… » sous les deux portes, et la ligne du pied de page |
| `ordinateur-connexion.png`, `mobile-connexion.png`, `mobile-connexion-bleu.png` | « Mot de passe oublié ou besoin d'un compte ? Écrivez à support@… » |
| `ordinateur-mon-compte.png`, `mobile-mon-compte.png`, `mobile-mon-compte-bleu.png` | « Besoin d'aide ? Écrivez à support@… », et **aucune** section « Mes courriels » : ni champ d'adresse, ni case de consentement |
| `ordinateur-reglages-tchat.png` (bleu, 1265 px) | le nouveau bloc **Adresses affichées** (contact, assistance) et la case **Courriels**, décochée |

Elles sont faites comme les autres : base de démonstration chargée dans une base
d'essai, pages rendues par le routeur du site, images converties en `data:` pour
que le rendu soit complet hors serveur, puis photographiées avec un navigateur
en 1280 px, 1265 px (administration) et 390 px, palette **papier** et **bleu**.
`mobile-connexion.png` est passée de 375 à **390 px**, la largeur de toutes les
autres captures de téléphone.

Le contrôle automatique des mêmes pages (`tools/navigateur/actions-contacts.mjs`) :
**14 contrôles × 4** (390 et 1280 px, papier et bleu), **0 échec** — liens
`mailto:` ordinaires (ni `onclick`, ni style en ligne), adresse qui tient dans la
largeur, contraste 6,98 (papier) et 6,30 (bleu), aucun débordement horizontal,
aucune erreur JavaScript.

### 1.26.0 — aucune capture refaite

La version 1.26.0 ne change **aucune page visible** : elle corrige des
comportements (conversion, versions de propositions, purge de la démonstration,
seuils de la vérification, espace disque). Les captures restent donc celles de
la 1.25.2. Seule la page **Administration → État du site** gagne un tableau
(« À regarder, sans bloquer l'ouverture ») : elle n'était pas capturée jusqu'ici
et ne l'est pas davantage ici.

Vérifié malgré tout, en 390 px, sur quatorze pages (accueil, documents, espace
famille, tableau de bord, état, documents d'administration, propositions,
à valider, carte, arbre, calendrier, conversions, comptes, réglages) : **aucun
défilement horizontal de page**, et chaque élément qui dépasse son cadre est
**dans un cadre qui défile** (`.tableau-defilant`), jamais nu.

Comment elles sont faites : la base de démonstration (`php app/cli/demo.php`)
est chargée dans une base d'essai, les pages sont rendues par le routeur du
site lui-même (même code que la production), puis photographiées avec un
navigateur en 1280 et 390 px de large pour les pages publiques, et en **1265**
pour l'administration (1265 = 1280 moins la barre de défilement : les captures
d'administration gardent ainsi la même largeur d'une version à l'autre). Les
images servies par Apache sont intégrées pour que le rendu soit complet hors
serveur. Aucune donnée réelle : tout le contenu est marqué « (démo) ».

Les trois outils sont dans `tools/navigateur/`, qui explique la marche à suivre :
`rendre.php` (la page par le routeur), `inline-medias.php` (les images en
`data:`), `serveur.mjs` (le petit serveur) et `capture.mjs` (la photo).
