# Entity Diagram & Sidebar

## The entity diagram

A canvas view of every generated entity and how they relate.

<!-- SCREENSHOT: the entity diagram with a few related entities. Save as docs/public/ext-diagram.png then:
![Entity diagram](/ext-diagram.png)
-->

- Relationship links are smooth Bezier curves anchored to the nearest card edge, with arrowheads and cardinality labels in readable pills.
- Hovering a card highlights its connections; inverse declarations (Post `hasMany` Comment + Comment `belongsTo` Post) are merged into a single link; self-referential relations render as a small loop.
- It behaves like a proper canvas: **Ctrl+wheel zooms toward the cursor**, dragging the background pans, cards can be dragged at any zoom level, and the toolbar has −/+/100%/Fit controls.

## The sidebar explorer

The **Generated Entities** view in the activity bar tracks everything the generator created.

<!-- SCREENSHOT: the sidebar tree with an entity expanded. Save as docs/public/ext-sidebar.png then:
![Sidebar explorer](/ext-sidebar.png)
-->

Each entity expands into three groups:

- **Files**: a green check / red slash per artifact (Model, Controller, Service…); click to open.
- **Fields**: read from the model's `$fillable`.
- **Relations**: extracted from the model's relation methods, shown as `belongsTo → Author`.

A file watcher keeps the tree and the status bar in sync when APIs are generated or deleted outside the extension, from the terminal or after a `git pull`.

## Entity actions

Right-click (or use the inline icons) on any entity:

- **Add Fields to Entity…**: type `excerpt:text,status:enum(draft,published)` and the package creates an incremental migration and patches the model, request, factory and resource in place via `--add-fields`, with one click to run the migration after. See [Evolving Entities](/guide/evolving).
- **Regenerate File(s)…**: the extension parses the existing migration to recover the field list, then lets you multi-select which artifacts to rebuild. The underlying call is `make:fullapi --only=…`, so the migration, route and seeder registration are left untouched.
- **Delete**: full cleanup via `delete:fullapi` (files, routes, seeder registration).

## Go to Related File

`Ctrl+Alt+R` (`Cmd+Alt+R` on macOS) from any generated file jumps to its siblings (model to controller to service to test) without hunting through the tree.
