# Chasse aux doublons (1.38.0)

## Le contrôle automatique

Deux contrôles vérifient qu'aucune page n'affiche deux fois le même bloc,
et ils sont faits pour être relancés après chaque changement d'interface.

- **Navigateur** : `tools/navigateur/rendre-tout.php` rend, avec les données
  fictives de la matrice d'accès (transaction annulée, aucun fichier servi),
  toutes les routes GET qui répondent 200 pour cinq profils : visiteur,
  lecteur, journaliste « avec validation », journaliste « sans validation »
  (créé pour ce contrôle) et administrateur.
  `tools/navigateur/actions-doublons.mjs <dossier-des-rendus>` ouvre ensuite
  chaque page à 1280 px et 390 px, en thème papier et en thème bleu
  (`HDF_THEME_CSS` pointe vers une feuille `/theme.css` générée), et signale,
  parmi les éléments **réellement affichés** : plus d'un `<h1>`, un titre
  (h1/h2/h3) répété, deux liens ou boutons de même libellé et même
  destination dans le même bloc, un lien du menu principal repris dans une
  navigation secondaire du contenu (hors fil d'Ariane), une phrase d'aide ou
  d'avertissement de plus de 40 caractères affichée deux fois (y compris
  reprise dans un `title` ou un `aria-label`), un `id` HTML dupliqué et un
  bloc de texte entier rendu deux fois.
- **PHP** : `php app/cli/tests.php doublons` rend cinq pages clés (accueil
  visiteur, accueil connecté, tableau de bord, page d'article, formulaire
  d'envoi d'une proposition) et échoue si un avertissement, un titre, un
  identifiant ou un bouton d'envoi y paraît deux fois.

Les doublons **volontaires** sont listés et justifiés dans
`tools/navigateur/doublons-liste-blanche.json` — jamais dans le code des
pages. Le contrôle échoue sur tout doublon hors liste blanche.

## Rapport avant correction (relevé du contrôle, 227 pages × 4 affichages)

| Page | Profil(s) | Élément répété | Verdict |
|---|---|---|---|
| Tableau de bord `/admin` | administrateur | Avertissement « aucune sauvegarde » : bandeau de la mise en page d'administration + encadré propre à la page (et variantes « a échoué » / « date de… jours ») | **Supprimé** : les encadrés de la page sont retirés ; le bandeau reste (présent sur toutes les pages de l'administration, avec le lien « Vérifier l'état du site »). Les alertes d'espace disque de la page sont conservées : seuil et objet différents, pas de doublon sur la page. |
| Accueil connecté `/` | lecteur, journalistes | Sections « Anniversaires », « Personnes ajoutées », « Depuis votre dernière visite » du bloc « Dans la famille » écrites deux fois dans le HTML (version `<details>` téléphone + version bloc bureau) | **Fusionné** : un seul `<details>` sert les deux largeurs. Sur téléphone, fermé, son `<summary>` tient lieu de titre ; sur grand écran, la feuille de style masque le `<summary>`, montre l'en-tête (titre + « Tout voir ») et force l'affichage du contenu. Une seule version est donc affichée — c'était un vrai doublon HTML, pas un doublon visible. |
| Liste `/articles`, arbre `/arbre` | tous | Lien « Tout / Toutes » de la barre de filtres, même destination que le menu principal | **Conservé** (liste blanche) : c'est un filtre qui se réinitialise, pas un second menu ; sans lui, un filtre actif ne pourrait pas s'effacer. |
| Calendrier `/calendrier` | journalistes | « Frise chronologique » : bouton en haut de page + lien dans la phrase du panneau « aucun événement ce mois-ci » | **Conservé** (liste blanche) : le lien fait partie d'une phrase d'explication lue sur place ; le bouton reste l'action, atteignable au clavier et sur téléphone. |
| Composition `/admin/composition` | administrateur | Titre de colonne « Sous la une, sur toute la largeur » sous deux `<h2>` différents | **Conservé** (liste blanche) : la page règle séparément l'accueil public et l'accueil connecté ; même nom de colonne, deux réglages indépendants. |
| Partout | tous | Boutons « Modifier / Supprimer » ligne à ligne, noms de personnes liés depuis plusieurs événements (frise, fiches) | **Faux positifs du premier relevé** : chaque ligne désigne son propre élément (destination ou contexte différent). Le contrôle a été précisé (destination d'un bouton = action de son formulaire et ses champs cachés ; chaque ligne de tableau ou de liste est un bloc distinct ; le fil d'Ariane a sa propre zone) plutôt que de toucher au site. |
| Partout | tous | `id` dupliqué, double `<h1>`, phrase d'aide affichée deux fois, bloc entier affiché deux fois (versions bureau + téléphone) | **Aucun cas trouvé** après correction. |

Après correction : **0 doublon hors liste blanche** (56 occurrences tolérées,
toutes dans les trois cas « conservé » ci-dessus), essai PHP `doublons`
16/16, matrice d'accès sans anomalie, suite complète relancée.

## Captures

`docs/captures/navigateur-1380-accueil-famille-{bureau,telephone}{,-bleu}.png` :
l'accueil connecté après fusion, 1280 px et 390 px, thèmes papier et bleu.
Attention : le Chromium du banc d'essai n'applique pas le masquage natif
d'un `<details>` fermé (vérifié sur une page minimale) ; sur téléphone, le
bloc paraît donc ouvert sur la capture, alors qu'un vrai navigateur
l'affiche fermé, réduit à son titre « Dans la famille ».
