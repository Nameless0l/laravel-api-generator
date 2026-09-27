# Dépannage

Quand une commande artisan échoue, l'extension lit la sortie avant de vous l'afficher. Avec le package 3.9 ou plus récent, une génération répond par un code d'erreur, que l'extension lit à la place. Si elle reconnaît l'échec, la notification nomme la vraie cause et embarque des correctifs en un clic : ouvrir `.env`, lancer la commande manquante dans un terminal ou sauter vers vos stubs. Cette page reprend les mêmes cas pour quand vous voulez comprendre ou corriger vous-même.

## PHP est introuvable

L'extension appelle `php` depuis votre PATH. Si VS Code ne le trouve pas, ou choisit la mauvaise installation, fixez un chemin explicite :

```json
{ "laravelApiGenerator.phpPath": "C:/laragon/bin/php/php-8.3/php.exe" }
```

Herd, Valet, Laragon et XAMPP embarquent chacun leur PHP ; pointez le réglage vers celui que votre projet utilise.

## PHP tourne dans Sail ou Docker

Quand PHP n'existe que dans un conteneur, donnez à l'extension la commande complète plutôt qu'un chemin. Chaque élément du tableau est un argument.

::: code-group

```json [Sail]
{ "laravelApiGenerator.phpCommand": ["./vendor/bin/sail", "php"] }
```

```json [Docker Compose]
{ "laravelApiGenerator.phpCommand": ["docker", "compose", "exec", "-T", "app", "php"] }
```

:::

Remplacez `app` par le nom de votre service PHP. Si PHP manque sur la machine et que le projet embarque Sail, l'aperçu du formulaire propose ce réglage en un clic.

## L'aperçu est indisponible

L'aperçu demande le package installé dans `vendor/`, dans une version assez récente pour y répondre, et une application Laravel qui démarre. Le panneau nomme la pièce manquante et propose la commande qui la corrige. Si c'est l'application qui ne démarre pas, le message montre la fin de l'erreur PHP, et cette même erreur arrêterait une génération. Les lignes que l'application affiche d'elle-même au démarrage atterrissent dans le canal de sortie **Laravel API Generator**.

## Pas de fichier artisan

« Could not open input file: artisan » signifie que le dossier ouvert n'est pas la racine Laravel. Ouvrez le dossier qui contient `artisan` ; l'extension le détecte aussi jusqu'à deux niveaux de profondeur pour les monorepos.

## make:fullapi is not defined

Artisan ne connaît la commande qu'une fois le package Composer installé :

```bash
composer require --dev nameless/laravel-api-generator
```

L'extension détecte le package manquant et propose elle-même cette installation. C'est une dépendance dev : rien n'en tourne en production et le code généré n'en dépend pas.

## Erreurs de base de données

Le générateur touche votre base pour importer des tables, migrer ou seeder. Les échecs classiques :

| Message | Cause et correctif |
| --- | --- |
| `SQLSTATE[HY000] [2002]`, Connection refused | Le serveur est éteint ou `.env` pointe vers le mauvais hôte ou port. |
| `SQLSTATE[HY000] [1045]`, Access denied | Mauvais `DB_USERNAME` ou `DB_PASSWORD`. |
| `could not find driver` | Le driver PHP est désactivé. Activez `pdo_mysql`, `pdo_pgsql` ou `pdo_sqlite` dans `php.ini`. |
| Database file does not exist | SQLite exige que le fichier existe. Créez un `database/database.sqlite` vide. |
| Unknown database | Créez la base elle-même, puis relancez les migrations. |

Si vous avez modifié `.env` sans effet, videz la config en cache avec `php artisan config:clear`.

## Conflits de migrations

Une table `migrations` absente signifie que la base n'a jamais été initialisée ; l'extension propose `php artisan migrate:install`. Une erreur « Table already exists » signifie qu'un passage précédent a laissé des tables derrière lui :

```bash
php artisan migrate:fresh
```

::: warning
`migrate:fresh` supprime toutes les tables et rejoue toutes les migrations. Parfait sur une base de dev, destructeur partout ailleurs.
:::

## Un stub personnalisé casse la génération

La génération valide les stubs publiés avant d'écrire le moindre fichier, et nomme tout stub qui a perdu un placeholder requis ou qui date de la 3.x, avec la raison. Corrigez le placeholder, ou réinitialisez depuis le builder avec **Customize Stubs** puis **Reset to Defaults**.

## Permission denied

Le générateur écrit dans `app/`, `database/`, `routes/` et `tests/`. « Permission denied » ou `EACCES` signifie que votre utilisateur ne peut pas y écrire, ce qui arrive surtout dans les montages Docker ou WSL où le projet appartient à un autre utilisateur. Corrigez la propriété du dossier du projet et relancez.

## L'interface est dans la mauvaise langue

L'extension suit la langue d'affichage de VS Code. Forcez-la avec le réglage `laravelApiGenerator.locale`, `en` ou `fr`.
