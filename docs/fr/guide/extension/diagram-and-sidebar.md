# Diagramme d'entités & sidebar

## Le diagramme d'entités

**Diagramme des entités** dessine chaque entité générée sur un canevas infini, avec ses colonnes et leurs types lus dans les migrations, clés étrangères comprises.

![Le diagramme d'entités, avec l'inspecteur de l'entité Loan](/ext-diagram.png)

Les déclarations inverses (Post `hasMany` Comment et Comment `belongsTo` Post) sont fusionnées en un seul lien avec sa cardinalité, et une relation auto-référentielle se dessine en petite boucle. Survoler une carte met ses connexions en surbrillance.

Glissez le fond ou faites défiler pour vous déplacer, et **Ctrl+molette** (ou un pincement sur le trackpad) zoome vers le curseur. Les cartes restent déplaçables à tout niveau de zoom. `Ctrl+F` cherche une entité ou un champ, la minicarte montre où vous êtes, et la barre d'outils offre **Tout afficher**, **Réorganiser** et **Exporter**, en image SVG ou en diagramme Mermaid (`.mmd`).

Sélectionnez une carte pour ouvrir son inspecteur, avec les champs, les relations et les fichiers de l'entité, chaque fichier marqué à jour, retouché ou absent. De là, **Ajouter des champs**, **Régénérer**, **Ouvrir le modèle** ou supprimer l'API.

![Sélectionner une entité, puis parcourir ses champs, ses relations et ses fichiers](/ext-diagram.gif)

## L'accueil de la sidebar

La vue de la barre d'activité s'ouvre sur un panneau d'accueil.

![L'accueil de la sidebar au-dessus de l'arbre des entités](/ext-sidebar.png)

En haut, le projet avec ses versions de Laravel et de PHP et l'état du package. Quand le package manque ou date trop, la commande Composer qui corrige la situation est juste là, et l'engrenage ouvre les réglages de l'extension. En dessous, **Nouvelle API** ouvre le [builder](/fr/guide/extension/builder), **Générer depuis** liste les autres [sources](/fr/guide/extension/imports) (une description, la base de données, un fichier de schéma, un diagramme Mermaid, une spec OpenAPI), et **Projet** ouvre le diagramme des entités, les [migrations, tests et seeders](/fr/guide/extension/quick-actions#actions-du-projet), les snippets et cette documentation.

Le panneau suit le thème VS Code et la langue de l'extension (anglais ou français).

## L'arbre des entités

Sous l'accueil, la vue **Generated Entities** suit tout ce que le générateur a créé. Sa barre de titre garde **New API**, **Diagram** et **Refresh**, et son menu `...` liste les sources et les outils du projet.

![Un fichier retouché à la main, signalé dans l'arbre](/ext-tree.png)

Chaque entité se déplie en trois groupes :

- **Files** : les fichiers que le package a notés dans `.api-generator/manifest.json`, requests Store et Update, enums et migrations de `--add-fields` compris. Cliquez sur l'un d'eux pour l'ouvrir. Un fichier retouché à la main depuis la génération est signalé, et l'entité indique combien.
- **Fields** : lus depuis le `$fillable` du modèle, ou son attribut `#[Fillable]` sur Laravel 13.
- **Relations** : extraites des méthodes de relation du modèle, affichées `belongsTo → Author`.

Les entités générées avant le manifest montrent leurs fichiers habituels. Un observateur de fichiers garde l'arbre et la barre de statut synchronisés quand des API sont générées ou supprimées hors de l'extension, depuis le terminal ou après un `git pull`.

## Actions sur une entité

Clic droit (ou les icônes en ligne) sur n'importe quelle entité :

- **Add Fields to Entity…** : tapez `excerpt:text,status:enum(draft,published)` et le package crée une migration incrémentale et patche en place le modèle, les requests, la factory et la resource via `--add-fields`, avec un clic pour lancer la migration ensuite. Voir [Faire évoluer les entités](/fr/guide/evolving).
- **Regenerate File(s)…** : l'extension relit la liste des champs dans la migration existante, puis vous laisse choisir les fichiers à reconstruire. L'appel sous-jacent est `make:fullapi --only=…`, donc la migration, la route et l'enregistrement du seeder restent intacts.
- **Delete** : nettoyage complet via `delete:fullapi` (fichiers, routes, enregistrement du seeder).

## Aller au fichier lié

`Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) depuis n'importe quel fichier généré saute vers ses voisins (modèle, contrôleur, service, test) sans fouiller l'arbre. Il connaît les requests Store et Update, les enums nommés d'après leur entité et, grâce au manifest, les migrations.
