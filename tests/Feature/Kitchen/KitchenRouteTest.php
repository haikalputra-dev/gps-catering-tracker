<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KitchenRouteTest extends TestCase
{
    public function test_exactly_five_kitchen_routes_exist(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'kitchens.'));

        $this->assertSame(5, $routes->count(), 'Expected exactly 5 kitchen routes.');

        $names = $routes->map(fn ($r) => $r->getName())->sort()->values()->all();
        $this->assertSame([
            'kitchens.create',
            'kitchens.edit',
            'kitchens.index',
            'kitchens.store',
            'kitchens.update',
        ], $names);
    }

    public function test_no_delete_route_exists(): void
    {
        $names = collect(Route::getRoutes())
            ->map(fn ($r) => (string) $r->getName())
            ->filter();

        $this->assertFalse($names->contains('kitchens.destroy'));
        $this->assertFalse(
            collect(Route::getRoutes())->contains(
                fn ($r) => in_array('DELETE', $r->methods(), true)
                    && str_starts_with((string) $r->uri(), 'kitchens')
            )
        );
    }

    public function test_no_public_or_api_kitchen_routes(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_contains((string) $route->uri(), 'kitchens')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "Route {$route->uri()} must be authenticated.");
            $this->assertContains('active', $middleware, "Route {$route->uri()} must require active users.");
            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => str_starts_with($m, 'role:')),
                "Route {$route->uri()} must have role middleware."
            );
        }
    }

    public function test_required_middleware_is_attached(): void
    {
        $route = Route::getRoutes()->getByName('kitchens.index');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('active', $middleware);
        $this->assertContains('role:owner,staff', $middleware);
    }
}
