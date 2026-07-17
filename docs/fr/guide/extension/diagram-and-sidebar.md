# Diagramme d'entités & sidebar

## Le diagramme d'entités

Une vue canevas de chaque entité générée et de leurs relations.

<!-- CAPTURE : le diagramme avec quelques entités reliées. Enregistrer sous docs/public/ext-diagram.png puis :
![Diagramme d'entités](/ext-diagram.png)
-->

- Les liens de relation sont des courbes de Bézier ancrées au bord de carte le plus proche, avec flèches et cardinalités dans des pastilles lisibles.
- Survoler une carte met ses connexions en surbrillance ; les déclarations inverses (Post `hasMany` Comment + Comment `belongsTo` Post) sont fusionnées en un seul lien ; les relations auto-référentielles se dessinent en petite boucle.
- Il se comporte comme un vrai canevas : **Ctrl+molette zoome vers le curseur**, glisser le fond déplace la vue, les cartes se déplacent à n'importe quel zoom, et la barre d'outils offre −/+/100 %/Fit.

## L'explorateur de la sidebar

La vue **Generated Entities** de la barre d'activité suit tout ce que le générateur a créé.

<!-- CAPTURE : l'arborescence avec une entité dépliée. Enregistrer sous docs/public/ext-sidebar.png puis :
![Explorateur d'entités](/ext-sidebar.png)
-->

Chaque entité se déplie en trois groupes :

- **Files** : une coche verte / barre rouge par artefact (Model, Controller, Service…) ; cliquez pour ouvrir.
- **Fields** : lus depuis le `$fillable` du modèle.
- **Relations** : extraites des méthodes de relation du modèle, affichées `belongsTo → Author`.

L'arbre **se rafraîchit tout seul** : un observateur de fichiers le garde (avec la barre de statut) synchronisé quand des API sont générées ou supprimées hors de l'extension : terminal, git pull, la branche d'un collègue.

## Actions sur une entité

Clic droit (ou icônes en ligne) sur n'importe quelle entité :

- **Add Fields to Entity…** : tapez `excerpt:text,status:enum(draft,published)` et le package crée une migration incrémentale et patch le modèle, la request, la factory et la resource en place via `--add-fields` (package ≥ 3.6). Un clic pour lancer la migration ensuite. Voir [Faire évoluer les entités](/fr/guide/evolving).
- **Regenerate File(s)…** : l'extension analyse la migration existante pour retrouver la liste des champs, puis vous laisse multi-sélectionner les artefacts à reconstruire. L'appel sous-jacent est `make:fullapi --only=…` : migration, route et enregistrement du seeder restent intacts.
- **Delete** : nettoyage complet via `delete:fullapi` : fichiers, routes, enregistrement du seeder.

## Go to Related File

`Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) depuis n'importe quel fichier généré saute vers ses voisins (du modèle au contrôleur au service au test) sans fouiller l'arborescence.
