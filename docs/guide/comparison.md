# Compared to API Platform

[API Platform](https://api-platform.com/docs/laravel/) is the reference API framework in PHP, and since its version 4 it runs on Laravel too. It solves the same problem as this package, a complete REST API over your models, in a very different way. This page sets the two side by side so you can pick the one that fits your project.

## Two approaches

With API Platform, you add the `#[ApiResource]` attribute to an Eloquent model and the API exists. API Platform registers the routes and answers every request through its own controller, state providers and processors, at runtime. Your repository holds the models and their attributes, and `api-platform/laravel` stays a production dependency.

The generator writes the code instead. Each entity gets a controller, a service, a DTO, two form requests, a resource, a policy, a migration, a factory, a seeder and two test classes, all plain Laravel. The package is a dev dependency, and nothing it generates refers to it, so the API keeps working once it is removed.

## Side by side

| | API Platform for Laravel | Laravel API Generator |
|---|---|---|
| How the API exists | `#[ApiResource]` on a model, served at runtime by API Platform | Controllers, services and the rest written into your project |
| Dependency | `api-platform/laravel` in production | `--dev`, removable once the code is generated |
| Formats | JSON-LD (Hydra), JSON:API, HAL, GraphQL | JSON through Laravel API resources, JSON:API resources as an option |
| API docs | OpenAPI generated automatically, with Swagger UI and GraphiQL | OpenAPI through [Scramble](/guide/docs-and-postman), Postman collection export |
| Pagination and filters | Built in, filters declared by attribute | Generated in each service: `filter[column]`, `sort`, `per_page` |
| Validation and authorization | Laravel form requests, gates and policies | Store and update form requests and a policy generated for each entity |
| Tests | Assertion helpers for Pest and PHPUnit, tests written by you | Feature and unit tests written for each entity, green right after generation |
| Changing behavior | Configuration, state providers and processors | Edit the generated code, or the [stubs](/guide/customizing-stubs) for the next generations |
| Adding a column | Migrate, and the API reads the new column from the database | [`--add-fields`](/guide/evolving) writes the migration and patches the model, requests, factory and resource |
| Inputs | Eloquent models | CLI flags, a YAML or JSON schema, a Mermaid diagram, an OpenAPI spec, an existing database, a description given to an AI agent |
| Also | Real-time updates with Mercure, HTTP cache with invalidation, admin and client generators | An [MCP server](/guide/mcp) for coding agents, a [VS Code extension](/guide/extension/) |

## Which one to pick

API Platform shines when the API is the product: several formats or GraphQL from the same model, linked data, real-time updates, very little code in the repository, and a team ready to learn its concepts.

The generator suits teams that want to read every line of their API in the Laravel they already know, with a service layer, DTOs and tests from the first day and no framework to depend on in production. It also fits projects that start from an existing database, a schema file or a spec, and teams whose coding agents should produce the same structure every time.
