# Droits des journalistes (1.37.0)

Les journalistes sont les comptes personnels (`account_type = 'personne'`).
Depuis la 1.37.0, un administrateur règle **par journaliste**, dans la fiche
du compte (Administration → Comptes) :

- **Contrôle** : « avec validation » (défaut) ou « sans validation » ;
- **Périmètre** : « toutes les branches » (défaut) ou une sélection de
  branches cochées.

Les valeurs par défaut sont exactement le comportement d'avant la 1.37.0 :
tout journaliste existant reste « avec validation » et « toutes les
branches » tant qu'un administrateur n'a rien changé (migration 024, en
ajout seul). Les changements sont immédiats, sans reconnexion.

## Ce qui ne change pas

- **La lecture** : `visibility_clause()` et `can_view()` sont inchangés.
  Un journaliste lit avec les droits de sa branche de compte, comme un
  lecteur. Le périmètre ci-dessous ne règle que l'**écriture**.
- **Les zones interdites** : comptes, rôles, journaux de connexion, état de
  l'installation, sauvegardes, conversions, réglages, apparence, branches,
  validation des propositions des autres : administration seulement,
  403/404 pour un journaliste, y compris « sans validation » et
  « toutes les branches ».
- **Aucune suppression** par un journaliste : ni contenu, ni personne, ni
  dossier, ni album, ni commentaire. Il peut seulement retirer une
  proposition à lui encore en attente.
- Un journaliste ne voit ni ne modifie les réglages d'un autre compte, ni
  les siens (fiche de compte : administrateurs seulement).

## La règle « ce contenu relève d'une branche »

Définie une seule fois dans `app/lib/journalistes.php`
(`contenu_releve_branches()`), appliquée partout de la même façon :

1. **Le rattachement explicite** : les branches cochées pour le contenu
   dans `content_branches` (c'est le rattachement qui sert déjà à la
   visibilité « Branches précises » et aux listes d'éditions). S'il existe,
   c'est lui qui compte.
2. **À défaut, la branche de l'auteur** : `articles.author_id`,
   `documents.created_by`, et `created_by` pour les recettes, les événements
   et les albums. Si l'auteur est rattaché à une branche, le contenu relève
   de cette branche. (Être le déposant — `submitted_by` — rend le contenu
   « sien », mais ne le rattache pas à une branche.)
3. **Les fiches personnes n'ont pas d'auteur** : seul le rattachement
   explicite compte (une personne « Branches précises » est rattachée à ses
   branches). Une personne sans rattachement ne relève d'aucune branche en
   particulier : seul un journaliste « toutes les branches » peut la
   corriger.

Un journaliste peut modifier : ses propres contenus (auteur ou déposant =
lui), et tout contenu qui relève d'une branche de son périmètre. Avec
« toutes les branches », il intervient sur tout le contenu du site, sauf
l'administration.

## Avec / sans validation

- **Avec validation** : comportement historique. Ses ajouts naissent privés
  et « en attente », invisibles de tous (sauf lui et les administrateurs) ;
  la modification d'un contenu publié crée une version en attente, le texte
  publié ne bouge pas. Un administrateur valide (la visibilité proposée
  s'applique) ou refuse.
- **Sans validation** : ses ajouts sont publiés directement, avec la
  visibilité qu'il choisit (y compris « public »). La modification d'un
  contenu (le sien ou celui d'un autre, dans son périmètre) s'applique
  directement. Chaque action est :
  - **journalisée** dans `activity_log` (actions `journaliste_*`), visible
    des seuls administrateurs dans Administration → « Journal des
    journalistes », d'où un administrateur peut **dépublier** (remettre en
    privé) en un clic ;
  - **tracée** dans `contenu_historique` : la version précédente des
    champs modifiés (JSON), pour ne jamais perdre l'ancien texte.

## Risques et limites assumées

- Un journaliste « sans validation » peut publier en **public** : c'est le
  réglage demandé, signalé dans sa fiche par l'avertissement « Ce
  journaliste publie directement, y compris en public. »
- Le périmètre porte sur la branche de rattachement ou d'auteur, pas sur
  la visibilité : un contenu « famille » sans rattachement ni auteur connu
  ne relève que du périmètre « toutes les branches ».
- Les événements et les albums créés par un journaliste passent par les
  écrans publics prévus (`/calendrier/ajouter`, `/albums/nouveau`) ; les
  écrans d'administration restent fermés.
- Les fiches personnes n'ont pas de file d'attente : leur correction par un
  journaliste est donc directe même « avec validation », mais toujours
  tracée dans `contenu_historique` (et journalisée si « sans validation »).
  C'est le seul endroit où « avec validation » ne passe pas par une
  validation d'administrateur, faute de mécanisme existant.
- Un contenu que le journaliste ne peut pas **voir** (par exemple « privé »
  ou « Branches précises » hors de sa branche de compte) ne peut pas être
  corrigé par adresse directe : le formulaire répond 404, rien ne fuit.
  Le périmètre règle l'écriture, jamais la lecture.
