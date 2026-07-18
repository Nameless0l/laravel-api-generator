# Actions rapides & garde-fous

## Du code généré à la doc dans le navigateur

Vous venez de générer une API ? Cliquez **Open API Docs**. L'extension vérifie que [Scramble](/fr/guide/docs-and-postman) est installé et propose le `composer require` s'il manque, cherche un serveur Laravel déjà lancé (ports 8000 à 8003, puis 8080), en démarre un sinon avec `php artisan serve`, détecte le port réel, et ouvre la documentation interactive de votre nouvelle API sur `/docs/api`. Le serveur qu'elle a démarré elle-même est arrêté à la fermeture du panneau. À aucun moment vous n'avez ouvert un terminal.


![Actions rapides](/ext-quick-actions.png)


Les autres boutons portent la même idée, l'action plus les préalables qu'elle suppose.

| Action | Ce qu'elle fait |
|--------|-----------------|
| **Run Migrations** | Lance `php artisan migrate` ; si `.env` manque, propose d'abord de le créer depuis `.env.example` |
| **Fresh + Seed** | `php artisan migrate:fresh --seed` après confirmation ; les seeders générés étant déjà enregistrés, la base repart remplie, 10 enregistrements par entité |
| **Run Tests** | `php artisan test`, ce qui exécute aussi les tests générés avec vos APIs |
| **List Routes** | `php artisan route:list --path=api` |
| **Open API Docs** | Le parcours décrit ci-dessus |
| **Customize Stubs** | Publie les stubs du package la première fois, puis propose Open Folder / Reset to Defaults |

Chaque bouton affiche sa progression, et un clic pendant l'exécution annule l'opération en tuant le process artisan.

## Garde-fous

### Validation des stubs

Si vous avez [personnalisé des stubs](/fr/guide/customizing-stubs), l'extension lance `api-generator:validate-stubs` avant chaque génération. Un `{{placeholder}}` requis qui manque déclenche un modal listant les fichiers fautifs, avec **Open Stubs Folder** pour corriger ou **Generate Anyway** pour passer outre en connaissance de cause.

### Détection des dépendances

Chaque dépendance est vérifiée au moment où elle sert. Sans le package `nameless/laravel-api-generator`, une notification propose de l'installer via Composer ; s'il est trop ancien pour la fonctionnalité demandée, elle propose `composer update`. Les intégrations optionnelles suivent la même règle, qu'il s'agisse de `dedoc/scramble` pour la doc, de `laravel/sanctum` pour l'option Auth ou de `spatie/laravel-query-builder` pour le filtrage. Ce qui manque s'installe en un clic.

### Réparation des routes orphelines

Quand **List Routes** échoue parce que `routes/api.php` référence un contrôleur supprimé (la `ReflectionException` qui casse aussi les autres outils Laravel), l'extension explique ce qui s'est passé et propose de lancer `api-generator:clean-routes`. Détails dans [Faire évoluer les entités](/fr/guide/evolving).

### Avertissements d'écrasement

Régénérer une entité existante affiche la liste complète des fichiers qui seraient écrasés avant d'écrire quoi que ce soit.
