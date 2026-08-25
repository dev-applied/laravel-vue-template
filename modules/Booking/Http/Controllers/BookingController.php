<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Booking\Http\Resources\BookingResource;
use Modules\Booking\Http\Resources\PublicBookingResource;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\BookingService;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);

        // is_active filtered here, not just inside the service: an inactive
        // resource must answer 404 exactly like the availability endpoint
        // does. Answering 404 in one place and 422 in the other tells a
        // stranger the resource exists.
        $resource = BookableResource::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($resource === null) {
            throw new AppException('That resource is not available.', 404);
        }

        $start = Carbon::parse($data['starts_at'])->utc();

        // The END is derived from the resource, never sent by the client — a
        // client-supplied end lets someone book a three-hour slot on a
        // thirty-minute resource.
        $end = $start->copy()->addMinutes($resource->slot_minutes);

        $booking = $this->bookings->book($resource, $start, $end, [
            'user_id' => $request->user()?->getKey(),
            'name'    => $data['name'],
            'email'   => mb_strtolower($data['email']),
            'notes'   => $data['notes'] ?? null,
        ]);

        return response()->json(new BookingResource($booking->load('resource')), 201);
    }

    /**
     * Look a booking up by its reference. Public — the reference is the secret,
     * which is why it is random rather than sequential.
     */
    public function show(string $reference): JsonResponse
    {
        $booking = Booking::query()->where('reference', $reference)->with('resource')->first();

        if ($booking === null) {
            throw new AppException('No booking found with that reference.', 404);
        }

        return response()->json(new PublicBookingResource($booking));
    }

    /**
     * Cancel by reference, with the booking's email as a second factor.
     *
     * The reference alone used to be enough, and a reference is not a secret in
     * practice: it is printed in the confirmation, quoted in support threads,
     * screenshotted, and forwarded. Requiring the address the booking was made
     * with means someone holding the whole confirmation can still cancel — which
     * is the intended flow — while someone who merely saw a reference cannot.
     *
     * Wrong email and unknown reference give the same 404: otherwise this
     * endpoint confirms which references exist.
     */
    public function cancel(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $booking = Booking::query()->where('reference', $reference)->with('resource')->first();

        if ($booking === null || ! hash_equals(mb_strtolower((string) $booking->email), mb_strtolower($data['email']))) {
            throw new AppException('No booking found with that reference.', 404);
        }

        return response()->json(new PublicBookingResource($this->bookings->cancel($booking)->load('resource')));
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['resource', 'user'])
            ->when($request->filled('resource'), fn ($q) => $q->whereHas(
                'resource',
                fn ($r) => $r->where('slug', $request->string('resource'))
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('upcoming'), fn ($q) => $q->where('starts_at', '>=', now())->holding())
            ->orderBy('starts_at')
            ->vuetifyPaginate();

        $bookings->setCollection(
            $bookings->getCollection()->map(fn (Booking $b) => new BookingResource($b))->collect()
        );

        return response()->json($bookings);
    }

    public function approve(Booking $booking): JsonResponse
    {
        return response()->json(
            new BookingResource($this->bookings->approve($booking)->load('resource'))
        );
    }
}
