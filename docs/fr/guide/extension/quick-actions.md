# API prête & actions du projet

## L'écran de l'API prête

Une fois les fichiers écrits, le panneau dit ce qui s'est passé : les fichiers écrits, les routes enregistrées avec la policy qui les protège, et le temps que cela a pris. Le manifeste garde la trace de chaque fichier. Un bouton ouvre le nouveau contrôleur, et **Nouvelle entité** ramène le builder.

![L'écran de l'API prête, après les migrations et les tests](/ext-ready.png)

Les étapes suivantes se lancent sur place, chacune avec sa progression et son résultat.

| Étape | Ce qu'elle fait |
|-------|-----------------|
| **Lancer les migrations** | `php artisan migrate`. Si `.env` manque, l'extension propose d'abord de le créer depuis `.env.example` |
| **Lancer les tests** | `php artisan test`, avec **Arrêter** pendant l'exécution, puis le nombre de tests réussis ou échoués |
| **Remplir la base** | `php artisan migrate:fresh --seed`, après un second clic qui confirme la suppression des tables. Les seeders générés étant déjà enregistrés, la base repart remplie, 10 enregistrements par entité |
| **Ouvrir la documentation de l'API** | Cherche un serveur Laravel sur les ports 8000 à 8003 ou 8080, ou lance `php artisan serve`, puis ouvre la documentation [Scramble](/fr/guide/docs-and-postman) sur `/docs/api`. Si Scramble manque, l'étape propose le `composer require` |
| **Adapter le code généré** | Publie les stubs du package dans `stubs/vendor/laravel-api-generator/`, puis ouvre leur dossier |

Une étape en échec affiche un extrait de la sortie de la commande, le journal complet à un clic, et **Relancer** la reprend. Le serveur que l'extension a démarré elle-même s'arrête à la fermeture du panneau.

## Actions du projet

**Migrations, tests, seeders** dans l'accueil de la sidebar, ou la commande **Project Actions**, ouvre les mêmes étapes à tout moment, avec les routes API de tout le projet.

## Garde-fous

### Validation des stubs

::: v-pre
Si vous avez [personnalisé des stubs](/fr/guide/customizing-stubs), l'extension lance `api-generator:validate-stubs` avant chaque génération. Un `{{placeholder}}` requis manquant, ou un stub écrit pour la 3.x, ouvre une fenêtre qui liste les fichiers fautifs et la raison, avec **Open Stubs Folder** pour les corriger ou **Generate Anyway** pour continuer en connaissance de cause. Un stub que le package ne lit plus, comme `request.stub`, n'a droit qu'à un avertissement.
:::

### Détection des dépendances

Chaque dépendance est vérifiée au moment où elle compte. Sans le package `nameless/laravel-api-generator`, l'extension propose de l'installer via Composer ; quand la version installée est trop ancienne pour la fonctionnalité demandée, elle propose un `composer update`. Les intégrations optionnelles suivent la même règle, qu'il s'agisse de `dedoc/scramble` pour la doc, de `laravel/sanctum` pour l'option Auth ou de `spatie/laravel-query-builder` pour le filtrage. Ce qui manque s'installe en un clic.

### Réparation des routes orphelines

Quand la liste des routes échoue parce que `routes/api.php` référence un contrôleur supprimé (la `ReflectionException` qui casse aussi d'autres outils Laravel), l'extension explique ce qui se passe et propose de lancer `api-generator:clean-routes`. Détails dans [Faire évoluer les entités](/fr/guide/evolving).

### Les fichiers que vous avez retouchés

Régénérer une entité garde les fichiers que vous avez modifiés à la main, et les nomme avant que quoi que ce soit ne soit écrit. Voir [Sécurité pendant la génération](/fr/guide/extension/builder#securite-pendant-la-generation) et [l'écran de relecture](/fr/guide/extension/imports#l-ecran-de-relecture).
