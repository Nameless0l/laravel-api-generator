# Actions rapides & garde-fous

## Actions rapides

Les commandes artisan courantes, à un clic, avec des spinners pour toujours savoir quand quelque chose tourne :

<!-- CAPTURE : le bloc d'actions rapides. Enregistrer sous docs/public/ext-quick-actions.png puis :
![Actions rapides](/ext-quick-actions.png)
-->

| Action | Ce qu'elle lance |
|--------|------------------|
| **Run Migrations** | `php artisan migrate` (crée `.env` depuis `.env.example` s'il manque) |
| **Fresh + Seed** | `php artisan migrate:fresh --seed`, avec confirmation d'abord |
| **Run Tests** | `php artisan test` |
| **List Routes** | `php artisan route:list --path=api` |
| **Open API Docs** | Détecte ou démarre le serveur de dev, puis ouvre la [doc Scramble](/fr/guide/docs-and-postman) |
| **Customize Stubs** | Publie les stubs du package la première fois ; ensuite, propose Open Folder / Reset to Defaults |

**Open API Docs** gère le serveur intelligemment : scan des ports courants (8000–8003, 8080), démarrage de `php artisan serve` si aucun serveur ne tourne, détection du port réel, et arrêt du process qu'il a démarré à la fermeture du panneau.

## Garde-fous

L'extension part du principe que les choses vont mal se passer, et le prévoit.

### Validation des stubs

Si vous avez [personnalisé des stubs](/fr/guide/customizing-stubs), l'extension lance `api-generator:validate-stubs` **avant chaque génération**. Un stub auquel il manque un `{{placeholder}}` requis déclenche un modal listant les fichiers fautifs, avec **Open Stubs Folder** / **Generate Anyway** : un template cassé ne produit jamais silencieusement du code cassé.

### Détection des dépendances

Un package manquant ne casse jamais le flux en silence :

- Projet sans `nameless/laravel-api-generator` → une notification propose **Install via Composer**.
- **Open API Docs** sans `dedoc/scramble` → proposition d'installation.
- **Auth (Sanctum)** coché sans `laravel/sanctum` → proposition d'installer, ou de générer sans auth.
- Option Spatie QueryBuilder sans `spatie/laravel-query-builder` → `composer require` en un clic.
- Package installé trop ancien pour une fonctionnalité → proposition de `composer update`.

### Réparation des routes orphelines

Quand **List Routes** échoue parce que `routes/api.php` référence un contrôleur supprimé (la `ReflectionException` qui casse aussi les autres outils Laravel), l'extension explique ce qui s'est passé et propose de lancer `api-generator:clean-routes` : détails dans [Faire évoluer les entités](/fr/guide/evolving).

### Avertissements d'écrasement

Régénérer une entité existante affiche la liste complète des fichiers qui seraient écrasés avant d'écrire quoi que ce soit.
