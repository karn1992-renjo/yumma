<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the public XML sitemap';

    public function handle(): int
    {
        $sitemap = Sitemap::create();
        $urls = collect();

        foreach (RouteFacade::getRoutes() as $route) {
            if ($this->isPublicPage($route)) {
                $urls->put(url($route->uri()), Url::create(url($route->uri())));
            }
        }

        foreach ($this->dynamicUrls() as $dynamicUrl) {
            $urls->put($dynamicUrl->url, $dynamicUrl);
        }

        $urls->each(fn (Url $url) => $sitemap->add($url));
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info("Sitemap generated with {$urls->count()} URLs.");

        return self::SUCCESS;
    }

    private function isPublicPage(Route $route): bool
    {
        $uri = trim($route->uri(), '/');
        $name = (string) $route->getName();

        if (! in_array('GET', $route->methods(), true) && ! in_array('HEAD', $route->methods(), true)) {
            return false;
        }

        if (str_contains($uri, '{') || str_starts_with($uri, 'api/')) {
            return false;
        }

        if ($this->hasPrivateMiddleware($route)) {
            return false;
        }

        return ! str_starts_with($name, 'admin.')
            && ! str_starts_with($name, 'restaurant.')
            && ! str_starts_with($name, 'branch.')
            && ! in_array($uri, ['login', 'register', 'up'], true)
            && ! str_starts_with($uri, 'password/')
            && ! str_starts_with($uri, 'verification/')
            && ! str_starts_with($uri, 'forgot-password')
            && ! str_starts_with($uri, 'reset-password')
            && ! str_starts_with($uri, 'user/')
            && ! in_array($uri, ['two-factor-challenge'], true)
            && ! str_starts_with($uri, 'two-factor/')
            && ! str_starts_with($uri, 'sanctum/')
            && ! str_starts_with($uri, 'livewire/');
    }

    private function hasPrivateMiddleware(Route $route): bool
    {
        foreach ($route->middleware() as $middleware) {
            if (in_array($middleware, ['auth', 'auth:sanctum', 'guest'], true)) {
                return true;
            }
        }

        return false;
    }

    private function dynamicUrls(): array
    {
        $definitions = [
            [Restaurant::class, 'restaurant.show', 'id', 0.8, Url::CHANGE_FREQUENCY_DAILY],
            [Category::class, 'category.show', 'category', 0.6, Url::CHANGE_FREQUENCY_WEEKLY],
            [MenuItem::class, 'product.show', 'product', 0.6, Url::CHANGE_FREQUENCY_WEEKLY],
        ];

        return collect($definitions)
            ->filter(fn (array $definition) => RouteFacade::has($definition[1]))
            ->flatMap(function (array $definition) {
                [$modelClass, $routeName, $parameter, $priority, $frequency] = $definition;

                return $modelClass::query()->get()->map(function ($model) use ($routeName, $parameter, $priority, $frequency) {
                    $url = Url::create(route($routeName, [$parameter => $model->getRouteKey()]))
                        ->setPriority($priority)
                        ->setChangeFrequency($frequency)
                        ->setLastModificationDate($model->updated_at);

                    return (object) ['url' => $url->url, 'sitemapUrl' => $url];
                })->pluck('sitemapUrl');
            })
            ->all();
    }
}