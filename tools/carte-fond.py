#!/usr/bin/env python3
"""Fabrique le fond de carte embarqué (app/data/carte_fond.php).

Ce script n'est PAS utilisé par le site : il tourne une fois, sur un
ordinateur, et son résultat est versionné. Le Raspberry Pi n'a donc besoin ni
de Python ni de quoi que ce soit d'autre.

Ce qu'il fait :

  1. lit les contours « land » de Natural Earth 4.1.0, distribués en TopoJSON
     par le paquet world-atlas (`npm pack world-atlas@2`) ;
  2. coupe les anneaux au méridien 180 (Chukotka et l'Antarctique passent d'un
     bord à l'autre : sans cette coupe, le polygone se replie sur lui-même et
     laisse un trait en travers de la carte) ;
  3. les projette en équirectangulaire, exactement comme le fait le site
     (app/lib/carte.php, fonction carte_projeter) ;
  4. les découpe au cadre de chaque vue et simplifie les tracés ;
  5. écrit app/data/carte_fond.php (un seul fichier, aucune dépendance).

Utilisation :

    npm pack world-atlas@2 && tar xzf world-atlas-2.0.2.tgz
    pip install shapely
    python3 tools/carte-fond.py --source package

Natural Earth est dans le domaine public ; world-atlas (Michael Bostock) est
sous licence ISC. Voir docs/carte.md.
"""

from __future__ import annotations

import argparse
import json
import os
import sys

try:
    from shapely.affinity import affine_transform
    from shapely.geometry import Polygon, box
    from shapely.validation import make_valid
except ImportError:  # pragma: no cover
    sys.exit('Ce script a besoin de shapely : pip install shapely')

# Les deux vues du site : mêmes valeurs que celles écrites dans le fichier
# final, et mêmes conventions que app/lib/carte.php.
VUES = {
    'france': {
        'fichier': 'land-50m.json',
        'lon_min': -5.6, 'lon_max': 9.9,
        'lat_min': 41.0, 'lat_max': 51.4,
        'largeur': 1000, 'hauteur': 671,
        'simplification': 0.35,   # en pixels de la vue
        'aire_minimale': 1.5,     # en pixels²
    },
    'monde': {
        'fichier': 'land-110m.json',
        'lon_min': -180.0, 'lon_max': 180.0,
        'lat_min': -58.0, 'lat_max': 84.0,
        'largeur': 1000, 'hauteur': 394,
        'simplification': 0.45,
        'aire_minimale': 2.5,
    },
}


def lire_topo(chemin: str) -> list[list[tuple[float, float]]]:
    """Anneaux (longitude, latitude) du calque « land » d'un fichier TopoJSON."""
    with open(chemin, encoding='utf-8') as flux:
        topo = json.load(flux)

    echelle = topo.get('transform', {}).get('scale', [1.0, 1.0])
    decalage = topo.get('transform', {}).get('translate', [0.0, 0.0])
    arcs = []
    for arc in topo['arcs']:
        x = y = 0.0
        points = []
        for dx, dy in arc:
            x += dx
            y += dy
            points.append((x * echelle[0] + decalage[0], y * echelle[1] + decalage[1]))
        arcs.append(points)

    def anneau(indices: list[int]) -> list[tuple[float, float]]:
        points: list[tuple[float, float]] = []
        for index in indices:
            arc = arcs[abs(index)] if index >= 0 else list(reversed(arcs[abs(index) - 1]))
            if points and points[-1] == arc[0]:
                points.extend(arc[1:])
            else:
                points.extend(arc)
        return points

    anneaux: list[list[tuple[float, float]]] = []
    for geometrie in topo['objects'].values():
        for objet in geometrie.get('geometries', []):
            type_ = objet['type']
            if type_ == 'Polygon':
                for contour in objet['arcs']:
                    anneaux.append(anneau(contour))
            elif type_ == 'MultiPolygon':
                for polygone in objet['arcs']:
                    for contour in polygone:
                        anneaux.append(anneau(contour))
    return anneaux


def couper_antimeridien(points: list[tuple[float, float]]) -> list[list[tuple[float, float]]]:
    """Coupe un anneau qui passe le méridien 180 en morceaux « plats ».

    Chukotka, les Fidji et l'Antarctique passent d'un bord de la carte à
    l'autre. Dessiné d'un seul trait, un tel anneau fait le tour du monde et se
    replie sur lui-même : c'est ce qui laissait un trait horizontal en travers
    de la planisphère. On le coupe donc à +180 et à -180.
    """
    morceaux: list[list[tuple[float, float]]] = []
    courant: list[tuple[float, float]] = []

    for index, point in enumerate(points):
        suivant = points[(index + 1) % len(points)]
        courant.append(point)

        delta = suivant[0] - point[0]
        if abs(delta) <= 180.0:
            continue

        # Longitude du point de passage, par interpolation sur le segment.
        if delta < 0:                      # de +179 vers -179 : on sort à +180
            ecart = (suivant[0] + 360.0) - point[0]
            part = (180.0 - point[0]) / ecart if ecart else 0.5
            latitude = point[1] + part * (suivant[1] - point[1])
            courant.append((180.0, latitude))
            morceaux.append(courant)
            courant = [(-180.0, latitude)]
        else:                              # de -179 vers +179 : on sort à -180
            ecart = point[0] - (suivant[0] - 360.0)
            part = (point[0] - -180.0) / ecart if ecart else 0.5
            latitude = point[1] - part * (point[1] - suivant[1])
            courant.append((-180.0, latitude))
            morceaux.append(courant)
            courant = [(180.0, latitude)]

    if len(courant) >= 3:
        morceaux.append(courant)

    return [morceau for morceau in morceaux if len(morceau) >= 3]


def projeter(vue: dict, geometrie):
    """Projette une géométrie (en degrés) vers les pixels de la vue.

    La projection est la même que celle du site (équirectangulaire) ; elle est
    affine, donc appliquée d'un bloc par shapely.
    """
    echelle_x = vue['largeur'] / (vue['lon_max'] - vue['lon_min'])
    echelle_y = -vue['hauteur'] / (vue['lat_max'] - vue['lat_min'])
    decalage_x = -vue['lon_min'] * echelle_x
    decalage_y = vue['lat_max'] * -echelle_y

    return affine_transform(geometrie, [echelle_x, 0.0, 0.0, echelle_y, decalage_x, decalage_y])


def polygones(geometrie) -> list:
    """Les polygones d'une géométrie, quel que soit son type."""
    if geometrie.is_empty:
        return []
    if geometrie.geom_type == 'Polygon':
        return [geometrie]
    return [g for g in getattr(geometrie, 'geoms', []) if g.geom_type == 'Polygon']


def morceaux(vue: dict, anneaux: list[list[tuple[float, float]]]) -> list[str]:
    """Découpe, simplifie et met en forme les anneaux d'une vue."""
    cadre = box(0.0, 0.0, float(vue['largeur']), float(vue['hauteur']))
    traces: list[str] = []

    for anneau in anneaux:
        for morceau in couper_antimeridien(anneau):
            polygone = Polygon(morceau)
            if not polygone.is_valid:
                # Quelques contours de Natural Earth se croisent légèrement :
                # make_valid() les répare sans déplacer la côte.
                polygone = make_valid(polygone)
            if polygone.is_empty:
                continue

            decoupe = projeter(vue, polygone).intersection(cadre)
            for partie in polygones(decoupe):
                partie = partie.simplify(vue['simplification'], preserve_topology=True)
                if partie.is_empty or partie.area < vue['aire_minimale']:
                    continue

                x_min, y_min, x_max, y_max = partie.bounds
                # Une bande très allongée est un défaut de découpe, pas une
                # terre : elle se verrait comme un trait en travers de la carte.
                if (y_max - y_min) < 2.0 and (x_max - x_min) > 40.0:
                    continue
                if (x_max - x_min) < 2.0 and (y_max - y_min) > 40.0:
                    continue

                for contour in [partie.exterior, *partie.interiors]:
                    coords = [(round(x, 1), round(y, 1)) for x, y in list(contour.coords)[:-1]]
                    if len(coords) < 3:
                        continue
                    if Polygon(coords).area < 12.0:      # trou minuscule : inutile
                        continue
                    traces.append('M' + 'L'.join(f'{x:.1f} {y:.1f}' for x, y in coords) + 'Z')

    return traces


def main() -> int:
    analyseur = argparse.ArgumentParser(description='Fabrique app/data/carte_fond.php')
    analyseur.add_argument('--source', default='package', help='dossier contenant land-50m.json')
    analyseur.add_argument('--sortie', default=None, help='fichier PHP à écrire')
    options = analyseur.parse_args()

    sortie = options.sortie or os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
        'app', 'data', 'carte_fond.php',
    )

    caches: dict[str, list[list[tuple[float, float]]]] = {}
    blocs = []
    for nom, vue in VUES.items():
        if vue['fichier'] not in caches:
            chemin = os.path.join(options.source, vue['fichier'])
            if not os.path.isfile(chemin):
                sys.exit('Fichier introuvable : ' + chemin)
            caches[vue['fichier']] = lire_topo(chemin)
        traces = morceaux(vue, caches[vue['fichier']])
        trace = ''.join(traces)
        blocs.append((nom, vue, trace))
        print(f"{nom} : {len(traces)} morceau(x), {len(trace)} caractères")

    texte = """<?php
/**
 * Fond de carte embarqué — AUCUNE ressource externe, aucun appel réseau.
 *
 * Source : Natural Earth 4.1.0 (domaine public), contours « land » au
 * 1:50 000 000 (vue France) et au 1:110 000 000 (vue monde), distribués en
 * TopoJSON par le paquet « world-atlas » (Michael Bostock, licence ISC).
 *
 * Les tracés ont été coupés au méridien 180, projetés en équirectangulaire,
 * découpés au cadre de chaque vue et simplifiés par tools/carte-fond.py ; le
 * site ne fait aucune conversion à l'affichage : il recopie le tracé dans le
 * SVG. Voir docs/carte.md pour l'origine, la licence et la projection.
 *
 * Ce fichier est engendré : on n'y touche pas à la main.
 */

declare(strict_types=1);

return [
    'source'  => 'Natural Earth 4.1.0 (domaine public) — contours simplifiés',
    'genere'  => 'tools/carte-fond.py',
    'vues'    => [
"""

    for nom, vue, trace in blocs:
        texte += (
            f"        '{nom}' => [\n"
            f"            'lon_min' => {vue['lon_min']:g}, 'lon_max' => {vue['lon_max']:g},\n"
            f"            'lat_min' => {vue['lat_min']:g}, 'lat_max' => {vue['lat_max']:g},\n"
            f"            'largeur' => {vue['largeur']}, 'hauteur' => {vue['hauteur']},\n"
            f"            'trace'   => '{trace}',\n"
            f"        ],\n"
        )

    texte += "    ],\n];\n"

    with open(sortie, 'w', encoding='utf-8') as flux:
        flux.write(texte)
    print('écrit :', sortie, f'({len(texte)} octets)')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
