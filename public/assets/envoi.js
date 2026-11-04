/**
 * Histoire de famille — envoi de fichiers par morceaux (aucune dépendance).
 *
 * Le navigateur découpe chaque fichier avec Blob.slice et envoie les morceaux
 * un par un (deux au maximum en même temps). Si la connexion tombe, il réessaie
 * en attendant de plus en plus longtemps, et il reprend là où il s'était arrêté
 * — y compris après avoir fermé la page et redéposé le même fichier.
 *
 * Tout est facultatif : sans JavaScript, le formulaire simple du site reste
 * utilisable (limité à la taille de l'envoi simple) et la page le dit.
 *
 * Aucun style en ligne, aucun script en ligne : la CSP du site reste stricte.
 */
(function () {
    'use strict';

    var ESSAIS_MAX = 8;
    var MORCEAUX_EN_PARALLELE = 2;

    // -----------------------------------------------------------------------
    // Petits utilitaires d'affichage
    // -----------------------------------------------------------------------

    function octets(n) {
        n = Number(n) || 0;
        if (n < 1024) {
            return n + ' o';
        }
        var unites = ['Ko', 'Mo', 'Go', 'To'];
        for (var i = 0; i < unites.length; i++) {
            n = n / 1024;
            if (n < 1024) {
                return n.toLocaleString('fr-FR', { maximumFractionDigits: n < 10 ? 1 : 0 }) + ' ' + unites[i];
            }
        }
        return n.toFixed(0) + ' To';
    }

    function duree(secondes) {
        secondes = Math.max(0, Math.round(secondes));
        if (!isFinite(secondes) || secondes <= 0) {
            return '';
        }
        if (secondes < 60) {
            return secondes + ' s';
        }
        var minutes = Math.floor(secondes / 60);
        if (minutes < 60) {
            return minutes + ' min ' + (secondes % 60) + ' s';
        }
        var heures = Math.floor(minutes / 60);
        return heures + ' h ' + (minutes % 60) + ' min';
    }

    function element(nom, classe, texte) {
        var noeud = document.createElement(nom);
        if (classe) {
            noeud.className = classe;
        }
        if (texte !== undefined && texte !== null) {
            noeud.textContent = texte;
        }
        return noeud;
    }

    // -----------------------------------------------------------------------
    // Un téléverseur par bloc [data-envoi]
    // -----------------------------------------------------------------------

    function Televerseur(racine) {
        this.racine = racine;
        this.urls = {
            init: racine.getAttribute('data-envoi-init'),
            morceau: racine.getAttribute('data-envoi-morceau'),
            etat: racine.getAttribute('data-envoi-etat'),
            terminer: racine.getAttribute('data-envoi-terminer'),
            decision: racine.getAttribute('data-envoi-decision'),
            annuler: racine.getAttribute('data-envoi-annuler'),
            lot: racine.getAttribute('data-envoi-lot')
        };
        this.csrf = racine.getAttribute('data-envoi-csrf') || '';
        this.creer = racine.getAttribute('data-envoi-creer') !== '0';
        this.tailleMorceau = Number(racine.getAttribute('data-envoi-morceau-octets')) || 5242880;
        this.tailleMax = Number(racine.getAttribute('data-envoi-max-octets')) || 4294967296;
        this.reprendre = racine.getAttribute('data-envoi-reprendre') || '';
        this.reprendreNom = racine.getAttribute('data-envoi-reprendre-nom') || '';
        this.reprendreTaille = Number(racine.getAttribute('data-envoi-reprendre-taille')) || 0;

        this.champ = racine.querySelector('[data-envoi-champ]');
        this.zone = racine.querySelector('[data-envoi-zone]');
        this.liste = racine.querySelector('[data-envoi-liste]');
        this.actions = racine.querySelector('[data-envoi-actions]');
        this.annonce = racine.querySelector('[data-envoi-annonce]');
        this.boutonPause = racine.querySelector('[data-envoi-tout-pause]');
        this.lienLot = racine.querySelector('[data-envoi-vers-lot]');

        this.file = [];      // les fichiers déposés, dans l'ordre
        this.encours = null; // le fichier en train de partir
        this.enPause = false;
        this.derniereAnnonce = 0;

        this.brancher();
        this.remplirDossierDepart();
    }

    Televerseur.prototype.brancher = function () {
        var moi = this;

        if (this.champ) {
            this.champ.addEventListener('change', function () {
                moi.ajouter(this.files);
                this.value = '';
            });
        }

        if (this.zone) {
            ['dragenter', 'dragover'].forEach(function (nom) {
                moi.zone.addEventListener(nom, function (evenement) {
                    evenement.preventDefault();
                    moi.zone.classList.add('envoi__zone--survol');
                });
            });
            ['dragleave', 'drop'].forEach(function (nom) {
                moi.zone.addEventListener(nom, function (evenement) {
                    evenement.preventDefault();
                    moi.zone.classList.remove('envoi__zone--survol');
                });
            });
            moi.zone.addEventListener('drop', function (evenement) {
                if (evenement.dataTransfer && evenement.dataTransfer.files) {
                    moi.ajouter(evenement.dataTransfer.files);
                }
            });
        }

        if (this.boutonPause) {
            this.boutonPause.addEventListener('click', function () {
                moi.basculerPause();
            });
        }
    };

    /** Remplir le dossier de départ quand on arrive par « Reprendre ». */
    Televerseur.prototype.remplirDossierDepart = function () {
        if (!this.zone || !this.reprendre) {
            return;
        }
        var note = element('p', 'envoi__reprise', 'En attente de « ' + this.reprendreNom + ' » ('
            + octets(this.reprendreTaille) + '). Le fichier doit être identique.');
        this.zone.appendChild(note);
    };

    Televerseur.prototype.annoncer = function (texte, force) {
        if (!this.annonce) {
            return;
        }
        var maintenant = Date.now();
        if (!force && maintenant - this.derniereAnnonce < 2000) {
            return;
        }
        this.derniereAnnonce = maintenant;
        this.annonce.textContent = texte;
    };

    /** Ajoute des fichiers à la file (refuse tout de suite ce qui ne passera pas). */
    Televerseur.prototype.ajouter = function (listeFichiers) {
        if (!listeFichiers || !listeFichiers.length) {
            return;
        }

        for (var i = 0; i < listeFichiers.length; i++) {
            var fichier = listeFichiers[i];
            var tache = this.creerTache(fichier);

            if (fichier.size <= 0) {
                this.signalerErreur(tache, 'Ce fichier est vide.');
                continue;
            }
            if (fichier.size > this.tailleMax) {
                this.signalerErreur(tache, 'Fichier trop gros : ' + octets(fichier.size)
                    + ' pour un maximum de ' + octets(this.tailleMax) + '.');
                continue;
            }
            if (this.reprendre && (fichier.name !== this.reprendreNom || fichier.size !== this.reprendreTaille)) {
                this.signalerErreur(tache, 'Ce n’est pas le même fichier que celui à reprendre ('
                    + this.reprendreNom + ', ' + octets(this.reprendreTaille)
                    + '). Déposez ce fichier-là, ou annulez l’envoi dans « Mes envois ».');
                continue;
            }

            this.file.push(tache);
        }

        this.liste.hidden = this.file.length === 0;
        if (this.actions) {
            this.actions.hidden = this.file.length === 0;
        }
        this.demarrerSuivant();
    };

    Televerseur.prototype.creerTache = function (fichier) {
        var ligne = element('li', 'envoi-ligne');
        ligne.setAttribute('data-etat', 'attente');

        var entete = element('p', 'envoi-ligne__entete');
        var nom = element('span', 'envoi-ligne__nom', fichier.name);
        var taille = element('span', 'envoi-ligne__taille discret', octets(fichier.size));
        entete.appendChild(nom);
        entete.appendChild(document.createTextNode(' '));
        entete.appendChild(taille);

        var jauge = document.createElement('progress');
        jauge.className = 'envoi-ligne__jauge';
        jauge.max = 100;
        jauge.value = 0;
        jauge.setAttribute('aria-label', 'Progression de ' + fichier.name);

        var statut = element('p', 'envoi-ligne__statut', 'En attente…');
        statut.setAttribute('role', 'status');

        var boutons = element('p', 'envoi-ligne__boutons');

        var boutonPause = element('button', 'lien-bouton', 'Mettre en pause');
        boutonPause.type = 'button';
        boutonPause.hidden = true;

        var boutonAnnuler = element('button', 'lien-bouton lien-bouton--danger', 'Annuler');
        boutonAnnuler.type = 'button';

        boutons.appendChild(boutonPause);
        boutons.appendChild(boutonAnnuler);

        ligne.appendChild(entete);
        ligne.appendChild(jauge);
        ligne.appendChild(statut);
        ligne.appendChild(boutons);
        this.liste.appendChild(ligne);

        var tache = {
            fichier: fichier,
            ligne: ligne,
            jauge: jauge,
            statut: statut,
            boutons: boutons,
            boutonPause: boutonPause,
            boutonAnnuler: boutonAnnuler,
            token: '',
            total: 0,
            recus: {},
            pause: false,
            annule: false,
            termine: false,
            remplis: 0,
            octetsFaits: 0,
            debut: 0,
            enVoie: 0,
            xhrs: []
        };

        var moi = this;
        boutonPause.addEventListener('click', function () {
            tache.pause = !tache.pause;
            boutonPause.textContent = tache.pause ? 'Reprendre' : 'Mettre en pause';
            tache.statut.textContent = tache.pause ? 'En pause.' : 'Reprise…';
            if (!tache.pause) {
                moi.pomper(tache);
            }
        });
        boutonAnnuler.addEventListener('click', function () {
            moi.annuler(tache);
        });

        return tache;
    };

    Televerseur.prototype.signalerErreur = function (tache, message) {
        if (!tache) {
            return;
        }
        tache.ligne.setAttribute('data-etat', 'erreur');
        tache.statut.textContent = message;
        tache.jauge.hidden = true;
        this.annoncer(tache.fichier.name + ' : ' + message, true);
    };

    // -----------------------------------------------------------------------
    // File d'attente : un fichier à la fois, deux morceaux en parallèle
    // -----------------------------------------------------------------------

    Televerseur.prototype.demarrerSuivant = function () {
        if (this.encours || this.enPause) {
            return;
        }
        for (var i = 0; i < this.file.length; i++) {
            var tache = this.file[i];
            if (!tache.termine && !tache.annule && !tache.demarre && tache.ligne.getAttribute('data-etat') !== 'erreur') {
                tache.demarre = true;
                this.encours = tache;
                this.demarrer(tache);
                return;
            }
        }
    };

    Televerseur.prototype.demarrer = function (tache) {
        var moi = this;
        tache.debut = Date.now();
        tache.statut.textContent = 'Préparation…';
        tache.ligne.setAttribute('data-etat', 'envoi');
        tache.boutonPause.hidden = false;

        this.initier(tache).then(function () {
            if (tache.annule) {
                return;
            }
            return moi.pomper(tache);
        }).catch(function () {
            // Le message a déjà été affiché par le code d'erreur.
        });
    };

    /** « init » : le serveur vérifie tout et rend l'identifiant d'envoi. */
    Televerseur.prototype.initier = function (tache) {
        var moi = this;
        var donnees = new FormData();
        donnees.append('_csrf', this.csrf);
        donnees.append('nom', tache.fichier.name);
        donnees.append('taille', String(tache.fichier.size));
        donnees.append('type', tache.fichier.type || '');
        donnees.append('creer', this.creer ? '1' : '0');
        donnees.append('reprise', this.reprendre ? '1' : '0');

        return this.poster(this.urls.init, donnees).then(function (reponse) {
            var envoi = reponse.envoi;
            tache.token = envoi.token;
            tache.total = envoi.morceaux;
            tache.tailleMorceau = envoi.morceau;

            (envoi.recus || []).forEach(function (numero) {
                tache.recus[numero] = true;
            });
            tache.remplis = (envoi.recus || []).length;
            tache.octetsFaits = moi.octetsRecus(tache);

            if (tache.remplis > 0) {
                tache.statut.textContent = 'Reprise : ' + tache.remplis + ' morceau(x) déjà reçus.';
            }
            moi.majJauge(tache);

            if (envoi.complet) {
                return moi.terminer(tache);
            }
            return null;
        }).catch(function (erreur) {
            moi.echouer(tache, erreur);
            throw erreur;
        });
    };

    Televerseur.prototype.octetsRecus = function (tache) {
        var somme = 0;
        for (var numero in tache.recus) {
            if (Object.prototype.hasOwnProperty.call(tache.recus, numero)) {
                somme += this.tailleDuMorceau(tache, Number(numero));
            }
        }
        return somme;
    };

    Televerseur.prototype.tailleDuMorceau = function (tache, numero) {
        var debut = numero * tache.tailleMorceau;
        return Math.min(tache.tailleMorceau, tache.fichier.size - debut);
    };

    /** Envoie les morceaux manquants, deux au maximum en même temps. */
    Televerseur.prototype.pomper = function (tache) {
        var moi = this;

        var boucle = function () {
            if (tache.annule || tache.termine || tache.pause || moi.enPause) {
                return Promise.resolve();
            }
            if (tache.remplis >= tache.total) {
                return moi.terminer(tache);
            }
            return moi.envoyerLot(tache, MORCEAUX_EN_PARALLELE).then(function (encore) {
                return encore ? boucle() : null;
            });
        };

        return boucle();
    };

    /** Envoie jusqu'à $nombre morceaux en parallèle ; false s'il n'en reste plus. */
    Televerseur.prototype.envoyerLot = function (tache, nombre) {
        var moi = this;
        var promesses = [];

        for (var i = 0; i < nombre; i++) {
            var numero = this.prochainMorceau(tache);
            if (numero === null) {
                break;
            }
            promesses.push(this.envoyerMorceau(tache, numero));
        }

        if (!promesses.length) {
            return Promise.resolve(false);
        }

        return Promise.all(promesses).then(function () {
            return true;
        }).catch(function (erreur) {
            moi.echouer(tache, erreur);
            throw erreur;
        });
    };

    /** Numéro du prochain morceau à envoyer, ou null. */
    Televerseur.prototype.prochainMorceau = function (tache) {
        for (var numero = 0; numero < tache.total; numero++) {
            if (!tache.recus[numero]) {
                // Réservation immédiate : deux appels ne prendront pas le même.
                tache.recus[numero] = 'envoi';
                return numero;
            }
        }
        return null;
    };

    /** Envoie un morceau, avec jusqu'à 8 essais et une attente croissante. */
    Televerseur.prototype.envoyerMorceau = function (tache, numero) {
        var moi = this;
        var essai = 0;

        var tentative = function () {
            if (tache.annule || tache.pause || moi.enPause) {
                delete tache.recus[numero];
                return Promise.resolve();
            }
            essai++;
            return moi.envoyerUneFois(tache, numero).then(function () {
                tache.recus[numero] = true;
                tache.remplis++;
                return null;
            }).catch(function (erreur) {
                if (erreur && erreur.fatale) {
                    moi.echouer(tache, erreur);
                    throw erreur;
                }
                if (essai >= ESSAIS_MAX) {
                    var message = 'La connexion a échoué ' + ESSAIS_MAX + ' fois de suite. '
                        + 'Vérifiez votre réseau, puis cliquez sur « Reprendre » : '
                        + 'l’envoi continuera là où il s’est arrêté.';
                    var definitive = new Error(message);
                    definitive.fatale = true;
                    moi.echouer(tache, definitive);
                    throw definitive;
                }
                var attente = Math.min(30, Math.pow(2, essai - 1));
                tache.statut.textContent = 'Coupure réseau : nouvel essai dans ' + attente + ' s ('
                    + essai + ' sur ' + ESSAIS_MAX + ')…';
                return new Promise(function (resoudre) {
                    setTimeout(resoudre, attente * 1000);
                }).then(tentative);
            });
        };

        return tentative();
    };

    /** Une tentative d'envoi d'un morceau (XMLHttpRequest : progression réelle). */
    Televerseur.prototype.envoyerUneFois = function (tache, numero) {
        var moi = this;
        var debut = numero * tache.tailleMorceau;
        var fin = Math.min(debut + tache.tailleMorceau, tache.fichier.size);
        var morceau = tache.fichier.slice(debut, fin);

        return new Promise(function (resoudre, rejeter) {
            var xhr = new XMLHttpRequest();
            tache.xhrs.push(xhr);
            tache.enVoie++;

            var donnees = new FormData();
            donnees.append('_csrf', moi.csrf);
            donnees.append('token', tache.token);
            donnees.append('numero', String(numero));
            donnees.append('morceau', morceau, 'morceau');

            xhr.open('POST', moi.urls.morceau, true);
            xhr.responseType = 'text';

            if (xhr.upload) {
                xhr.upload.onprogress = function (evenement) {
                    if (!evenement.lengthComputable) {
                        return;
                    }
                    moi.majProgression(tache, tache.octetsFaits + evenement.loaded);
                };
            }

            xhr.onload = function () {
                tache.enVoie--;
                moi.retirerXhr(tache, xhr);
                var reponse = null;
                try {
                    reponse = JSON.parse(xhr.responseText || '{}');
                } catch (erreur) {
                    reponse = null;
                }

                if (xhr.status >= 200 && xhr.status < 300 && reponse && reponse.ok) {
                    tache.octetsFaits += (fin - debut);
                    moi.majProgression(tache, tache.octetsFaits);
                    resoudre(reponse);
                    return;
                }

                var refus = new Error((reponse && reponse.erreur)
                    ? reponse.erreur
                    : 'Le serveur a refusé ce morceau (code ' + xhr.status + ').');

                // Un refus net (session, droits, formulaire expiré) ne se
                // réessaie pas : on prévient et on s'arrête.
                if (xhr.status === 401 || xhr.status === 403 || xhr.status === 404) {
                    refus.fatale = true;
                }
                if (xhr.status === 400 && reponse && reponse.code === 'morceau') {
                    // Morceau refusé (taille incohérente) : inutile d'insister.
                    refus.fatale = true;
                }
                rejeter(refus);
            };

            xhr.onerror = function () {
                tache.enVoie--;
                moi.retirerXhr(tache, xhr);
                rejeter(new Error('Connexion interrompue.'));
            };

            xhr.onabort = function () {
                tache.enVoie--;
                moi.retirerXhr(tache, xhr);
                var arret = new Error('Envoi annulé.');
                arret.annule = true;
                rejeter(arret);
            };

            xhr.send(donnees);
        });
    };

    Televerseur.prototype.retirerXhr = function (tache, xhr) {
        var index = tache.xhrs.indexOf(xhr);
        if (index >= 0) {
            tache.xhrs.splice(index, 1);
        }
    };

    Televerseur.prototype.majProgression = function (tache, faits) {
        var pourcentage = tache.fichier.size > 0
            ? Math.min(100, Math.round((faits / tache.fichier.size) * 100))
            : 0;
        this.majJauge(tache, pourcentage);

        var ecoule = (Date.now() - tache.debut) / 1000;
        var debit = ecoule > 0.5 ? faits / ecoule : 0;
        var reste = debit > 0 ? (tache.fichier.size - faits) / debit : 0;

        var texte = pourcentage + ' %';
        if (debit > 0) {
            texte += ' · ' + octets(debit) + '/s';
        }
        if (reste > 1) {
            texte += ' · reste ' + duree(reste);
        }
        tache.statut.textContent = texte;
        tache.ligne.setAttribute('data-etat', 'envoi');
        this.annoncer(tache.fichier.name + ' : ' + texte, false);
    };

    Televerseur.prototype.majJauge = function (tache, pourcentage) {
        if (pourcentage === undefined) {
            pourcentage = tache.fichier.size > 0
                ? Math.round((tache.octetsFaits / tache.fichier.size) * 100)
                : 0;
        }
        tache.jauge.hidden = false;
        tache.jauge.value = pourcentage;
    };

    /** « terminer » : assemblage, empreinte, fiche privée ou doublon. */
    Televerseur.prototype.terminer = function (tache) {
        var moi = this;
        tache.statut.textContent = 'Vérification du fichier sur le serveur…';

        var donnees = new FormData();
        donnees.append('_csrf', this.csrf);
        donnees.append('token', tache.token);

        return this.poster(this.urls.terminer, donnees).then(function (reponse) {
            if (reponse.etat === 'doublon') {
                moi.afficherDoublon(tache, reponse.doublon);
                return null;
            }
            moi.reussir(tache, reponse);
            return null;
        }).catch(function (erreur) {
            moi.echouer(tache, erreur);
            throw erreur;
        });
    };

    Televerseur.prototype.reussir = function (tache, reponse) {
        tache.termine = true;
        tache.ligne.setAttribute('data-etat', 'termine');
        tache.boutonPause.hidden = true;
        tache.boutonAnnuler.hidden = true;
        tache.jauge.value = 100;

        var texte = 'Envoyé. ';
        if (this.creer) {
            texte += 'La fiche est créée en privé : complétez-la pour la publier.';
        } else {
            texte += 'Le fichier est prêt : enregistrez le formulaire ci-dessous.';
        }
        tache.statut.textContent = texte;

        if (this.creer && reponse.lien) {
            var lien = element('a', 'lien', 'Ouvrir la fiche');
            lien.href = reponse.lien;
            tache.boutons.appendChild(document.createTextNode(' '));
            tache.boutons.appendChild(lien);
        }

        // Formulaire qui entoure le composant : on lui donne le fichier prêt.
        if (!this.creer) {
            var formulaire = this.racine.closest('form');
            if (formulaire) {
                var champ = formulaire.querySelector('input[name="envoi"]');
                if (!champ) {
                    champ = element('input');
                    champ.type = 'hidden';
                    champ.name = 'envoi';
                    formulaire.appendChild(champ);
                }
                champ.value = tache.token;

                // Le champ « Fichier » du formulaire n'est plus obligatoire :
                // le fichier est déjà sur le serveur.
                var champFichier = formulaire.querySelector('input[type="file"]');
                if (champFichier) {
                    champFichier.removeAttribute('required');
                }
            }
        }

        this.annoncer(tache.fichier.name + ' : envoyé.', true);
        this.finFichier(tache);
    };

    /** Doublon : on demande à l'administrateur, rien n'est supprimé d'office. */
    Televerseur.prototype.afficherDoublon = function (tache, doublon) {
        var moi = this;
        tache.ligne.setAttribute('data-etat', 'doublon');
        tache.boutonPause.hidden = true;
        tache.boutonAnnuler.hidden = true;
        tache.jauge.value = 100;

        var date = doublon && doublon.doc_date ? ' (' + doublon.doc_date + ')' : '';
        var titre = doublon ? doublon.title : 'un fichier existant';
        tache.statut.textContent = 'Ce fichier est déjà sur le site : « ' + titre + ' »' + date
            + '. Le garder quand même, ou l’ignorer ?';

        var boutons = element('p', 'envoi-ligne__boutons');
        var garder = element('button', 'bouton', 'Garder quand même');
        garder.type = 'button';
        var ignorer = element('button', 'bouton bouton--secondaire', 'Ignorer ce fichier');
        ignorer.type = 'button';

        var decider = function (choix) {
            var donnees = new FormData();
            donnees.append('_csrf', moi.csrf);
            donnees.append('token', tache.token);
            donnees.append('choix', choix);

            garder.disabled = true;
            ignorer.disabled = true;
            tache.statut.textContent = choix === 'garder' ? 'Fichier conservé.' : 'Fichier ignoré.';

            moi.poster(moi.urls.decision, donnees).then(function (reponse) {
                boutons.remove();
                if (reponse.etat === 'termine') {
                    if (doublon && doublon.id) {
                        var lienExistant = element('a', 'lien', 'Voir la fiche existante');
                        lienExistant.href = '/admin/documents/' + doublon.id + '/modifier';
                        tache.boutons.appendChild(document.createTextNode(' '));
                        tache.boutons.appendChild(lienExistant);
                    }
                    moi.reussir(tache, reponse);
                } else {
                    tache.termine = true;
                    tache.ligne.setAttribute('data-etat', 'annule');
                    tache.statut.textContent = 'Fichier ignoré : rien n’a été ajouté.';
                    moi.finFichier(tache);
                }
            }).catch(function (erreur) {
                garder.disabled = false;
                ignorer.disabled = false;
                moi.echouer(tache, erreur);
            });
        };

        garder.addEventListener('click', function () { decider('garder'); });
        ignorer.addEventListener('click', function () { decider('ignorer'); });

        boutons.appendChild(garder);
        boutons.appendChild(ignorer);
        tache.ligne.insertBefore(boutons, tache.boutons);
        this.annoncer('Doublon détecté pour ' + tache.fichier.name + '. Choisissez : garder ou ignorer.', true);
    };

    Televerseur.prototype.finFichier = function (tache) {
        if (this.encours === tache) {
            this.encours = null;
        }
        if (this.lienLot) {
            this.lienLot.hidden = false;
        }
        this.demarrerSuivant();
        this.verifierFin();
    };

    Televerseur.prototype.verifierFin = function () {
        var restants = this.file.filter(function (tache) {
            return !tache.termine && !tache.annule;
        });
        if (restants.length === 0 && this.actions) {
            this.boutonPause.hidden = true;
        }
    };

    Televerseur.prototype.basculerPause = function () {
        var moi = this;
        this.enPause = !this.enPause;
        if (this.boutonPause) {
            this.boutonPause.textContent = this.enPause
                ? 'Tout reprendre'
                : 'Tout mettre en pause';
        }
        if (this.enPause) {
            this.annoncer('Envoi en pause.', true);
            this.file.forEach(function (tache) {
                if (!tache.termine && !tache.annule) {
                    tache.statut.textContent = 'En pause.';
                    tache.ligne.setAttribute('data-etat', 'pause');
                }
            });
            return;
        }
        this.encours = null;
        this.file.forEach(function (tache) {
            if (!tache.termine && !tache.annule) {
                tache.pause = false;
                tache.boutonPause.textContent = 'Mettre en pause';
            }
        });
        this.demarrerSuivant();
        moi.annoncer('Reprise de l’envoi.', true);
    };

    /** Annulation : on arrête les morceaux en vol et on libère l'espace. */
    Televerseur.prototype.annuler = function (tache) {
        var moi = this;
        tache.annule = true;
        tache.xhrs.slice().forEach(function (xhr) {
            xhr.abort();
        });

        var donnees = new FormData();
        donnees.append('_csrf', this.csrf);
        donnees.append('token', tache.token);
        donnees.append('format', 'json');

        tache.ligne.setAttribute('data-etat', 'annule');
        tache.statut.textContent = 'Envoi annulé, morceaux supprimés.';
        tache.boutonPause.hidden = true;
        tache.boutonAnnuler.hidden = true;

        if (tache.token) {
            this.poster(this.urls.annuler, donnees).catch(function () {
                tache.statut.textContent = 'Envoi annulé (le serveur fera le ménage dans 48 h).';
            });
        }
        this.finFichier(tache);
        moi.annoncer(tache.fichier.name + ' : annulé.', true);
    };

    Televerseur.prototype.echouer = function (tache, erreur) {
        var message = (erreur && erreur.message) ? erreur.message : 'L’envoi a échoué.';
        if (erreur && erreur.annule) {
            return;
        }
        tache.ligne.setAttribute('data-etat', 'erreur');
        tache.statut.textContent = message;
        tache.boutonPause.hidden = true;
        this.annoncer(tache.fichier.name + ' : ' + message, true);
        this.finFichier(tache);
    };

    /** POST vers une adresse du site, avec lecture du JSON et des erreurs. */
    Televerseur.prototype.poster = function (url, donnees) {
        return fetch(url, {
            method: 'POST',
            body: donnees,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (reponse) {
            return reponse.text().then(function (texte) {
                var json = null;
                try {
                    json = JSON.parse(texte || '{}');
                } catch (erreur) {
                    json = null;
                }
                if (!reponse.ok || !json || json.ok === false) {
                    var message = (json && json.erreur)
                        ? json.erreur
                        : 'Le serveur n’a pas répondu correctement (code ' + reponse.status + ').';
                    var refus = new Error(message);
                    if (reponse.status === 401 || reponse.status === 403) {
                        refus.fatale = true;
                    }
                    refus.code = json && json.code ? json.code : '';
                    throw refus;
                }
                return json;
            });
        });
    };

    // -----------------------------------------------------------------------
    // Démarrage : un téléverseur par bloc présent dans la page
    // -----------------------------------------------------------------------
    Array.prototype.forEach.call(document.querySelectorAll('[data-envoi]'), function (racine) {
        // Marqué pour ne pas brancher deux fois si le script est rechargé.
        if (racine.getAttribute('data-envoi-pret') === '1') {
            return;
        }
        racine.setAttribute('data-envoi-pret', '1');
        new Televerseur(racine);
    });
})();
