# Outils et agents

Un éditeur, un script ou un agent IA peut demander au générateur ce qu'il écrirait avant de toucher au projet. Les deux points d'entrée ci-dessous passent par le même moteur que `make:fullapi`, si bien qu'un aperçu correspond toujours aux fichiers d'une vraie génération.

## Prévisualiser une génération

Ajoutez `--dry-run` à n'importe quelle commande `make:fullapi`. La génération s'exécute entièrement, puis liste chaque fichier qu'elle créerait ou modifierait au lieu de l'écrire.

```bash
php artisan make:fullapi Post --fields="title:string,body:text" --dry-run
```

Un fichier existant apparaît en `update` quand son contenu changerait, en `unchanged` quand le générateur écrirait exactement les mêmes octets. Avant de régénérer une entité retouchée à la main, un dry run montre les fichiers qui seraient remplacés.

## Une sortie lisible par les machines

`--json` remplace le rapport texte par un seul document JSON, sur la dernière ligne de la sortie. Avec `--dry-run`, chaque fichier arrive avec le contenu que le générateur écrirait.

```bash
php artisan make:fullapi Post --fields="title:string" --dry-run --json
```

```json
{
  "protocol": 1,
  "dryRun": true,
  "files": [
    { "path": "app/Models/Post.php", "kind": "Model", "entity": "Post", "action": "create", "content": "<?php ..." }
  ],
  "warnings": [],
  "errors": []
}
```

Les chemins sont relatifs à la racine du projet et utilisent toujours `/`. En cas d'échec, la commande sort avec le code 1 et `errors` contient un `code` stable, un `message` et parfois un `hint`.

## Envoyer un schéma sur l'entrée standard

`--schema=-` lit sur l'entrée standard un schéma au [format des fichiers de schéma](/fr/guide/schema-files), en YAML ou en JSON. Rien n'est à enregistrer dans le projet au préalable, et cela fonctionne aussi quand PHP tourne dans un conteneur.

```bash
cat api-schema.yaml | php artisan make:fullapi --schema=- --dry-run --json
```

## Garder un processus d'aperçu ouvert

Chaque appel à `php artisan` démarre toute l'application, souvent en quelques centaines de millisecondes. Un éditeur qui rafraîchit un aperçu pendant la frappe peut lancer un seul processus et le garder ouvert.

```bash
php artisan api-generator:serve --stdio
```

Il parle JSON-RPC 2.0, un message par ligne sur l'entrée et la sortie standard, et n'écrit jamais sur le disque. L'extension VS Code l'utilise pour son aperçu en direct.

| Méthode | Paramètres | Résultat |
|---|---|---|
| `handshake` | `client`, `clientVersion` | numéro de protocole, versions du package, de Laravel et de PHP, types de champs, types de relations et options pris en charge |
| `plan` | `schema` (un objet au format api-schema), `flags` (`auth`, `postman`, `only`) | `files` et `warnings`, comme dans un document `--dry-run --json` |
| `shutdown` | aucun | `null`, puis le processus s'arrête |

Le processus s'arrête aussi dès que l'entrée standard se ferme. Les erreurs reviennent sous forme d'erreurs JSON-RPC dont le champ `data` porte les mêmes `code`, `message` et `hint` qu'en ligne de commande.

## Agents IA

Avec [Laravel Boost](https://laravel.com/docs/boost), le package apprend à votre agent à générer une API au lieu d'écrire une douzaine de fichiers à la main. Lancez `php artisan boost:install`, ou `php artisan boost:update --discover` si Boost est déjà en place, et cochez `nameless/laravel-api-generator` dans la liste des packages tiers. Boost ajoute alors deux choses :

- des consignes courtes dans les fichiers que vos agents lisent au démarrage, comme `CLAUDE.md` ou `AGENTS.md` ;
- un skill `laravel-api-generator` que l'agent charge quand une tâche le demande, avec le format des schémas, le déroulé aperçu puis génération, toutes les options et le sens de la sortie JSON.

L'extension VS Code donne le même skill à GitHub Copilot, sans Boost.

Les agents qui lisent le web peuvent partir de [`llms.txt`](https://nameless0l.github.io/laravel-api-generator/llms.txt), ou charger toute la documentation en un seul fichier avec [`llms-full.txt`](https://nameless0l.github.io/laravel-api-generator/llms-full.txt). Ces deux fichiers reprennent la documentation anglaise.

`php artisan about` affiche aussi une section Laravel Api Generator avec la version installée, le protocole, le fichier de schéma détecté et l'état des stubs (publiés ou non). `php artisan about --only=laravel_api_generator --json` la renvoie en JSON.

## Stabilité

Chaque document et chaque réponse porte `"protocol": 1`. De nouveaux champs peuvent apparaître sans changer ce numéro, tandis que retirer un champ ou en changer le sens ferait passer au protocole 2. Le schéma JSON est livré avec le package, dans `resources/protocol/v1.schema.json`.
