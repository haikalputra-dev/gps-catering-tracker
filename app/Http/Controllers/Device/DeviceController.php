<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Domain\Device\ApiTokenGenerator;
use App\Domain\Device\DeviceAssignmentService;
use App\Domain\Device\Exceptions\CourierAlreadyBoundException;
use App\Domain\Device\Exceptions\InactiveCourierException;
use App\Domain\Device\Exceptions\InactiveDeviceException;
use App\Domain\Device\Exceptions\NotCourierRoleException;
use App\Domain\Identity\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Device\AssignDeviceRequest;
use App\Http\Requests\Device\StoreDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-facing physical device management.
 *
 * Handles the nine device admin routes:
 *   index, create, store, show, edit, update, rotateToken, assign, unassign
 *
 * Route model binding pulls the {@see Device} directly; the routes are
 * mounted behind `auth + active + role:owner`, so the controller does
 * not re-check authorisation.
 *
 * The API token is displayed exactly once — at creation and at
 * rotation — via a one-shot flash key (`token_plain`). The token is
 * `$hidden` on the model, so it will not leak through view helpers
 * that serialise arbitrary attributes.
 */
class DeviceController extends Controller
{
    public function __construct(
        private readonly ApiTokenGenerator $tokens,
        private readonly DeviceAssignmentService $assignments,
    ) {
    }

    public function index(): View
    {
        $devices = Device::query()
            ->with(['currentAssignment.courier'])
            ->orderBy('identifier')
            ->get();

        return view('devices.index', ['devices' => $devices]);
    }

    public function create(): View
    {
        return view('devices.create');
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $token = $this->tokens->generate();

        $device = Device::query()->create([
            'identifier' => $validated['identifier'],
            'model' => $validated['model'] ?? null,
            'hardware_version' => $validated['hardware_version'] ?? null,
            'api_token' => $token,
            'is_active' => (bool) $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device registered.')
            ->with('token_plain', $token);
    }

    public function show(Device $device): View
    {
        $device->load([
            'currentAssignment.courier',
            'assignments' => fn ($q) => $q
                ->with(['courier', 'assignedBy', 'unassignedBy'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id'),
        ]);

        $activeCouriers = User::query()
            ->where('role', UserRole::Courier->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('devices.show', [
            'device' => $device,
            'activeCouriers' => $activeCouriers,
        ]);
    }

    public function edit(Device $device): View
    {
        return view('devices.edit', ['device' => $device]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $validated = $request->validated();

        $device->identifier = $validated['identifier'];
        $device->model = $validated['model'] ?? null;
        $device->hardware_version = $validated['hardware_version'] ?? null;
        $device->notes = $validated['notes'] ?? null;
        $device->is_active = (bool) $validated['is_active'];
        $device->save();

        if (! $device->is_active) {
            // Deactivating a device implicitly ends any live binding so
            // it stops being counted as "current" in the admin view.
            $this->assignments->unassign(
                $device->fresh(),
                $request->user('web'),
                'device deactivated',
            );
        }

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device updated.');
    }

    public function rotateToken(Device $device): RedirectResponse
    {
        $token = DB::transaction(function () use ($device) {
            $new = $this->tokens->generate();
            $device->forceFill(['api_token' => $new])->save();

            return $new;
        });

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'API token rotated.')
            ->with('token_plain', $token);
    }

    public function assign(AssignDeviceRequest $request, Device $device): RedirectResponse
    {
        $courier = $request->courier();
        $actor = $request->user('web');
        $notes = $request->validated('notes');

        try {
            $this->assignments->assign($device, $courier, $actor, $notes);
        } catch (NotCourierRoleException | InactiveCourierException | InactiveDeviceException $e) {
            return back()->withErrors(['courier_id' => $e->getMessage()])->withInput();
        } catch (CourierAlreadyBoundException $e) {
            return back()->withErrors(['courier_id' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Courier assigned.');
    }

    public function unassign(Device $device): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = request()->user('web');

        $this->assignments->unassign($device, $actor, 'unassigned via admin UI');

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Courier unassigned.');
    }
}
