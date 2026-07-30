<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerRouteTest extends TestCase
{
    public function test_expected_named_customer_routes_are_registered(): void
    {
        $expected = [
            'customers.index',
            'customers.create',
            'customers.store',
            'customers.edit',
            'customers.update',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Route::has($name),
                "Missing named route: {$name}"
            );
        }
    }

    public function test_customer_route_group_has_exactly_five_endpoints(): void
    {
        $customerRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $name = $route->getName();

                return is_string($name) && str_starts_with($name, 'customers.');
            })
            ->values();

        $this->assertCount(5, $customerRoutes);
    }

    public function test_no_customer_destroy_route_is_registered(): void
    {
        $this->assertFalse(Route::has('customers.destroy'));
        $this->assertFalse(Route::has('customers.delete'));
    }

    public function test_customers_index_uri_matches_convention(): void
    {
        $route = Route::getRoutes()->getByName('customers.index');
        $this->assertNotNull($route);
        $this->assertSame('customers', $route->uri());
    }
}
