/**
 * Histoire de famille — JavaScript léger (aucune dépendance).
 *
 * 1. Confirmation avant les actions destructrices.
 * 2. Affichage du bloc « branches » quand la visibilité « branches » est choisie.
 * 3. Filtrage rapide des longues listes de personnes.
 * 4. Explorateur d'arbre généalogique (chargement JSON à la demande).
 * 5. Message clair quand une vidéo ne peut pas être lue (limite de lectures).
 * 6. Tchat de la famille : interrogation périodique et envoi des messages.
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


    // ---------------------------------------------------------------------
    // 5. Vidéo illisible : message d'attente au lieu d'une erreur technique
    // ---------------------------------------------------------------------
    // Le lecteur déclenche « error » quand le serveur refuse le flux (trop de
    // lectures simultanées) ou quand le format n'est pas lisible. On affiche
    // alors un texte compréhensible, sans exposer de détail technique.
    document.querySelectorAll('video[data-video-erreur]').forEach(function (video) {
        var bloc = document.getElementById(video.getAttribute('data-video-erreur'));
        if (!bloc) {
            return;
        }
        video.addEventListener('error', function () {
            bloc.hidden = false;
        });
    });
    // ---------------------------------------------------------------------
    // 6. Tchat de la famille
    // ---------------------------------------------------------------------
    // Interrogation périodique (toutes les 5 s), qui ne demande QUE les
    // messages plus récents que le dernier affiché. Elle se ralentit à 30 s
    // après 3 minutes sans nouveauté, s'arrête quand l'onglet est caché et
    // quand on quitte la page. Envoi par fetch, avec le jeton CSRF du
    // formulaire. Aucun script n'est inséré par cette page : le texte des
    // messages est échappé côté serveur.
    var tchat = document.getElementById('tchat');
    if (tchat) {
        var MESSAGE_URL = '/tchat/messages.json';
        var DELAI_VIF = 5000;      // rythme normal
        var DELAI_CALME = 30000;   // après 3 minutes sans nouveauté
        var SEUIL_CALME = 36;      // 36 × 5 s = 3 minutes

        var liste = document.getElementById('tchat-messages');
        var etat = document.getElementById('tchat-etat');
        var vide = document.getElementById('tchat-vide');
        var boutonAnciens = document.getElementById('tchat-plus-anciens');
        var boutonNouveaux = document.getElementById('tchat-nouveaux');
        var formulaire = document.getElementById('tchat-envoi');
        var zone = document.querySelector('[data-tchat-texte]');
        var compteur = document.getElementById('tchat-compteur');
        var erreur = document.getElementById('tchat-erreur');
        var boutonEnvoi = formulaire ? formulaire.querySelector('button[type="submit"]') : null;

        var dernier = parseInt(tchat.getAttribute('data-apres'), 10) || 0;
        var premier = parseInt(tchat.getAttribute('data-premier'), 10) || 0;
        var jourDernier = tchat.getAttribute('data-jour') || '';
        var jourPremier = jourDernier;
        var resteAnciens = tchat.getAttribute('data-reste-avant') === '1';
        var silences = 0;
        var minuterie = null;
        var arrete = false;

        var dire = function (texte) {
            if (etat) {
                etat.textContent = texte;
            }
        };

        var auBas = function () {
            if (!liste) {
                return true;
            }
            return (liste.scrollHeight - liste.scrollTop - liste.clientHeight) < 80;
        };

        var versLeBas = function () {
            if (liste) {
                liste.scrollTop = liste.scrollHeight;
            }
        };

        var masquerVide = function () {
            if (vide && vide.parentNode) {
                vide.parentNode.removeChild(vide);
                vide = null;
            }
        };

        // ---- interrogation -------------------------------------------------
        var programmer = function (delai) {
            if (arrete || document.hidden) {
                return;
            }
            if (minuterie) {
                window.clearTimeout(minuterie);
            }
            minuterie = window.setTimeout(interroger, delai);
        };

        var interroger = function () {
            if (arrete || document.hidden) {
                return;
            }
            if (!navigator.onLine) {
                programmer(DELAI_CALME);
                return;
            }

            fetch(MESSAGE_URL + '?apres=' + encodeURIComponent(dernier), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (reponse) {
                    if (reponse.status === 403) {
                        arrete = true;
                        dire('Le tchat a été fermé par les administrateurs.');
                        return null;
                    }
                    if (!reponse.ok) {
                        throw new Error('Réponse ' + reponse.status);
                    }
                    return reponse.json();
                })
                .then(function (donnees) {
                    if (!donnees) {
                        return;
                    }
                    if (donnees.prenom_requis) {
                        arrete = true;
                        dire('Choisissez d’abord votre prénom pour discuter.');
                        return;
                    }
                    if (donnees.ok && donnees.nombre > 0) {
                        var collé = auBas();
                        masquerVide();
                        liste.insertAdjacentHTML('beforeend', donnees.html);
                        dernier = donnees.dernier;
                        jourDernier = donnees.jour || jourDernier;
                        tchat.setAttribute('data-apres', String(dernier));
                        tchat.setAttribute('data-jour', jourDernier);
                        silences = 0;
                        if (collé) {
                            versLeBas();
                            if (boutonNouveaux) {
                                boutonNouveaux.hidden = true;
                            }
                        } else if (boutonNouveaux) {
                            boutonNouveaux.hidden = false;
                        }
                        dire('');
                    } else {
                        silences = silences + 1;
                    }
                })
                .catch(function () {
                    dire('Connexion interrompue, nouvelle tentative dans un instant…');
                })
                .then(function () {
                    programmer(silences >= SEUIL_CALME ? DELAI_CALME : DELAI_VIF);
                });
        };

        // ---- messages plus anciens ----------------------------------------
        if (boutonAnciens) {
            var lienAnciens = boutonAnciens.querySelector('[data-voir-anciens]');
            if (lienAnciens) {
                lienAnciens.addEventListener('click', function () {
                    if (premier <= 0) {
                        return;
                    }
                    lienAnciens.disabled = true;
                    var hauteurAvant = liste.scrollHeight;

                    fetch(MESSAGE_URL + '?avant=' + encodeURIComponent(premier)
                        + '&jour=' + encodeURIComponent(jourPremier), {
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
                            if (!donnees.ok || donnees.nombre === 0) {
                                boutonAnciens.hidden = true;
                                return;
                            }
                            masquerVide();
                            liste.insertAdjacentHTML('afterbegin', donnees.html);
                            premier = donnees.premier;
                            jourPremier = donnees.jour || jourPremier;
                            tchat.setAttribute('data-premier', String(premier));
                            resteAnciens = donnees.resteAvant === true;
                            boutonAnciens.hidden = !resteAnciens;
                            // On garde le repère de lecture : la vue ne saute pas.
                            liste.scrollTop = liste.scrollHeight - hauteurAvant;
                        })
                        .catch(function () {
                            dire('Impossible de charger les messages plus anciens.');
                        })
                        .then(function () {
                            lienAnciens.disabled = false;
                        });
                });
            }
        }

        if (boutonNouveaux) {
            var lienBas = boutonNouveaux.querySelector('[data-vers-bas]');
            if (lienBas) {
                lienBas.addEventListener('click', function () {
                    versLeBas();
                    boutonNouveaux.hidden = true;
                });
            }
        }

        // ---- envoi ---------------------------------------------------------
        var actualiserCompteur = function () {
            if (compteur && zone) {
                compteur.textContent = zone.value.length + ' / 1000 caractères';
            }
        };

        if (zone) {
            zone.addEventListener('input', actualiserCompteur);
            // Entrée seule = envoyer ; Maj+Entrée = nouvelle ligne.
            zone.addEventListener('keydown', function (evenement) {
                if (evenement.key === 'Enter' && !evenement.shiftKey) {
                    evenement.preventDefault();
                    if (formulaire) {
                        formulaire.requestSubmit
                            ? formulaire.requestSubmit()
                            : formulaire.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                }
            });
            actualiserCompteur();
        }

        if (formulaire) {
            formulaire.addEventListener('submit', function (evenement) {
                if (!window.fetch || !window.FormData) {
                    return; // navigateur ancien : envoi normal, la page se recharge
                }
                evenement.preventDefault();

                if (erreur) {
                    erreur.textContent = '';
                }
                var texte = zone ? zone.value.trim() : '';
                if (texte === '') {
                    if (erreur) {
                        erreur.textContent = 'Votre message est vide.';
                    }
                    return;
                }
                if (texte.length > 1000) {
                    if (erreur) {
                        erreur.textContent = 'Votre message dépasse 1000 caractères.';
                    }
                    return;
                }
                if (boutonEnvoi) {
                    boutonEnvoi.disabled = true;
                }

                fetch(formulaire.getAttribute('action'), {
                    method: 'POST',
                    body: new FormData(formulaire),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'fetch'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (reponse) {
                        return reponse.json().catch(function () {
                            return { ok: false, erreur: 'La page a expiré, rechargez-la.' };
                        });
                    })
                    .then(function (donnees) {
                        if (!donnees.ok) {
                            if (erreur) {
                                erreur.textContent = donnees.erreur || 'Envoi impossible.';
                            }
                            return;
                        }
                        masquerVide();
                        liste.insertAdjacentHTML('beforeend', donnees.html);
                        dernier = donnees.dernier;
                        jourDernier = donnees.jour || jourDernier;
                        tchat.setAttribute('data-apres', String(dernier));
                        tchat.setAttribute('data-jour', jourDernier);
                        if (zone) {
                            zone.value = '';
                        }
                        actualiserCompteur();
                        versLeBas();
                        if (boutonNouveaux) {
                            boutonNouveaux.hidden = true;
                        }
                        silences = 0;
                        dire('Message envoyé.');
                        window.setTimeout(function () { dire(''); }, 3000);
                    })
                    .catch(function () {
                        if (erreur) {
                            erreur.textContent = 'Envoi impossible : vérifiez votre connexion.';
                        }
                    })
                    .then(function () {
                        if (boutonEnvoi) {
                            boutonEnvoi.disabled = false;
                        }
                        if (zone) {
                            zone.focus();
                        }
                    });
            });
        }

        // ---- mise en veille quand l'onglet est caché ------------------------
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (minuterie) {
                    window.clearTimeout(minuterie);
                    minuterie = null;
                }
            } else if (!arrete) {
                interroger();
            }
        });

        window.addEventListener('beforeunload', function () {
            arrete = true;
            if (minuterie) {
                window.clearTimeout(minuterie);
            }
        });

        versLeBas();
        programmer(DELAI_VIF);
    }
})();
