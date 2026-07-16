# API Docs & Postman

Your API is documented the moment it exists — through Scramble for interactive OpenAPI docs, and Postman collections for your team.

## Scramble: instant OpenAPI docs

The generated controllers, requests and resources are written so [Scramble](https://scramble.dedoc.co) can analyze them with **no annotations and no manual setup**:

```bash
composer require dedoc/scramble --dev
php artisan serve
```

Open `http://localhost:8000/docs/api`:

![Scramble API Docs](../scramble-docs.png)

What you get automatically:

- **Interactive Swagger UI** — test endpoints from the browser with *Send API Request*
- **Auto-detected schemas** — `PostRequest`, `PostResource`… inferred from FormRequest rules and Resource structure
- **Validation rules as constraints** — `required|string|max:255` becomes a required string with `<= 255 characters` in the docs
- **Request/response examples** — sample JSON bodies generated for you
- **Grouped endpoints** — each entity gets its own section with all CRUD operations

![Scramble Schemas](../scramble-schemas.png)

| URL | Description |
|-----|-------------|
| `/docs/api` | Interactive Swagger UI |
| `/docs/api.json` | Raw OpenAPI 3.x JSON specification |

::: tip
Scramble is a dev dependency — it won't affect your production deployment. Just like the generator itself.
:::

## Postman collection

```bash
php artisan make:fullapi Post --fields="title:string" --postman
```

Exports `postman_collection.json` at the project root, following the Postman v2.1 schema:

- A folder per entity
- Pre-configured List, Create, Show, Update and Delete requests
- Sample request bodies with appropriate field values
- A `base_url` variable (defaults to `http://localhost:8000/api`)

Import it into Postman and hand it to your frontend team the same morning.
