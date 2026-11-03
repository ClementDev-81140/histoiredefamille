# Matrice d'accès

> **Document engendré automatiquement.** Ne pas modifier à la main : il est
> réécrit par `php app/cli/tests.php matrice`. Il décrit ce que le site
> répond réellement, route par route et profil par profil.

Date de génération : 03/11/2026 à 23:40

## Profils testés

| Profil | Qui c'est |
|---|---|
| Visiteur | personne non connectée |
| Branche A | compte de la branche A (ne voit pas la branche B) |
| Personne de la branche B | compte « personne » rattaché à la branche B |
| Voit toutes les branches | compte « personne » coché « voit toutes les branches » |
| Administrateur | compte administrateur actif |

## Codes attendus

- **200** : la page s'affiche ;
- **303** : renvoi vers la page de connexion (ou vers l'accueil) ;
- **403** : accès refusé (compte connecté mais sans le droit, ou jeton CSRF absent) ;
- **404** : introuvable — c'est aussi la réponse pour un contenu que l'on n'a pas le droit de voir (aucune fuite d'existence) ;
- **405** : méthode refusée (une action POST appelée en GET).

## Résultat

- Routes lues dans `app/routes.php` : **142** ;
- Vérifications (route × profil) : **710** ;
- Anomalies : **0** ;
- Contrôles ciblés : **217**, dont **0** en échec.

## Points examinés

| Point | Décision |
|---|---|
| `GET` et `POST /admin/mon-compte` | ouverts à tout compte connecté : c'est la page « Mon compte » (chacun modifie **son** mot de passe), pas une page d'administration. Le menu d'administration n'est affiché qu'aux administrateurs. |
| `GET /admin/comptes/{id}` | renvoie vers « Branches & comptes » ou « Administrateurs » pour un compte de branche : la fiche détaillée n'existe que pour les comptes « personne ». |
| `GET /famille`, `/tchat`, `/admin` pour un visiteur | **303** : renvoi vers `/connexion` (avec le chemin de retour), jamais la page elle-même. |
| Contenu non autorisé | **404** identique à une page qui n'existe pas : l'existence d'un document privé n'est pas révélée. |
| `POST` sans jeton CSRF | **403**, y compris pour un administrateur. |

## Anomalies constatées

Aucune anomalie : pour chaque route, le code obtenu correspond au code attendu.

## Contrôles ciblés

| Contrôle | Attendu | Obtenu |
|---|---|---|
| fichier public — /f/51 — Visiteur (non connecté) | 200 | 200 |
| fichier public — /f/51/telecharger — Visiteur (non connecté) | 200 | 200 |
| fichier public — /media/miniature/51 — Visiteur (non connecté) | 200 | 200 |
| fichier public — /f/51 — Branche A | 200 | 200 |
| fichier public — /f/51/telecharger — Branche A | 200 | 200 |
| fichier public — /media/miniature/51 — Branche A | 200 | 200 |
| fichier public — /f/51 — Personne de la branche B | 200 | 200 |
| fichier public — /f/51/telecharger — Personne de la branche B | 200 | 200 |
| fichier public — /media/miniature/51 — Personne de la branche B | 200 | 200 |
| fichier public — /f/51 — Voit toutes les branches | 200 | 200 |
| fichier public — /f/51/telecharger — Voit toutes les branches | 200 | 200 |
| fichier public — /media/miniature/51 — Voit toutes les branches | 200 | 200 |
| fichier public — /f/51 — Administrateur | 200 | 200 |
| fichier public — /f/51/telecharger — Administrateur | 200 | 200 |
| fichier public — /media/miniature/51 — Administrateur | 200 | 200 |
| fichier famille — /f/52 — Visiteur (non connecté) | 404 | 404 |
| fichier famille — /f/52/telecharger — Visiteur (non connecté) | 404 | 404 |
| fichier famille — /media/miniature/52 — Visiteur (non connecté) | 404 | 404 |
| fichier famille — /f/52 — Branche A | 200 | 200 |
| fichier famille — /f/52/telecharger — Branche A | 200 | 200 |
| fichier famille — /media/miniature/52 — Branche A | 200 | 200 |
| fichier famille — /f/52 — Personne de la branche B | 200 | 200 |
| fichier famille — /f/52/telecharger — Personne de la branche B | 200 | 200 |
| fichier famille — /media/miniature/52 — Personne de la branche B | 200 | 200 |
| fichier famille — /f/52 — Voit toutes les branches | 200 | 200 |
| fichier famille — /f/52/telecharger — Voit toutes les branches | 200 | 200 |
| fichier famille — /media/miniature/52 — Voit toutes les branches | 200 | 200 |
| fichier famille — /f/52 — Administrateur | 200 | 200 |
| fichier famille — /f/52/telecharger — Administrateur | 200 | 200 |
| fichier famille — /media/miniature/52 — Administrateur | 200 | 200 |
| fichier branche A — /f/53 — Visiteur (non connecté) | 404 | 404 |
| fichier branche A — /f/53/telecharger — Visiteur (non connecté) | 404 | 404 |
| fichier branche A — /media/miniature/53 — Visiteur (non connecté) | 404 | 404 |
| fichier branche A — /f/53 — Branche A | 200 | 200 |
| fichier branche A — /f/53/telecharger — Branche A | 200 | 200 |
| fichier branche A — /media/miniature/53 — Branche A | 200 | 200 |
| fichier branche A — /f/53 — Personne de la branche B | 404 | 404 |
| fichier branche A — /f/53/telecharger — Personne de la branche B | 404 | 404 |
| fichier branche A — /media/miniature/53 — Personne de la branche B | 404 | 404 |
| fichier branche A — /f/53 — Voit toutes les branches | 200 | 200 |
| fichier branche A — /f/53/telecharger — Voit toutes les branches | 200 | 200 |
| fichier branche A — /media/miniature/53 — Voit toutes les branches | 200 | 200 |
| fichier branche A — /f/53 — Administrateur | 200 | 200 |
| fichier branche A — /f/53/telecharger — Administrateur | 200 | 200 |
| fichier branche A — /media/miniature/53 — Administrateur | 200 | 200 |
| fichier branche B — /f/54 — Visiteur (non connecté) | 404 | 404 |
| fichier branche B — /f/54/telecharger — Visiteur (non connecté) | 404 | 404 |
| fichier branche B — /media/miniature/54 — Visiteur (non connecté) | 404 | 404 |
| fichier branche B — /f/54 — Branche A | 404 | 404 |
| fichier branche B — /f/54/telecharger — Branche A | 404 | 404 |
| fichier branche B — /media/miniature/54 — Branche A | 404 | 404 |
| fichier branche B — /f/54 — Personne de la branche B | 200 | 200 |
| fichier branche B — /f/54/telecharger — Personne de la branche B | 200 | 200 |
| fichier branche B — /media/miniature/54 — Personne de la branche B | 200 | 200 |
| fichier branche B — /f/54 — Voit toutes les branches | 200 | 200 |
| fichier branche B — /f/54/telecharger — Voit toutes les branches | 200 | 200 |
| fichier branche B — /media/miniature/54 — Voit toutes les branches | 200 | 200 |
| fichier branche B — /f/54 — Administrateur | 200 | 200 |
| fichier branche B — /f/54/telecharger — Administrateur | 200 | 200 |
| fichier branche B — /media/miniature/54 — Administrateur | 200 | 200 |
| fichier privé — /f/55 — Visiteur (non connecté) | 404 | 404 |
| fichier privé — /f/55/telecharger — Visiteur (non connecté) | 404 | 404 |
| fichier privé — /media/miniature/55 — Visiteur (non connecté) | 404 | 404 |
| fichier privé — /f/55 — Branche A | 404 | 404 |
| fichier privé — /f/55/telecharger — Branche A | 404 | 404 |
| fichier privé — /media/miniature/55 — Branche A | 404 | 404 |
| fichier privé — /f/55 — Personne de la branche B | 404 | 404 |
| fichier privé — /f/55/telecharger — Personne de la branche B | 404 | 404 |
| fichier privé — /media/miniature/55 — Personne de la branche B | 404 | 404 |
| fichier privé — /f/55 — Voit toutes les branches | 404 | 404 |
| fichier privé — /f/55/telecharger — Voit toutes les branches | 404 | 404 |
| fichier privé — /media/miniature/55 — Voit toutes les branches | 404 | 404 |
| fichier privé — /f/55 — Administrateur | 200 | 200 |
| fichier privé — /f/55/telecharger — Administrateur | 200 | 200 |
| fichier privé — /media/miniature/55 — Administrateur | 200 | 200 |
| vidéo publique — lecture complète (visiteur) | 200 | 200 |
| vidéo publique — lecture par morceaux (Range) | 206 | 206 |
| vidéo publique — plage invalide refusée | 416 | 416 |
| audio public — lecture complète (visiteur) | 200 | 200 |
| audio public — lecture par morceaux (Range) | 206 | 206 |
| audio public — plage invalide refusée | 416 | 416 |
| vidéo privée — refusée au visiteur | 404 | 404 |
| export GEDCOM — visiteur renvoyé vers la connexion | 303 | 303 |
| export GEDCOM — compte de branche refusé | 403 | 403 |
| export GEDCOM — administrateur autorisé | 200 | 200 |
| recherche — visiteur : aucun contenu privé | aucun secret | aucun secret |
| recherche — administrateur : le contenu privé est trouvé | trouvé | trouvé |
| page privée — introuvable pour un visiteur | 404 | 404 |
| page privée — visible par l’administrateur | 200 | 200 |
| calendrier — visiteur : aucun titre privé | aucun secret | aucun secret |
| album public — la photo privée n’est pas montrée au visiteur | aucun secret | aucun secret |
| album public — la photo publique est bien montrée | montrée | montrée |
| tchat / espace famille — /tchat — visiteur renvoyé vers la connexion | 303 | 303 |
| tchat / espace famille — /tchat/messages.json — visiteur renvoyé vers la connexion | 303 | 303 |
| tchat / espace famille — /famille — visiteur renvoyé vers la connexion | 303 | 303 |
| tchat / espace famille — /mon-compte — visiteur renvoyé vers la connexion | 303 | 303 |
| POST /tchat/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /tchat/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/courriels/reglages — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/courriels/reglages — compte de branche | 403 | 403 |
| POST /admin/courriels/essai — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/courriels/essai — compte de branche | 403 | 403 |
| POST /admin/connexions/conservation — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/connexions/conservation — compte de branche | 403 | 403 |
| POST /admin/alertes/acquitter — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/alertes/acquitter — compte de branche | 403 | 403 |
| POST /admin/reglages — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/reglages — compte de branche | 403 | 403 |
| POST /admin/apparence — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/apparence — compte de branche | 403 | 403 |
| POST /admin/composition — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/composition — compte de branche | 403 | 403 |
| POST /admin/composition/reglages — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/composition/reglages — compte de branche | 403 | 403 |
| POST /admin/composition/retablir — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/composition/retablir — compte de branche | 403 | 403 |
| POST /admin/documents/nouveau — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/documents/nouveau — compte de branche | 403 | 403 |
| POST /admin/documents/{id}/modifier — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/documents/{id}/modifier — compte de branche | 403 | 403 |
| POST /admin/documents/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/documents/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/documents/{id}/personnes/ajouter — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/documents/{id}/personnes/ajouter — compte de branche | 403 | 403 |
| POST /admin/documents/{id}/personnes/retirer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/documents/{id}/personnes/retirer — compte de branche | 403 | 403 |
| POST /admin/albums/nouveau — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/albums/nouveau — compte de branche | 403 | 403 |
| POST /admin/albums/{id}/modifier — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/albums/{id}/modifier — compte de branche | 403 | 403 |
| POST /admin/albums/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/albums/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/articles/nouveau — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/articles/nouveau — compte de branche | 403 | 403 |
| POST /admin/articles/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/articles/{id} — compte de branche | 403 | 403 |
| POST /admin/articles/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/articles/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/videos/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/videos/nouvelle — compte de branche | 403 | 403 |
| POST /admin/videos/importer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/videos/importer — compte de branche | 403 | 403 |
| POST /admin/videos/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/videos/{id} — compte de branche | 403 | 403 |
| POST /admin/videos/{id}/mise-en-avant — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/videos/{id}/mise-en-avant — compte de branche | 403 | 403 |
| POST /admin/videos/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/videos/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/recettes/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/recettes/nouvelle — compte de branche | 403 | 403 |
| POST /admin/recettes/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/recettes/{id} — compte de branche | 403 | 403 |
| POST /admin/recettes/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/recettes/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/categories/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/categories/nouvelle — compte de branche | 403 | 403 |
| POST /admin/categories/{id}/modifier — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/categories/{id}/modifier — compte de branche | 403 | 403 |
| POST /admin/categories/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/categories/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/comptes/apercu — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/apercu — compte de branche | 403 | 403 |
| POST /admin/comptes/creer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/creer — compte de branche | 403 | 403 |
| POST /admin/comptes/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/{id} — compte de branche | 403 | 403 |
| POST /admin/comptes/{id}/mot-de-passe — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/{id}/mot-de-passe — compte de branche | 403 | 403 |
| POST /admin/comptes/{id}/activer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/{id}/activer — compte de branche | 403 | 403 |
| POST /admin/comptes/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/comptes/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/branches/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/branches/nouvelle — compte de branche | 403 | 403 |
| POST /admin/branches/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/branches/{id} — compte de branche | 403 | 403 |
| POST /admin/branches/{id}/mot-de-passe — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/branches/{id}/mot-de-passe — compte de branche | 403 | 403 |
| POST /admin/branches/{id}/activer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/branches/{id}/activer — compte de branche | 403 | 403 |
| POST /admin/branches/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/branches/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/admins/nouveau — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/admins/nouveau — compte de branche | 403 | 403 |
| POST /admin/admins/{id}/mot-de-passe — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/admins/{id}/mot-de-passe — compte de branche | 403 | 403 |
| POST /admin/admins/{id}/activer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/admins/{id}/activer — compte de branche | 403 | 403 |
| POST /admin/pages/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/pages/nouvelle — compte de branche | 403 | 403 |
| POST /admin/pages/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/pages/{id} — compte de branche | 403 | 403 |
| POST /admin/pages/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/pages/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/arbre/personne/nouvelle — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/personne/nouvelle — compte de branche | 403 | 403 |
| POST /admin/arbre/personne/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/personne/{id} — compte de branche | 403 | 403 |
| POST /admin/arbre/personne/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/personne/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/arbre/couple/nouveau — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/couple/nouveau — compte de branche | 403 | 403 |
| POST /admin/arbre/couple/{id} — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/couple/{id} — compte de branche | 403 | 403 |
| POST /admin/arbre/couple/{id}/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/couple/{id}/supprimer — compte de branche | 403 | 403 |
| POST /admin/arbre/import — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/import — compte de branche | 403 | 403 |
| POST /admin/arbre/import/confirmer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/arbre/import/confirmer — compte de branche | 403 | 403 |
| POST /admin/importer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/importer — compte de branche | 403 | 403 |
| POST /admin/importer/supprimer — sans jeton CSRF (administrateur) | 403 | 403 |
| POST /admin/importer/supprimer — compte de branche | 403 | 403 |
| theme.css — feuille de style servie au visiteur | 200 | 200 |
| theme.css — aucun contenu d’administration | aucune fuite | aucune fuite |
| maintenance — page d’attente sans contenu privé | aucune fuite | aucune fuite |

## Tableau complet

| Méthode | Route | Visiteur (non connecté) | Branche A | Personne de la branche B | Voit toutes les branches | Administrateur | Famille |
|---|---|---|---|---|---|---|---|
| GET | `/theme.css` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/articles` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/articles/{slug}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/documents` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/documents/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/albums` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/albums/{slug}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/calendrier` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/frise` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/recettes` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/recettes/{slug}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/temoignages` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/pages/{slug}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/recherche` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/arbre` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/arbre/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/api/arbre/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/videos` | 200 | 200 | 200 | 200 | 200 | public |
| GET | `/videos/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/f/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/f/{id}/telecharger` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/media/miniature/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/video/{id}` | 200 | 200 | 200 | 200 | 200 | contenu |
| GET | `/connexion` | 200 | 303 | 303 | 303 | 303 | connexion |
| POST | `/connexion` | 403 | 403 | 403 | 403 | 403 | connexion |
| POST | `/deconnexion` | 403 | 403 | 403 | 403 | 403 | connexion |
| GET | `/famille` | 303 | 200 | 200 | 200 | 200 | famille |
| GET | `/mon-compte` | 303 | 200 | 200 | 200 | 200 | famille |
| POST | `/mon-compte/courriels` | 303 | 403 | 403 | 403 | 403 | famille |
| GET | `/desinscription` | 404 | 404 | 404 | 404 | 404 | desinscription |
| POST | `/desinscription` | 403 | 403 | 403 | 403 | 403 | desinscription |
| POST | `/mon-compte` | 303 | 403 | 403 | 403 | 403 | famille |
| GET | `/tchat` | 303 | 200 | 200 | 200 | 200 | famille |
| GET | `/tchat/messages.json` | 303 | 200 | 200 | 200 | 200 | famille |
| POST | `/tchat/envoyer` | 303 | 403 | 403 | 403 | 403 | famille |
| POST | `/tchat/prenom` | 303 | 403 | 403 | 403 | 403 | famille |
| POST | `/tchat/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/courriels` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/courriels/reglages` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/courriels/essai` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/etat` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/connexions` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/connexions/conservation` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/alertes/acquitter` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/reglages` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/reglages` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/apparence` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/apparence` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/composition` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/composition` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/composition/reglages` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/composition/retablir` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/mon-compte` | 303 | 200 | 200 | 200 | 200 | famille |
| POST | `/admin/mon-compte` | 303 | 403 | 403 | 403 | 403 | famille |
| GET | `/admin/documents` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/documents/nouveau` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/documents/nouveau` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/documents/{id}/modifier` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/documents/{id}/modifier` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/documents/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/documents/{id}/personnes` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/documents/{id}/personnes/ajouter` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/documents/{id}/personnes/retirer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/albums` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/albums/nouveau` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/albums/nouveau` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/albums/{id}/modifier` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/albums/{id}/modifier` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/albums/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/articles` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/articles/nouveau` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/articles/nouveau` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/articles/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/articles/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/articles/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/videos` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/videos/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/videos/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/videos/importer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/videos/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/videos/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/videos/{id}/mise-en-avant` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/videos/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/recettes` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/recettes/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/recettes/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/recettes/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/recettes/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/recettes/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/export/gedcom` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/categories` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/categories/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/categories/{id}/modifier` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/categories/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/comptes` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/comptes/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/comptes/apercu` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/comptes/creer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/comptes/resultat/{slug}` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/comptes/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/comptes/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/comptes/{id}/mot-de-passe` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/comptes/{id}/activer` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/comptes/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/branches` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/branches/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/branches/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/branches/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/branches/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/branches/{id}/mot-de-passe` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/branches/{id}/activer` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/branches/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/admins` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/admins/nouveau` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/admins/{id}/mot-de-passe` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/admins/{id}/activer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/pages` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/pages/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/pages/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/pages/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/pages/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/pages/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/arbre` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/arbre/personne/nouvelle` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/arbre/personne/nouvelle` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/arbre/personne/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/arbre/personne/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/arbre/personne/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/arbre/couples` | 303 | 403 | 403 | 403 | 200 | administration |
| GET | `/admin/arbre/couple/nouveau` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/arbre/couple/nouveau` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/arbre/couple/{id}` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/arbre/couple/{id}` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/arbre/couple/{id}/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/arbre/import` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/arbre/import` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/arbre/import/confirmer` | 303 | 403 | 403 | 403 | 403 | administration |
| GET | `/admin/importer` | 303 | 403 | 403 | 403 | 200 | administration |
| POST | `/admin/importer` | 303 | 403 | 403 | 403 | 403 | administration |
| POST | `/admin/importer/supprimer` | 303 | 403 | 403 | 403 | 403 | administration |
