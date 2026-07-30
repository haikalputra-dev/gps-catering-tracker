<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryCanceller;
use App\Domain\Delivery\DeliveryCompleter;
use App\Domain\Delivery\DeliveryDispatcher;
use App\Domain\Delivery\DeliveryScheduler;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\Exceptions\ConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\CourierConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\CourierNotCourierRoleException;
use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\InactiveCustomerException;
use App\Domain\Delivery\Exceptions\InactiveKitchenException;
use App\Domain\Delivery\Exceptions\MissingCourierException;
use App\Domain\Delivery\Exceptions\MissingSchedulingFieldsException;
use App\Domain\Delivery\Exceptions\NotAssignedCourierException;
use App\Domain\Delivery\Exceptions\NotAuthorizedToCancelException;
use App\Domain\Delivery\Exceptions\NotCancellableStateException;
use App\Domain\Delivery\Exceptions\NotCompletableStateException;
use App\Domain\Delivery\Exceptions\NotDispatchableStateException;
use App\Domain\Delivery\Exceptions\NotSchedulableStateException;
use App\Domain\Identity\UserRole;
use App\Http\Requests\Delivery\CancelDeliveryRequest;
use App\Http\Requests\Delivery\ScheduleDeliveryRequest;
use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly DeliveryScheduler $scheduler,
        private readonly DeliveryCanceller $canceller,
        private readonly DeliveryDispatcher $dispatcher,
        private readonly DeliveryCompleter $completer,
    ) {
    }

    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $statusEnum = null;

        if (is_string($statusFilter) && $statusFilter !== '') {
            $statusEnum = DeliveryStatus::tryFrom($statusFilter);
        }

        $query = Delivery::query()
            ->with(['kitchen', 'customer', 'createdBy']);

        if ($statusEnum !== null) {
            $query->where('status', $statusEnum->value);
        }

        $terminalValues = array_map(
            static fn (DeliveryStatus $status): string => $status->value,
            DeliveryStatus::terminalCases(),
        );

        // Non-terminal first (0 sort key), terminal last (1). Uses raw
        // expression compatible with both MySQL and SQLite.
        $terminalList = "'".implode("','", $terminalValues)."'";

        $deliveries = $query
            ->orderByRaw("CASE WHEN status IN ({$terminalList}) THEN 1 ELSE 0 END")
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'statusFilter' => $statusEnum,
            'statusOptions' => DeliveryStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('deliveries.create', [
            'delivery' => new Delivery(),
            'kitchens' => Kitchen::query()->active()->orderBy('name')->get(),
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'couriers' => $this->activeCouriers(),
        ]);
    }

    public function store(StoreDeliveryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = DeliveryStatus::Draft->value;
        $data['created_by_user_id'] = $request->user()->getKey();

        $delivery = DB::transaction(static fn (): Delivery => Delivery::create($data));

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery draft created.');
    }

    public function show(Request $request, Delivery $delivery): View|RedirectResponse
    {
        $actor = $request->user();

        // Couriers may only view a delivery assigned to them (AR-41).
        // Owner and staff see everything. Any other role should already
        // have been blocked by middleware but we enforce it defensively.
        if ($actor instanceof User && $actor->role === UserRole::Courier) {
            $isAssigned = $delivery->courier_id !== null
                && (int) $delivery->courier_id === (int) $actor->getKey();

            if (! $isAssigned) {
                return redirect()
                    ->route('courier.dashboard')
                    ->withErrors([
                        'status' => 'You can only view deliveries assigned to you.',
                    ]);
            }
        }

        $delivery->load([
            'kitchen',
            'customer',
            'courier',
            'createdBy',
            'scheduledBy',
            'cancelledBy',
        ]);

        return view('deliveries.show', ['delivery' => $delivery]);
    }

    public function edit(Delivery $delivery): View|RedirectResponse
    {
        if ($delivery->status !== DeliveryStatus::Draft) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => 'Only draft deliveries can be edited.']);
        }

        return view('deliveries.edit', [
            'delivery' => $delivery,
            'kitchens' => Kitchen::query()->active()->orderBy('name')->get(),
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'couriers' => $this->activeCouriers(),
        ]);
    }

    /**
     * Load the active couriers for the delivery form picker (AR-37).
     *
     * Returns only users whose role is Courier and whose is_active flag
     * is true, ordered by name for a stable UI. This helper exists so
     * both create() and edit() share the same query.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    private function activeCouriers()
    {
        return User::query()
            ->where('role', UserRole::Courier->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        DB::transaction(static function () use ($delivery, $request): void {
            $delivery->update($request->validated());
        });

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery draft updated.');
    }

    public function schedule(ScheduleDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        try {
            $this->scheduler->schedule($delivery, $request->user());
        } catch (NotSchedulableStateException|MissingSchedulingFieldsException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        } catch (InactiveKitchenException|InactiveCustomerException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        } catch (MissingCourierException
                | CourierNotCourierRoleException
                | InactiveCourierException
                | CourierConcurrencyLimitReachedException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        } catch (ConcurrencyLimitReachedException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery scheduled.');
    }

    public function cancel(CancelDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        try {
            $this->canceller->cancel(
                $delivery,
                $request->user(),
                (string) $request->validated('cancellation_reason'),
            );
        } catch (NotCancellableStateException|NotAuthorizedToCancelException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery cancelled.');
    }

    /**
     * POST /deliveries/{delivery}/dispatch — courier starts the delivery.
     *
     * Route middleware `role:courier` limits this to couriers; the
     * dispatcher enforces state (`scheduled → in_transit`) and actor
     * identity (must be the assigned courier). Any domain rejection is
     * surfaced back to the show page as a flash error (AR-41).
     */
    public function dispatch(Request $request, Delivery $delivery): RedirectResponse
    {
        try {
            $this->dispatcher->dispatch($delivery, $request->user());
        } catch (NotDispatchableStateException
                | NotAssignedCourierException
                | MissingCourierException
                | InactiveCourierException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery dispatched.');
    }

    /**
     * POST /deliveries/{delivery}/mark-delivered — courier taps the
     * "Mark Delivered" button on their dashboard (AR-35).
     *
     * Route middleware `role:courier` limits this to couriers; the
     * completer enforces state (`in_transit → delivered`) and actor
     * identity. No auto-detection, no GPS proximity, no customer
     * confirmation.
     */
    public function markDelivered(Request $request, Delivery $delivery): RedirectResponse
    {
        try {
            $this->completer->complete($delivery, $request->user());
        } catch (NotCompletableStateException
                | NotAssignedCourierException
                | InactiveCourierException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery marked delivered.');
    }
}
