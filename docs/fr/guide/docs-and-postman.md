# Doc API & Postman

Votre API est documentée dès qu'elle existe : via Scramble pour la doc OpenAPI interactive, et des collections Postman pour votre équipe.

## Scramble : doc OpenAPI instantanée

Les contrôleurs, requests et resources générés sont écrits pour que [Scramble](https://scramble.dedoc.co) puisse les analyser **sans annotations ni configuration** :

```bash
composer require dedoc/scramble --dev
php artisan serve
```

Ouvrez `http://localhost:8000/docs/api` :

![Doc API Scramble](../../scramble-docs.png)

Ce que vous obtenez automatiquement :

- **Swagger UI interactif** : testez les endpoints depuis le navigateur avec *Send API Request*
- **Schémas auto-détectés** : `PostRequest`, `PostResource`… déduits des règles de FormRequest et de la structure des Resources
- **Les règles de validation deviennent des contraintes** : `required|string|max:255` devient un champ requis avec `<= 255 characters` dans la doc
- **Exemples de requête/réponse** : les corps JSON d'exemple sont générés pour vous
- **Endpoints groupés** : chaque entité a sa section avec toutes les opérations CRUD

![Schémas Scramble](../../scramble-schemas.png)

| URL | Description |
|-----|-------------|
| `/docs/api` | Swagger UI interactif |
| `/docs/api.json` | Spécification OpenAPI 3.x brute (JSON) |

::: tip
Scramble est une dépendance de dev : il n'affecte pas votre déploiement en production. Exactement comme le générateur lui-même.
:::

## Collection Postman

```bash
php artisan make:fullapi Post --fields="title:string" --postman
```

Exporte `postman_collection.json` à la racine du projet, au schéma Postman v2.1 :

- Un dossier par entité
- Des requêtes List, Create, Show, Update et Delete pré-configurées
- Des corps de requête d'exemple avec des valeurs adaptées aux champs
- Une variable `base_url` (par défaut `http://localhost:8000/api`)

Importez-le dans Postman et donnez-le à votre équipe frontend le matin même.
