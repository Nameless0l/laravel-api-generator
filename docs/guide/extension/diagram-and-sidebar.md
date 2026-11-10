# Entity Diagram & Sidebar

## The entity diagram

**Entity diagram** draws every generated entity on an infinite canvas, with its columns and their types read from the migrations, foreign keys included.

![The entity diagram, with the inspector of the Loan entity](/ext-diagram.png)

Inverse declarations (Post `hasMany` Comment and Comment `belongsTo` Post) are merged into a single link with its cardinality, and a self-referential relation draws a small loop. Hovering a card highlights its connections.

Drag the background, scroll or use the arrow keys to pan, and **Ctrl+wheel** (or a trackpad pinch) zooms toward the cursor. Cards stay draggable at any zoom. `Ctrl+F` (`Cmd+F` on macOS) searches an entity or a field, the minimap shows where you are, and the toolbar has **Show all** (`F`), **Arrange**, which offers **Undo**, and **Export**, to an SVG image or a Mermaid diagram (`.mmd`).

Select a card to open its inspector, with the fields, the relations and the files of the entity, each file marked up to date, edited or missing. From there, **Add fields** opens the builder on the entity, and **Regenerate**, **Open the model** and the deletion are one click away.

![Select an entity, then browse its fields, relations and files](/ext-diagram.gif)

## The sidebar home

The activity bar view opens on a home panel.

![The sidebar home above the entity tree](/ext-sidebar.png)

At the top, the project with its Laravel and PHP versions and the state of the package. When the package is missing, the main button installs it and the sources wait, each one saying why. When it is too old, the Composer command that updates it sits right there. The gear opens the extension settings. Below, **New API** opens the [builder](/guide/extension/builder), **Generate from** lists the other [sources](/guide/extension/imports) (a description, the database, a schema file, a Mermaid diagram, an OpenAPI spec), and **Project** opens the entity diagram, the [project actions](/guide/extension/quick-actions#project-actions), the snippets and this documentation.

The **Agents** block says whether Copilot's agent mode, Cursor or another agent can generate APIs in this project. It installs `laravel/mcp` when it is missing and copies the command that adds the server to Claude Code. Once the project has [MCP tools](/guide/mcp-tools), the block also says that agents can use your API and copies the Claude Code command for that server.

Outside a Laravel project the panel stays, says that the folder has no `artisan` file and offers **Open a folder** with a link to this documentation.

The panel follows your VS Code theme and the extension's language setting (English or French).

## The entity tree

Below the home, the **Generated Entities** view tracks everything the generator created. Its title bar keeps **New API**, **Diagram** and **Refresh**, and its `...` menu lists the sources and the project tools. While the project has no generated API, the view offers to install the package or to generate a first API.

![A file edited by hand, flagged in the tree](/ext-tree.png)

Each entity expands into three groups:

- **Files**: the files the package recorded in `.api-generator/manifest.json`, Store and Update requests, enums, MCP tools and `--add-fields` migrations included. Click one to open it. A file you edited by hand since the generation is flagged, and the entity shows how many.
- **Fields**: read from the model's `$fillable`, or from its `#[Fillable]` attribute on Laravel 13.
- **Relations**: extracted from the model's relation methods, shown as `belongsTo → Author`.

Entities generated before the manifest show their conventional files. A file watcher keeps the tree and the status bar in sync when APIs are generated or deleted outside the extension, from the terminal or after a `git pull`.

## Entity actions

Right-click (or use the inline icons) on any entity:

- **Edit Entity…**: opens the [builder on the entity](/guide/extension/builder#edit-a-generated-entity), its fields and relations locked. The fields you add go through the review screen, where the package's `--add-fields` dry run shows the incremental migration and the patches to the model, the requests, the factory and the resource, with one click to run the migration after. See [Evolving Entities](/guide/evolving).
- **Regenerate File(s)…**: multi-select the artifacts to rebuild, then the [review screen](/guide/extension/imports#the-review-screen) shows the diff of every file before anything is written. With package 4.1 or later, the entity comes back from the definition the package recorded when it generated it, relations, enum values and column modifiers included. An entity generated before 4.1 is read back from its migrations, model and enums, and the screen says so. The underlying call is `make:fullapi --only=…`, so the migration, route and seeder registration are left untouched. When the entity has MCP tools, they are in the list.
- **Delete API…**: opens a review of what `delete:fullapi` would remove, from the package's dry run. Every file is listed, the ones you edited by hand called out, with the entries that leave `routes/api.php`, `DatabaseSeeder.php` and the MCP server. Nothing is deleted before you click the red button. With a package older than 3.11, a confirmation modal stands in for the review.

From the command palette, the same three commands pick the entity in a list that shows its file count and its edited files. **Add Fields to Entity…** is still there and opens the same edit screen.

## Go to Related File

`Ctrl+Alt+R` (`Cmd+Alt+R` on macOS) from any generated file jumps to its siblings (model to controller to service to test) without hunting through the tree. It knows the Store and Update requests, the enums named after their entity and, through the manifest, the migrations.
