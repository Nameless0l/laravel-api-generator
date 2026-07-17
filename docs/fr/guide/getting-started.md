# Démarrage rapide

Laravel API Generator génère une API REST complète, structurée comme en production, depuis une seule commande artisan : modèle, migration, contrôleur, service, DTO, form request, resource, policy, factory, seeder et **tests écrits**.

## Prérequis

- PHP >= 8.2
- Laravel 10.x, 11.x ou 12.x

## Installation

```bash
composer require --dev nameless/laravel-api-generator
```

Le service provider est auto-découvert. Aucune configuration requise.

::: tip Zéro lock-in
Le générateur est une **dépendance de dev** : il ne s'exécute jamais en production (`composer install --no-dev` l'exclut), et le code généré est du Laravel pur, **sans dépendance à ce package** (pas de classes de base, pas de helpers à l'exécution). Vous pouvez même supprimer le générateur ensuite : tout continue de fonctionner.
:::

## Votre première API

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

Puis :

```bash
php artisan migrate
php artisan test
```

Les tests passent immédiatement : ils sont générés *avec leurs assertions*, pas comme des squelettes vides.

## Ce qui est généré

Une commande crée **12 fichiers** par entité et enregistre la route API :

| Couche | Fichier | Emplacement |
|--------|---------|-------------|
| Modèle | `Post.php` | `app/Models/` |
| Contrôleur | `PostController.php` | `app/Http/Controllers/` |
| Service | `PostService.php` | `app/Services/` |
| DTO | `PostDTO.php` | `app/DTO/` |
| Request | `PostRequest.php` | `app/Http/Requests/` |
| Resource | `PostResource.php` | `app/Http/Resources/` |
| Policy | `PostPolicy.php` | `app/Policies/` |
| Factory | `PostFactory.php` | `database/factories/` |
| Seeder | `PostSeeder.php` | `database/seeders/` |
| Migration | `*_create_posts_table.php` | `database/migrations/` |
| Test feature | `PostControllerTest.php` | `tests/Feature/` |
| Test unitaire | `PostServiceTest.php` | `tests/Unit/` |
| Route | entrée `apiResource` | `routes/api.php` |

## L'architecture générée

Chaque requête traverse une structure en couches propre :

```
Requête HTTP → FormRequest (validation) → Contrôleur (fin) → Service (logique métier) ⇄ DTO → Modèle → BDD
                                              ↓
                                          Resource (sérialisation) → réponse JSON
```

Le contrôleur reste fin et délègue au service :

```php
public function store(PostRequest $request)
{
    $dto = PostDTO::fromRequest($request);
    $post = $this->service->create($dto);

    return new PostResource($post);
}
```

La logique métier vit dans `PostService`, les données traversent les couches sous forme d'un `PostDTO` typé et `readonly`. Quand votre API grandit, les bons emplacements existent déjà.

## Des modèles que votre IDE comprend

Chaque modèle généré embarque un bloc PHPDoc complet : champs, colonnes FK, relations, timestamps :

```php
/**
 * @property int $id
 * @property string $title
 * @property \App\Enums\Status $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 */
class Post extends Model
```

L'autocomplétion fonctionne immédiatement dans VS Code et PhpStorm : pas besoin d'`ide-helper` pour le code généré.

## Étapes suivantes

- [La commande `make:fullapi` et ses options](/fr/guide/generating)
- [Générer depuis une base de données existante](/fr/guide/from-database)
- [Décrire toute votre API dans un schéma YAML](/fr/guide/schema-files)
- [Utiliser l'extension VS Code](/fr/guide/extension/)

<!-- VIDEO #1 (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo est en ligne, puis la placer en haut de page :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Une API Laravel complète en 30 secondes" allowfullscreen loading="lazy"></iframe>
</div>
-->
