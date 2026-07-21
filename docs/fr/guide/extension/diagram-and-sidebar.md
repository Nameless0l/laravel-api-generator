# Diagramme d'entités & sidebar

## Le diagramme d'entités

Une vue canevas de chaque entité générée et de leurs relations.


![Diagramme d'entités](/ext-diagram.png)


- Les liens de relation sont des courbes de Bézier ancrées au bord de carte le plus proche, avec flèches et cardinalités dans des pastilles lisibles.
- Survoler une carte met ses connexions en surbrillance ; les déclarations inverses (Post `hasMany` Comment + Comment `belongsTo` Post) sont fusionnées en un seul lien ; les relations auto-référentielles se dessinent en petite boucle.
- Le canevas est infini : glisser le fond ou faire défiler déplace la vue sur la grille en pointillés, dans toutes les directions. **Ctrl+molette zoome vers le curseur**, et les cartes restent déplaçables à tout niveau de zoom. La barre d'outils offre −/+/100 %/Fit.

## L'accueil de la sidebar

La vue de la barre d'activité s'ouvre sur un panneau d'accueil : chaque façon de générer reste à un clic.

- **Nouvelle API** ouvre le builder visuel.
- **Générer depuis** liste les autres sources : base existante, fichier de schéma, diagramme Mermaid.
- **Explorer** mène au diagramme d'entités, aux snippets et à cette documentation.

Le panneau suit le thème VS Code et la langue de l'extension (anglais ou français).

## L'explorateur de la sidebar

Juste en dessous, la vue **Generated Entities** suit tout ce que le générateur a créé.


![Explorateur d'entités](/ext-sidebar.png)


Chaque entité se déplie en trois groupes :

- **Files** : une coche verte / barre rouge par artefact (Model, Controller, Service…) ; cliquez pour ouvrir.
- **Fields** : lus depuis le `$fillable` du modèle.
- **Relations** : extraites des méthodes de relation du modèle, affichées `belongsTo → Author`.

Un observateur de fichiers garde l'arbre et la barre de statut synchronisés quand des API sont générées ou supprimées hors de l'extension, depuis le terminal ou après un `git pull`.

## Actions sur une entité

Clic droit (ou icônes en ligne) sur n'importe quelle entité :

- **Add Fields to Entity…** : tapez `excerpt:text,status:enum(draft,published)` et le package crée une migration incrémentale et patch le modèle, la request, la factory et la resource en place via `--add-fields`, avec un clic pour lancer la migration ensuite. Voir [Faire évoluer les entités](/fr/guide/evolving).
- **Regenerate File(s)…** : l'extension analyse la migration existante pour retrouver la liste des champs, puis vous laisse multi-sélectionner les artefacts à reconstruire. L'appel sous-jacent est `make:fullapi --only=…`, donc la migration, la route et l'enregistrement du seeder restent intacts.
- **Delete** : nettoyage complet via `delete:fullapi` (fichiers, routes, enregistrement du seeder).

## Go to Related File

`Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) depuis n'importe quel fichier généré saute vers ses voisins (du modèle au contrôleur au service au test) sans fouiller l'arborescence.
