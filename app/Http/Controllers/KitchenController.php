<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Kitchen\StoreKitchenRequest;
use App\Http\Requests\Kitchen\UpdateKitchenRequest;
use App\Models\Kitchen;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KitchenController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): View
    {
        $kitchens = Kitchen::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return view('kitchens.index', ['kitchens' => $kitchens]);
    }

    public function create(): View
    {
        return view('kitchens.create', [
            'kitchen' => new Kitchen(['is_active' => true]),
            'mapConfig' => $this->mapConfig(),
        ]);
    }

    public function store(StoreKitchenRequest $request): RedirectResponse
    {
        Kitchen::create($request->validated());

        return redirect()
            ->route('kitchens.index')
            ->with('status', 'Kitchen created.');
    }

    public function edit(Kitchen $kitchen): View
    {
        return view('kitchens.edit', [
            'kitchen' => $kitchen,
            'mapConfig' => $this->mapConfig(),
        ]);
    }

    public function update(UpdateKitchenRequest $request, Kitchen $kitchen): RedirectResponse
    {
        $kitchen->update($request->validated());

        return redirect()
            ->route('kitchens.index')
            ->with('status', 'Kitchen updated.');
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
