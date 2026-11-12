# Inventaire préalable : saisie d'une fiche personne

État relevé sur le socle 1.40.0. Ce document est un inventaire ; le parcours
« tout-en-un » et l'aperçu de confirmation ne sont pas encore livrés.

## Personne

La table `persons` contient : identifiant, identifiant d'origine GEDCOM,
prénom(s), nom, sexe, date et année de naissance, lieu de naissance, date
et année de décès, lieu de décès, notes, référence au document de portrait
`photo_document_id`, visibilité et dates de création et de modification.
Les années sont extraites des dates lors de l'enregistrement. Les branches
sont rattachées séparément par `content_branches`.

Il n'existe pas de colonne distincte de nom de naissance ni de profession.
Il ne faut pas présenter les notes comme si elles étaient ces champs ni
inventer leur contenu.

## Liens

Une ligne de `families` possède deux références de conjoints/parents,
une date de mariage, un lieu de mariage, des notes, un identifiant GEDCOM
et ses dates de création et modification. `family_children` relie une
personne enfant à une famille. Les parents et la fratrie sont déduits de ces
liens : un frère ou une sœur partage au moins une famille parente. Une
personne peut être reliée à plusieurs familles dans les données existantes ;
il faudra traiter les cas ambigus sans effacer de liens à l'insu du lecteur.

## Formulaires et autorisations existants

- L'administration modifie les champs de la personne, sa photo et sa
  visibilité. Les couples et leurs enfants ont un formulaire distinct.
- Le formulaire journaliste ajoute actuellement un parent, un conjoint ou
  un enfant à la fois. Il ne propose pas encore d'aperçu préalable.
- Les journalistes « avec validation » reçoivent 403 pour les fiches
  personnes ; les journalistes « sans validation » sont limités à leur
  périmètre pour modifier une fiche. Une fiche hors périmètre répond 404.
- Pour la nouvelle saisie, les suggestions doivent croiser la visibilité et
  le périmètre, et les choix doivent être revérifiés lors de la confirmation.
  Un contrôle de cycle doit considérer aussi les liens invisibles sans en
  révéler les personnes ; il doit tenir compte de tous les liens proposés
  ensemble, pas seulement de ceux déjà présents en base.

## Travail restant

Concevoir et implémenter le formulaire, l'aperçu sans écriture, la
confirmation atomique et son historique, les contrôles des liens et des dates,
la création privée « à compléter » et sa migration en ajout seul, le filtre
administrateur, l'intégration à l'arbre et tous les tests de parcours et de
droits. Une sauvegarde est nécessaire avant l'application de la migration.
