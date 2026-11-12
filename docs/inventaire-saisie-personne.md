# Inventaire préalable : saisie d'une fiche personne

État relevé sur le socle 1.40.0. Le parcours « tout-en-un » n'est pas
encore livré. Une section séparée sur la fiche permet maintenant de proposer
un lien à la fois, avec aperçu puis confirmation, sans modifier le formulaire
existant. La création de personnes sur place reste à faire.

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

Le nouveau parcours crée toujours une famille distincte : un parent ou un
conjoint n'est jamais ajouté à une famille préexistante. Pour une fratrie, il
faut une seule famille de parents connue et accessible ; ses parents sont
montrés dans l'aperçu et une nouvelle famille les relie aux deux enfants.
Les situations ambiguës sont refusées. La confirmation vérifie à nouveau les
droits et les cycles, dans une transaction ; elle est journalisée. L'ancien
formulaire reste inchangé et ne bénéficie pas de ces nouveaux contrôles.

Restent notamment la création privée sur place, l'historique des liens et les
contrôles de dates. La migration 026 ajoute l'indicateur « à compléter » et le
filtre administrateur ; une sauvegarde est nécessaire avant son application.
