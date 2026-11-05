# Captures d'écran

Elles servent de référence visuelle pour le README et pour comparer deux
versions. Elles sont **engendrées** : aucune n'est retouchée à la main.

| Fichier | Page | Largeur |
|---|---|---|
| `ordinateur-accueil.png` / `mobile-accueil.png` | page publique `/` (deux portes : Consulter, Se connecter) | 1280 / 390 |
| `ordinateur-accueil-connecte.png` / `mobile-accueil-connecte.png` | accueil d'un lecteur (`/famille`) : nouveautés de la famille, une, encarts | 1280 / 390 |
| `ordinateur-accueil-journaliste.png` | accueil d'un journaliste : « Proposer un article » + « Mes propositions » | 1280 |
| `ordinateur-mes-propositions.png` / `mobile-mes-propositions.png` | `/propositions` | 1280 / 390 |
| `ordinateur-a-valider.png` / `mobile-a-valider.png` | `/admin/a-valider` (file des propositions) | 1280 / 390 |
| `ordinateur-article-nouveau.png` | formulaire « Nouvel article », visibilité « Privé » cochée | 1280 |
| `ordinateur-documents.png` | `/documents` | 1280 |

Les autres fichiers (albums, articles, arbre, calendrier, frise, tchat, vidéos,
recettes, administration) datent des versions précédentes : les pages
correspondantes n'ont pas changé depuis.

Comment elles sont faites : la base de démonstration (`php app/cli/demo.php`)
est chargée dans une base d'essai, les pages sont rendues par le routeur du
site lui-même (même code que la production), puis photographiées avec un
navigateur en 1280 et 390 px de large. Les images servies par Apache sont
intégrées pour que le rendu soit complet hors serveur. Aucune donnée réelle :
tout le contenu est marqué « (démo) ».
