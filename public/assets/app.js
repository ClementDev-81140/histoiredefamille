/**
 * Histoire de famille — JavaScript léger (aucune dépendance).
 *
 * 1. Confirmation avant les actions destructrices.
 * 2. Affichage du bloc « branches » quand la visibilité « branches » est choisie.
 * 3. Filtrage rapide des longues listes de personnes.
 * 4. Explorateur d'arbre généalogique (chargement JSON à la demande).
 * 5. Message clair quand une vidéo ne peut pas être lue (limite de lectures).
 * 6. Tchat de la famille : interrogation périodique et envoi des messages.
 * 7. Impression (boutons « Imprimer » des albums, fiches et recettes).
 * 8. Apparence du journal : aperçu vivant et contrastes calculés pendant la
 *    saisie (les couleurs de l'aperçu passent par l'API CSSOM, jamais par un
 *    attribut style ni un <style> en ligne : la CSP reste « style-src 'self' »).
 * 9. « Tout cocher » dans une liste à cases (1.30.0). Confort seulement : la
 *    même page propose un bouton qui fait le travail sans JavaScript.
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
    // ---------------------------------------------------------------------
    // 7. Impression (boutons « Imprimer » des albums, fiches et recettes)
    // ---------------------------------------------------------------------
    // Aucun script en ligne (CSP « self ») : un seul écouteur pour tous les
    // boutons portant l'attribut data-imprimer.
    document.addEventListener('click', function (event) {
        var bouton = event.target.closest ? event.target.closest('[data-imprimer]') : null;
        if (!bouton) {
            return;
        }
        event.preventDefault();
        window.print();
    });

    // ---------------------------------------------------------------------
    // 8. Copier un message d'accès (mots de passe provisoires)
    // ---------------------------------------------------------------------
    // Deux boutons : « Copier le message » (data-copier="#identifiant") et
    // « Tout copier » (data-copier-tout=".classe"). Aucun script en ligne :
    // l'API Clipboard quand elle existe, sinon sélection + execCommand.
    function texteDe(selecteur) {
        var cible = document.querySelector(selecteur);
        if (!cible) {
            return '';
        }
        // <pre> : textContent garde les retours à la ligne.
        return (cible.textContent || '').trim();
    }

    function copieDeSecours(texte) {
        var zone = document.createElement('textarea');
        zone.value = texte;
        zone.setAttribute('readonly', 'readonly');
        zone.className = 'copie-temporaire';
        document.body.appendChild(zone);
        zone.select();
        zone.setSelectionRange(0, texte.length);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (erreur) {
            ok = false;
        }
        document.body.removeChild(zone);
        return ok;
    }

    function annoncer(bouton, message) {
        var libelle = bouton.getAttribute('data-libelle') || bouton.textContent;
        bouton.setAttribute('data-libelle', libelle);
        bouton.textContent = message;
        window.setTimeout(function () {
            bouton.textContent = libelle;
        }, 2500);
    }

    function copier(texte, bouton) {
        if (texte === '') {
            annoncer(bouton, 'Rien à copier');
            return;
        }

        var reussi = function () {
            annoncer(bouton, 'Copié !');
        };
        var echoue = function () {
            annoncer(bouton, copieDeSecours(texte) ? 'Copié !' : 'Copie impossible');
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texte).then(reussi, echoue);
        } else {
            echoue();
        }
    }

    document.addEventListener('click', function (event) {
        var bouton = event.target.closest
            ? event.target.closest('[data-copier], [data-copier-tout]')
            : null;
        if (!bouton) {
            return;
        }

        var tout = bouton.getAttribute('data-copier-tout');
        if (tout) {
            var morceaux = [];
            document.querySelectorAll(tout).forEach(function (bloc) {
                var texte = (bloc.textContent || '').trim();
                if (texte !== '') {
                    morceaux.push(texte);
                }
            });
            copier(morceaux.join('\n\n'), bouton);
            return;
        }

        var cible = bouton.getAttribute('data-copier');
        if (cible) {
            copier(texteDe(cible), bouton);
        }
    });

    // ---------------------------------------------------------------------
    // 8. Apparence du journal : aperçu et contrastes en direct
    // ---------------------------------------------------------------------
    (function () {
        var formulaire = document.querySelector('form[data-apparence]');
        if (!formulaire) {
            return;
        }

        var apercu = document.getElementById('apercu-journal');
        if (!apercu) {
            return;
        }

        var numeroAuto = formulaire.getAttribute('data-numero-auto') || '';

        // --- Calculs de couleur (mêmes règles que app/lib/apparence.php) ---

        var composantes = function (hex) {
            var valeur = String(hex || '').trim().toLowerCase();
            if (/^#[0-9a-f]{3}$/.test(valeur)) {
                valeur = '#' + valeur[1] + valeur[1] + valeur[2] + valeur[2] + valeur[3] + valeur[3];
            }
            if (!/^#[0-9a-f]{6}$/.test(valeur)) {
                return null;
            }
            return [
                parseInt(valeur.substr(1, 2), 16),
                parseInt(valeur.substr(3, 2), 16),
                parseInt(valeur.substr(5, 2), 16)
            ];
        };

        var enHex = function (r, g, b) {
            var borne = function (v) {
                return Math.max(0, Math.min(255, Math.round(v)));
            };
            var deux = function (v) {
                var t = borne(v).toString(16);
                return t.length === 1 ? '0' + t : t;
            };
            return '#' + deux(r) + deux(g) + deux(b);
        };

        var melange = function (a, b, pourcent) {
            var ca = composantes(a);
            var cb = composantes(b);
            if (!ca || !cb) {
                return a;
            }
            var p = pourcent / 100;
            return enHex(
                ca[0] + (cb[0] - ca[0]) * p,
                ca[1] + (cb[1] - ca[1]) * p,
                ca[2] + (cb[2] - ca[2]) * p
            );
        };

        var luminance = function (hex) {
            var c = composantes(hex);
            if (!c) {
                return 0;
            }
            var canal = function (v) {
                v = v / 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            };
            return 0.2126 * canal(c[0]) + 0.7152 * canal(c[1]) + 0.0722 * canal(c[2]);
        };

        var contraste = function (a, b) {
            var la = luminance(a);
            var lb = luminance(b);
            return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
        };

        var fondClair = function (fond) {
            return luminance(fond) >= 0.35;
        };

        var ajuster = function (couleur, fond, minimum) {
            if (contraste(couleur, fond) >= minimum) {
                return couleur;
            }
            var vers = fondClair(fond) ? '#000000' : '#ffffff';
            for (var pas = 1; pas <= 100; pas++) {
                var essai = melange(couleur, vers, pas);
                if (contraste(essai, fond) >= minimum) {
                    return essai;
                }
            }
            return vers;
        };

        var texteSur = function (fond, minimum) {
            var blanc = contraste('#ffffff', fond);
            var noir = contraste('#000000', fond);
            if (blanc >= minimum && noir >= minimum) {
                return blanc >= noir ? '#ffffff' : '#000000';
            }
            if (blanc >= minimum) {
                return '#ffffff';
            }
            if (noir >= minimum) {
                return '#000000';
            }
            return ajuster('#ffffff', fond, minimum);
        };

        // --- Éléments de la page ---

        var champ = function (nom) {
            return formulaire.elements[nom] || document.getElementById(nom);
        };

        var valeur = function (nom, defaut) {
            var el = champ(nom);
            if (el && typeof el.value === 'string' && el.value.trim() !== '') {
                return el.value.trim();
            }
            return defaut || '';
        };

        var caseCochee = function (nom) {
            var el = champ(nom);
            return el ? !!el.checked : true;
        };

        var cible = function (cle) {
            return apercu.querySelector('[data-apercu="' + cle + '"]');
        };

        var ecrire = function (cle, texte) {
            var el = cible(cle);
            if (el) {
                // textContent uniquement : jamais innerHTML.
                el.textContent = texte;
            }
        };

        var afficher = function (cle, visible) {
            var el = cible(cle);
            if (el) {
                el.hidden = !visible;
            }
        };

        var modeCouleurs = function () {
            var choix = formulaire.querySelector('input[name="couleurs_mode"]:checked');
            return choix ? choix.value : 'papier';
        };

        var couleursSaisies = function () {
            var defauts = { fond: '#f3e7ce', texte: '#1d140d', accent: '#8f3126', bordure: '#362c24' };
            var resultat = {};
            Object.keys(defauts).forEach(function (cle) {
                var brut = valeur('couleur_' + cle, '');
                var estValide = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/.test(brut);
                resultat[cle] = estValide ? brut : defauts[cle];
            });
            return resultat;
        };

        // --- Contrastes : le tableau et le message sous les couleurs ---

        var minima = { texte: 7, 'texte-doux': 4.5, accent: 4.5, 'sur-accent': 4.5 };

        var majContrastes = function (variables) {
            Object.keys(minima).forEach(function (cle) {
                var ligne = document.querySelector('[data-contraste="' + cle + '"]');
                if (!ligne) {
                    return;
                }
                var ratio;
                if (cle === 'texte') {
                    ratio = contraste(variables.texte, variables.fond);
                } else if (cle === 'texte-doux') {
                    ratio = contraste(variables.texteDoux, variables.fond);
                } else if (cle === 'accent') {
                    ratio = contraste(variables.accent, variables.fond);
                } else {
                    ratio = contraste(variables.surAccent, variables.accent);
                }
                var ok = ratio >= minima[cle];
                var valeurEl = ligne.querySelector('[data-contraste-ratio]');
                var etatEl = ligne.querySelector('[data-contraste-etat]');
                if (valeurEl) {
                    valeurEl.textContent = ratio.toFixed(2).replace('.', ',') + ':1';
                }
                if (etatEl) {
                    etatEl.textContent = ok ? 'suffisant' : 'insuffisant';
                }
                ligne.classList.toggle('contraste-ok', ok);
                ligne.classList.toggle('contraste-ko', !ok);
            });

            var message = document.querySelector('[data-contraste-message]');
            if (message) {
                var insuffisants = [];
                if (contraste(variables.texte, variables.fond) < minima.texte) { insuffisants.push('le texte'); }
                if (contraste(variables.accent, variables.fond) < minima.accent) { insuffisants.push('l’accent'); }
                if (contraste(variables.surAccent, variables.accent) < minima['sur-accent']) { insuffisants.push('le texte des boutons'); }
                message.textContent = insuffisants.length === 0
                    ? 'Tous les contrastes sont suffisants : ces couleurs peuvent être enregistrées.'
                    : 'Attention : ' + insuffisants.join(', ') + ' ne se détache pas assez. '
                        + 'Le site refusera l’enregistrement et proposera « Corriger pour moi ».';
            }
        };

        // --- Application des couleurs à l'aperçu (API CSSOM) ---

        var appliquerCouleurs = function () {
            var mode = modeCouleurs();
            var variables;

            if (mode === 'perso') {
                var base = couleursSaisies();
                // Le texte et l'accent sont affichés tels quels : s'ils ne
                // sont pas assez contrastés, le tableau le dit et le site
                // refusera l'enregistrement. Seules les couleurs dérivées
                // (gris, texte des boutons) sont ajustées automatiquement.
                variables = {
                    fond: base.fond,
                    texte: base.texte,
                    accent: base.accent,
                    bordure: base.bordure,
                    texteDoux: ajuster(melange(base.texte, base.fond, 35), base.fond, 4.5),
                    surAccent: texteSur(base.accent, 4.5)
                };
            } else if (mode === 'bleu') {
                variables = {
                    fond: '#f6f5f2',
                    texte: '#1d1d1b',
                    accent: '#2b4a68',
                    bordure: '#dcd8d0',
                    texteDoux: '#5d5a55',
                    surAccent: '#ffffff'
                };
            } else {
                variables = {
                    fond: '#f3e7ce',
                    texte: '#1d140d',
                    accent: '#8f3126',
                    bordure: '#362c24',
                    texteDoux: '#5a493d',
                    surAccent: '#f3e7ce'
                };
            }

            // element.style.setProperty : autorisé par « style-src 'self' »
            // (la CSP ne bloque que les attributs style et les <style>).
            apercu.style.setProperty('--fond', variables.fond);
            apercu.style.setProperty('--texte', variables.texte);
            apercu.style.setProperty('--accent', variables.accent);
            apercu.style.setProperty('--bordure', variables.bordure);
            apercu.style.setProperty('--texte-doux', variables.texteDoux);
            apercu.style.setProperty('--surface', melange(variables.fond, '#ffffff', 6));
            apercu.style.setProperty('--plaque-1', variables.bordure);
            apercu.style.setProperty('--bordure-douce', melange(variables.bordure, variables.fond, 50));

            var bouton = apercu.querySelector('[data-apercu="bouton"]');
            if (bouton) {
                bouton.style.setProperty('background', variables.accent);
                bouton.style.setProperty('border-color', variables.accent);
                bouton.style.setProperty('color', variables.surAccent);
            }

            majContrastes(variables);
        };

        // --- Textes et cases ---

        var majTextes = function () {
            var milieu = valeur('bandeau_milieu', 'Édition des familles réunies');
            var droite = valeur('bandeau_droite', 'Prix : un sourire');
            ecrire('bandeau', milieu);
            ecrire('prix', droite);
            ecrire('titre', valeur('nom', apercu.querySelector('[data-apercu="titre"]').textContent));

            var fondee = valeur('fondee', '');
            ecrire('fondee', fondee !== '' ? 'Fondée en ' + fondee : '');

            var devise = valeur('devise', '');
            ecrire('devise', devise !== '' ? '« ' + devise + ' »' : '');

            var choix = formulaire.querySelector('input[name="numero_mode"]:checked');
            var mode = choix ? choix.value : 'auto';
            var texte = valeur('numero_texte', '');
            var numero = mode === 'aucun' ? '' : (mode === 'libre' && texte !== '' ? texte : numeroAuto);
            ecrire('numero', numero);

            var montrerBandeau = caseCochee('montrer_bandeau');
            var montrerDevise = caseCochee('montrer_devise');
            var montrerFondee = caseCochee('montrer_fondee');
            var montrerNumero = caseCochee('montrer_numero');

            afficher('bandeau', montrerBandeau);
            afficher('prix', montrerBandeau);
            afficher('fondee', montrerFondee);
            afficher('devise', montrerDevise);
            afficher('numero', montrerNumero);

            // Une ligne vide ne doit pas laisser un filet double tout seul.
            var barre = apercu.querySelector('.apercu-journal__barre');
            if (barre) {
                barre.hidden = !montrerBandeau;
            }
            var ligne = apercu.querySelector('.apercu-journal__ligne');
            if (ligne) {
                ligne.hidden = !(montrerDevise || montrerFondee || montrerNumero);
            }

            apercu.classList.toggle('apercu-journal--gothique', caseCochee('gothique'));
            apercu.classList.toggle('apercu-journal--romain', !caseCochee('gothique'));
        };

        // --- Aspect « photo ancienne » (1.32.0) ---
        // L'aperçu change de classe comme la vraie image : le filtre CSS est
        // le même que celui des photos du site.

        var majAspect = function () {
            var choix = formulaire.querySelector('input[name="aspect_photo"]:checked');
            var mode = choix ? choix.value : 'aucun';
            var image = document.querySelector('#apercu-aspect [data-apercu-aspect-image]');
            if (image) {
                image.classList.remove('aspect-photo', 'aspect-photo--doux', 'aspect-photo--marque');
                if (mode === 'doux' || mode === 'marque') {
                    image.classList.add('aspect-photo', 'aspect-photo--' + mode);
                }
            }
            var libelle = document.querySelector('[data-apercu-aspect-libelle]');
            if (libelle) {
                libelle.textContent = mode === 'doux' ? 'Doux' : (mode === 'marque' ? 'Marqué' : 'Aucun');
            }
        };

        // --- Branchements ---

        formulaire.addEventListener('input', function (evenement) {
            var nom = evenement.target && evenement.target.name ? evenement.target.name : '';
            if (nom === 'couleur_fond' || nom === 'couleur_texte' || nom === 'couleur_accent' || nom === 'couleur_bordure') {
                // Le champ texte et le sélecteur de couleur se suivent.
                var cle = nom.replace('couleur_', '');
                var pipette = formulaire.querySelector('[data-apercu-pipette="' + cle + '"]');
                if (pipette && /^#[0-9a-fA-F]{6}$/.test(evenement.target.value)) {
                    pipette.value = evenement.target.value;
                }
            }
            majTextes();
            appliquerCouleurs();
            majAspect();
        });

        formulaire.addEventListener('change', function (evenement) {
            var source = evenement.target;
            // Le sélecteur de couleur et le champ #RRGGBB se complètent.
            if (source && source.hasAttribute && source.hasAttribute('data-apercu-pipette')) {
                var cle = source.getAttribute('data-apercu-pipette');
                var champTexte = formulaire.querySelector('[data-apercu-couleur="' + cle + '"]');
                if (champTexte) {
                    champTexte.value = source.value;
                }
            }
            majTextes();
            appliquerCouleurs();
            majAspect();
        });

        majTextes();
        appliquerCouleurs();
        majAspect();
    })();

    // ---------------------------------------------------------------------
    // 9. « Tout cocher » une liste à cases
    // ---------------------------------------------------------------------
    document.querySelectorAll('[data-tout-cocher]').forEach(function (bouton) {
        var cible = document.getElementById(bouton.getAttribute('data-tout-cocher'));
        if (!cible) {
            return;
        }
        bouton.addEventListener('click', function () {
            var cases = cible.querySelectorAll('input[type="checkbox"][name="documents[]"]');
            var toutCoche = true;
            cases.forEach(function (caseACocher) {
                if (!caseACocher.checked) {
                    toutCoche = false;
                }
            });
            cases.forEach(function (caseACocher) {
                caseACocher.checked = !toutCoche;
            });
            bouton.textContent = toutCoche ? 'Tout cocher sur cette page' : 'Tout décocher';
        });
    });
})();
