/**
 * Histoire de famille — JavaScript léger (aucune dépendance).
 *
 * 1. Confirmation avant les actions destructrices.
 * 2. Affichage du bloc « branches » quand la visibilité « branches » est choisie.
 * 3. Filtrage rapide des longues listes de personnes.
 * 4. Explorateur d'arbre généalogique (chargement JSON à la demande).
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------------
    // 1. Confirmation des actions destructrices
    // ---------------------------------------------------------------------
    document.querySelectorAll('form[data-confirmation]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirmation');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // ---------------------------------------------------------------------
    // 2. Bloc des branches selon la visibilité choisie
    // ---------------------------------------------------------------------
    document.querySelectorAll('form').forEach(function (form) {
        var bloc = form.querySelector('[data-bloc-branches]');
        if (!bloc) {
            return;
        }
        var radios = form.querySelectorAll('input[name="visibility"]');

        function actualiser() {
            var choix = form.querySelector('input[name="visibility"]:checked');
            var valeur = choix ? choix.value : '';
            bloc.hidden = valeur !== 'branches';
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', actualiser);
        });
        actualiser();
    });

    // ---------------------------------------------------------------------
    // 3. Filtrage d'une liste déroulante multiple
    // ---------------------------------------------------------------------
    document.querySelectorAll('[data-filtre-liste]').forEach(function (input) {
        var select = document.getElementById(input.getAttribute('data-filtre-liste'));
        if (!select) {
            return;
        }
        // On mémorise si l'option correspondait au filtre pour restaurer la sélection.
        input.addEventListener('input', function () {
            var terme = input.value.toLowerCase().trim();
            Array.prototype.forEach.call(select.options, function (option) {
                var texte = option.textContent.toLowerCase();
                option.hidden = terme !== '' && texte.indexOf(terme) === -1;
            });
        });
    });

    // ---------------------------------------------------------------------
    // 4. Explorateur d'arbre
    // ---------------------------------------------------------------------
    var explorateur = document.getElementById('explorateur-arbre');
    if (explorateur) {
        var racine = explorateur.getAttribute('data-racine');
        var contenu = document.getElementById('explorateur-contenu');
        var chargement = document.getElementById('explorateur-chargement');

        var libelleAscendant = function (niveau) {
            if (niveau === 0) { return 'Parents'; }
            if (niveau === 1) { return 'Grands-parents'; }
            if (niveau === 2) { return 'Arrière-grands-parents'; }
            return 'Génération ' + (niveau + 2);
        };

        var libelleDescendant = function (niveau) {
            if (niveau === 0) { return 'Enfants'; }
            if (niveau === 1) { return 'Petits-enfants'; }
            if (niveau === 2) { return 'Arrière-petits-enfants'; }
            return 'Génération -' + (niveau + 2);
        };

        var creerNoeud = function (personne, central) {
            var lien = document.createElement('a');
            lien.className = 'explorateur__noeud' + (central ? ' explorateur__noeud--central' : '');
            lien.href = personne.lien;

            var nom = document.createElement('span');
            nom.textContent = personne.nom;
            lien.appendChild(nom);

            if (personne.dates) {
                var dates = document.createElement('span');
                dates.className = 'explorateur__dates';
                dates.textContent = personne.dates;
                lien.appendChild(dates);
            }
            return lien;
        };

        var creerColonne = function (legende, personnes, central) {
            var colonne = document.createElement('div');
            colonne.className = 'explorateur__colonne';

            var titre = document.createElement('p');
            titre.className = 'explorateur__legende';
            titre.textContent = legende;
            colonne.appendChild(titre);

            personnes.forEach(function (personne) {
                colonne.appendChild(creerNoeud(personne, central));
            });
            return colonne;
        };

        fetch('/api/arbre/' + encodeURIComponent(racine) + '?ascendants=3&descendants=2', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (reponse) {
                if (!reponse.ok) {
                    throw new Error('Réponse ' + reponse.status);
                }
                return reponse.json();
            })
            .then(function (donnees) {
                if (chargement) {
                    chargement.remove();
                }

                var niveaux = document.createElement('div');
                niveaux.className = 'explorateur__niveaux';

                // Ascendants : de la génération la plus ancienne vers les parents.
                var ascendants = donnees.ascendants || {};
                var niveauxAsc = Object.keys(ascendants)
                    .map(Number)
                    .sort(function (a, b) { return a - b; });

                for (var i = niveauxAsc.length - 1; i >= 0; i--) {
                    var niveau = niveauxAsc[i];
                    var personnes = ascendants[niveau];
                    if (personnes && personnes.length > 0) {
                        niveaux.appendChild(creerColonne(libelleAscendant(niveau), personnes, false));
                    }
                }

                // Personne centrale + conjoints.
                var centrales = [donnees.personne].concat(donnees.conjoints || []);
                niveaux.appendChild(creerColonne('Personne', centrales, true));

                // Descendants, génération par génération.
                var descendants = donnees.descendants || {};
                Object.keys(descendants)
                    .map(Number)
                    .sort(function (a, b) { return a - b; })
                    .forEach(function (niveau) {
                        var liste = descendants[niveau];
                        if (liste && liste.length > 0) {
                            niveaux.appendChild(creerColonne(libelleDescendant(niveau), liste, false));
                        }
                    });

                contenu.appendChild(niveaux);

                if (niveauxAsc.length === 0) {
                    var note = document.createElement('p');
                    note.className = 'discret';
                    note.textContent = 'Aucun ascendant renseigné pour cette personne.';
                    contenu.appendChild(note);
                }
            })
            .catch(function () {
                if (chargement) {
                    chargement.textContent = 'Impossible de charger l’arbre. Utilisez les liens de la fiche.';
                }
            });
    }
})();
