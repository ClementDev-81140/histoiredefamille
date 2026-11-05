# Captures d'écran

Elles servent de référence visuelle pour le README et pour comparer deux
versions. Elles sont **engendrées** : aucune n'est retouchée à la main.

| Fichier | Page | Largeur |
|---|---|---|
| `ordinateur-accueil.png` / `mobile-accueil.png` | page publique `/` (deux portes : Consulter, Se connecter) | 1280 / 390 |
| `ordinateur-accueil-dans-la-famille.png` / `mobile-accueil-dans-la-famille.png` | la même page `/` vue par un lecteur : plus de porte, et le bloc « Dans la famille » (anniversaires, événements, personnes, nouveautés) | 1280 / 390 |
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

Comment elles sont faites : la base de démonstration (`php app/cli/demo.php`)
est chargée dans une base d'essai, les pages sont rendues par le routeur du
site lui-même (même code que la production), puis photographiées avec un
navigateur en 1280 et 390 px de large pour les pages publiques, et en **1265**
pour l'administration (1265 = 1280 moins la barre de défilement : les captures
d'administration gardent ainsi la même largeur d'une version à l'autre). Les
images servies par Apache sont intégrées pour que le rendu soit complet hors
serveur. Aucune donnée réelle : tout le contenu est marqué « (démo) ».
