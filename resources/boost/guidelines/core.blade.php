## Laravel API Generator

This project uses `nameless/laravel-api-generator`. One command writes a complete REST API for an entity: model, migration, controller, service, DTO, form request, resource, policy, factory, seeder, feature and unit tests, the `apiResource` route and the seeder registration.

- Generate CRUD APIs with `php artisan make:fullapi` instead of writing these files by hand.
- Keep the entities in `api-schema.yaml` at the project root and generate from it.
- Preview before writing with `--dry-run --json`. Files edited by hand since they were generated are kept unless you pass `--force`.
- When the `laravel-api-generator` MCP server is available, use its `plan-api` and `generate-api` tools instead of the shell commands.
- Add columns to a generated entity with `--add-fields` rather than regenerating it.
- Put business logic in the generated service class and keep the controller thin.

@verbatim
<code-snippet name="Preview, then generate the API described in api-schema.yaml" lang="bash">
php artisan make:fullapi --schema=api-schema.yaml --dry-run --json
php artisan make:fullapi --schema=api-schema.yaml
php artisan migrate
</code-snippet>

<code-snippet name="Generate one entity from the command line" lang="bash">
php artisan make:fullapi Post --fields="title:string,body:text,status:enum(draft,published)" --soft-deletes
</code-snippet>

<code-snippet name="Add columns to an entity generated before" lang="bash">
php artisan make:fullapi Post --add-fields="excerpt:text"
</code-snippet>
@endverbatim
