# Serveur MCP

Les agents de code comme Claude Code, GitHub Copilot ou Cursor peuvent appeler le générateur directement grâce au [Model Context Protocol](https://modelcontextprotocol.io). Vous décrivez une API avec vos mots, l'agent en tire un schéma, vous montre chaque fichier qu'il écrirait, puis génère toute la pile avec le même moteur que `make:fullapi`.

## Installation

Le serveur repose sur [Laravel MCP](https://laravel.com/docs/mcp), qui demande Laravel 12.41 ou plus récent. Ajoutez-le à côté du package :

```bash
composer require --dev laravel/mcp
```

Le package enregistre son serveur tout seul, il n'y a donc aucun fichier de routes à publier. Votre agent le lance avec `php artisan api-generator:mcp` quand il en a besoin.

## Brancher votre agent

Avec l'[extension VS Code](/fr/guide/extension/), rien à configurer. Dès que le package et `laravel/mcp` sont installés, le mode agent de Copilot affiche un serveur Laravel API Generator, lancé avec la commande PHP des réglages de l'extension, Sail et Docker compris.

Les autres clients ont besoin de la commande une fois. Claude Code l'enregistre en une ligne, et la plupart des autres clients lisent un fichier JSON à la racine du projet :

::: code-group

```bash [Claude Code]
claude mcp add -s project laravel-api-generator -- php artisan api-generator:mcp
```

```json [.vscode/mcp.json]
{
  "servers": {
    "laravel-api-generator": {
      "type": "stdio",
      "command": "php",
      "args": ["artisan", "api-generator:mcp"],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

```json [.cursor/mcp.json]
{
  "mcpServers": {
    "laravel-api-generator": {
      "command": "php",
      "args": ["artisan", "api-generator:mcp"]
    }
  }
}
```

:::

Avec Claude Code, `-s project` écrit `.mcp.json`, que vous pouvez committer pour que toute l'équipe profite du serveur. Tapez `/mcp` dans une session pour vérifier : le serveur apparaît connecté, avec ses outils, sa ressource et son prompt. `-s local` le garde pour vous seul. Tout autre client prend la même commande, `php artisan api-generator:mcp`, lancée depuis la racine du projet.

Quand PHP tourne dans un conteneur, préfixez la commande. Gardez `-i` avec Docker, car le serveur parle sur l'entrée standard :

::: code-group

```bash [Sail]
./vendor/bin/sail php artisan api-generator:mcp
```

```bash [Docker]
docker exec -i my-app php artisan api-generator:mcp
```

:::

## Demander une API

Demandez avec vos mots, par exemple un blog avec des articles, des catégories et des tags, où un article est brouillon ou publié. L'agent regarde ce qui existe déjà avec `list-entities`, écrit un document [api-schema](/fr/guide/schema-files) et appelle `plan-api`, qui liste chaque fichier que la génération créerait ou modifierait sans rien écrire. Une fois votre accord donné, `generate-api` les écrit, et l'agent peut lancer `php artisan migrate` puis les tests générés.

![Claude Code lance le prompt design-api : le schéma proposé et les 56 fichiers, avant toute écriture](/mcp-plan.png)

Quand le dépôt contient déjà une spec, demandez l'API décrite dans `docs/openapi.yaml`. L'agent passe ce chemin à `plan-api` au lieu d'écrire un schéma, et les avertissements lui disent quels schémas ont été écartés.

Plus tard, « ajoute un résumé aux articles » passe par `add-fields`. L'outil écrit une migration incrémentale et modifie sur place le modèle, les form requests, la factory et la resource, en gardant ce que vous y avez changé.

![Les quatre outils dans Claude Code, les deux qui ne changent rien marqués en lecture seule](/mcp-tools.png)

| Outil | Rôle |
|---|---|
| `list-entities` | Lit `.api-generator/manifest.json` et renvoie chaque entité générée avec ses fichiers, marqués intacts, modifiés ou absents. Donne aussi le fichier de schéma trouvé à la racine. |
| `plan-api` | Prévisualise les fichiers d'un document api-schema, ou d'une [spec OpenAPI](/fr/guide/openapi) du projet désignée par son chemin, avec les avertissements, comme un type de champ inconnu. Renvoie le contenu des fichiers sur demande. |
| `generate-api` | Écrit les fichiers d'un document api-schema ou d'une spec OpenAPI du projet, avec `auth`, `postman` et `only` comme en ligne de commande. |
| `add-fields` | Ajoute des colonnes à une entité générée, avec un essai à blanc possible. |

Le serveur fournit aussi un prompt `design-api`, que la plupart des clients proposent comme commande slash. Donnez-lui la description, et il guide l'agent à travers les étapes ci-dessus, en attendant votre accord avant `generate-api`.

Les résultats reprennent le document JSON de [`make:fullapi --json`](/fr/guide/integrations#une-sortie-lisible-par-les-machines), les erreurs gardent donc les mêmes codes stables et les mêmes indices. Le serveur expose aussi le JSON Schema du format api-schema, sous la ressource `api-generator://schema/api-schema.json`.

## Ce qui reste entre vos mains

Le serveur n'écrase jamais un fichier que vous avez modifié depuis sa génération. `generate-api` le laisse tel quel et le signale avec `"kept": true`. Il ne supprime aucun fichier et ne lance aucune migration. `list-entities` et `plan-api` sont marqués en lecture seule, votre client sait donc qu'ils ne changent rien.

Écraser vos retouches avec `make:fullapi --force`, supprimer une entité avec `delete:fullapi` et migrer restent en ligne de commande, entre vos mains.

## Quand le serveur ne démarre pas

Lancez la commande vous-même depuis la racine du projet :

```bash
php artisan api-generator:mcp
```

Sans `laravel/mcp`, elle le dit et s'arrête. Sinon elle attend un client sans rien afficher, signe que tout fonctionne. Arrêtez-la avec `Ctrl+C`.
