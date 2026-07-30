<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryCanceller;
use App\Domain\Delivery\DeliveryScheduler;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\Exceptions\ConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\InactiveCustomerException;
use App\Domain\Delivery\Exceptions\InactiveKitchenException;
use App\Domain\Delivery\Exceptions\MissingSchedulingFieldsException;
use App\Domain\Delivery\Exceptions\NotCancellableStateException;
use App\Domain\Delivery\Exceptions\NotSchedulableStateException;
use App\Http\Requests\Delivery\CancelDeliveryRequest;
use App\Http\Requests\Delivery\ScheduleDeliveryRequest;
use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
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

    public function show(Delivery $delivery): View
    {
        $delivery->load([
            'kitchen',
            'customer',
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
        ]);
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
        } catch (NotCancellableStateException $e) {
            return redirect()
                ->route('deliveries.show', $delivery)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status', 'Delivery cancelled.');
    }
}
