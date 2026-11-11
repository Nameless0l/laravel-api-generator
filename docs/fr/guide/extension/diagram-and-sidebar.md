# Diagramme d'entités & sidebar

## Le diagramme d'entités

**Diagramme des entités** dessine chaque entité générée sur un canevas infini, avec ses colonnes et leurs types lus dans les migrations, clés étrangères comprises.

![Le diagramme d'entités, avec les fichiers de l'entité Comment dans l'inspecteur](/ext-diagram.png)

Les déclarations inverses (Post `hasMany` Comment et Comment `belongsTo` Post) sont fusionnées en un seul lien avec sa cardinalité, et une relation auto-référentielle se dessine en petite boucle. Survoler une carte met ses connexions en surbrillance.

Glissez le fond, faites défiler ou utilisez les flèches du clavier pour vous déplacer, et **Ctrl+molette** (ou un pincement sur le trackpad) zoome vers le curseur. Les cartes restent déplaçables à tout niveau de zoom. `Ctrl+F` (`Cmd+F` sur macOS) cherche une entité ou un champ, la minicarte montre où vous êtes, et la barre d'outils offre **Tout afficher** (`F`), **Réorganiser**, qui propose **Annuler**, et **Exporter**, en image SVG ou en diagramme Mermaid (`.mmd`).

Sélectionnez une carte pour ouvrir son inspecteur, avec les champs, les relations et les fichiers de l'entité, chaque fichier marqué à jour, retouché ou absent. De là, **Ajouter des champs** ouvre le builder sur l'entité, et **Régénérer**, **Ouvrir le modèle** et la suppression sont à un clic.

![Sélectionner une entité, puis parcourir ses champs, ses relations et ses fichiers](/ext-diagram.gif)

## L'accueil de la sidebar

La vue de la barre d'activité s'ouvre sur un panneau d'accueil.

![L'accueil de la sidebar au-dessus de l'arbre des entités](/ext-sidebar.png)

En haut, le projet avec ses versions de Laravel et de PHP et l'état du package. Quand le package manque, le bouton principal l'installe et les sources attendent, chacune disant pourquoi. Quand il date trop, la commande Composer qui le met à jour est juste là. L'engrenage ouvre les réglages de l'extension. En dessous, **Nouvelle API** ouvre le [builder](/fr/guide/extension/builder), **Générer depuis** liste les autres [sources](/fr/guide/extension/imports) (une description, la base de données, un fichier de schéma, un diagramme Mermaid, une spec OpenAPI), et **Projet** ouvre le diagramme des entités, les [actions du projet](/fr/guide/extension/quick-actions#actions-du-projet), les snippets et cette documentation.

Le bloc **Agents** indique si le mode agent de Copilot, Cursor ou un autre agent peut générer des API dans ce projet. Il installe `laravel/mcp` quand il manque et copie la commande qui ajoute le serveur à Claude Code. Dès que le projet a des [outils MCP](/fr/guide/mcp-tools), le bloc indique aussi que les agents peuvent utiliser votre API et copie la commande Claude Code de ce serveur.

Hors d'un projet Laravel, le panneau reste affiché, signale que le dossier n'a pas de fichier `artisan` et propose **Ouvrir un dossier** avec un lien vers cette documentation.

Le panneau suit le thème VS Code et la langue de l'extension (anglais ou français).

## L'arbre des entités

Sous l'accueil, la vue **Entités générées** suit tout ce que le générateur a créé. Sa barre de titre garde **Nouvelle API**, le diagramme et l'actualisation, et son menu `...` liste les sources et les outils du projet. Tant que le projet n'a aucune API générée, la vue propose d'installer le package ou de générer une première API.

![Un fichier retouché à la main, signalé dans l'arbre](/ext-tree.png)

Chaque entité se déplie en trois groupes :

- **Fichiers** : les fichiers que le package a notés dans `.api-generator/manifest.json`, requests Store et Update, enums, outils MCP et migrations de `--add-fields` compris. Cliquez sur l'un d'eux pour l'ouvrir. Un fichier retouché à la main depuis la génération est signalé, et l'entité indique combien.
- **Champs** : lus depuis le `$fillable` du modèle, ou son attribut `#[Fillable]` sur Laravel 13.
- **Relations** : extraites des méthodes de relation du modèle, affichées `belongsTo → Author`.

Les entités générées avant le manifest montrent leurs fichiers habituels. Un observateur de fichiers garde l'arbre et la barre de statut synchronisés quand des API sont générées ou supprimées hors de l'extension, depuis le terminal ou après un `git pull`.

## Actions sur une entité

Clic droit (ou les icônes en ligne) sur n'importe quelle entité :

- **Modifier l'entité…** : ouvre le [builder sur l'entité](/fr/guide/extension/builder#modifier-une-entite-generee), ses champs et ses relations verrouillés. Les champs ajoutés passent par l'écran de relecture, où la simulation `--add-fields` du package montre la migration incrémentale et les modifications du modèle, des requests, de la factory et de la resource, avec un clic pour lancer la migration ensuite. Voir [Faire évoluer les entités](/fr/guide/evolving).
- **Régénérer des fichiers…** : choisissez les fichiers à reconstruire, puis l'[écran de relecture](/fr/guide/extension/imports#l-ecran-de-relecture) montre le diff de chaque fichier avant toute écriture. Avec le package 4.1 ou plus récent, l'entité revient depuis la définition que le package a enregistrée en la générant, relations, valeurs d'enum et modificateurs de colonnes compris. Une entité générée avant la 4.1 est relue dans ses migrations, son modèle et ses enums, et l'écran le signale. L'appel sous-jacent est `make:fullapi --only=…`, donc la migration, la route et l'enregistrement du seeder restent intacts. Quand l'entité a des outils MCP, ils figurent dans la liste.
- **Supprimer l'API…** : ouvre une relecture de ce que `delete:fullapi` supprimerait, d'après la simulation du package. Chaque fichier est listé, ceux que vous avez retouchés à la main signalés, avec les entrées qui quittent `routes/api.php`, `DatabaseSeeder.php` et le serveur MCP. Rien n'est supprimé avant le clic sur le bouton rouge. Avec un package antérieur à la 3.11, une fenêtre de confirmation remplace la relecture.

Depuis la palette de commandes, ces trois commandes choisissent l'entité dans une liste qui affiche son nombre de fichiers et ceux qui ont été retouchés. **Ajouter des champs à une entité…** y reste et ouvre le même écran de modification.

## Aller au fichier lié

`Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) depuis n'importe quel fichier généré saute vers ses voisins (modèle, contrôleur, service, test) sans fouiller l'arbre. Il connaît les requests Store et Update, les enums nommés d'après leur entité et, grâce au manifest, les migrations.
