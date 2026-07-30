<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): View
    {
        $customers = Customer::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return view('customers.index', ['customers' => $customers]);
    }

    public function create(): View
    {
        return view('customers.create', [
            'customer' => new Customer(['is_active' => true]),
            'mapConfig' => $this->mapConfig(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer created.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
            'mapConfig' => $this->mapConfig(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapConfig(): array
    {
        return [
            'defaultLatitude' => (float) config('map.default_latitude'),
            'defaultLongitude' => (float) config('map.default_longitude'),
            'defaultZoom' => (int) config('map.default_zoom'),
            'selectionZoom' => (int) config('map.selection_zoom'),
            'tileUrl' => (string) config('map.tile_url'),
            'tileAttribution' => (string) config('map.tile_attribution'),
            'tileMaxZoom' => (int) config('map.tile_max_zoom'),
        ];
    }
}
