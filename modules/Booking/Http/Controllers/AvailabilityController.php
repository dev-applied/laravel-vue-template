<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Support\AvailabilityCalculator;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityCalculator $availability) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after:from'],
        ]);

        $resource = BookableResource::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($resource === null) {
            throw new AppException('That resource is not available.', 404);
        }

        $from = Carbon::parse($data['from']);
        $to   = Carbon::parse($data['to']);

        // Capped so one request cannot ask for five years of half-hour slots
        // and compute a million rows.
        if ($from->diffInDays($to) > 62) {
            throw new AppException('Ask for at most 62 days at a time.', 422);
        }

        return response()->json([
            'resource' => [
                'name'        => $resource->name,
                'slug'        => $resource->slug,
                'timezone'    => $resource->timezone,
                'slotMinutes' => $resource->slot_minutes,
                'capacity'    => $resource->capacity,
            ],
            'slots' => $this->availability->slots($resource, $from, $to),
        ]);
    }
}
