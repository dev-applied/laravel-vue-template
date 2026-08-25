<?php

declare(strict_types=1);

namespace Modules\Checklists\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Checklists\Models\ChecklistTemplate;

class ChecklistTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = ChecklistTemplate::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('items')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name'                      => ['required', 'string', 'max:255'],
            'description'               => ['nullable', 'string', 'max:2000'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.label'             => ['required', 'string', 'max:255'],
            'items.*.help'              => ['nullable', 'string', 'max:1000'],
            'items.*.requires_evidence' => ['sometimes', 'boolean'],
            'items.*.is_required'       => ['sometimes', 'boolean'],
        ]);

        $template = ChecklistTemplate::query()->create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        foreach (array_values($data['items']) as $position => $item) {
            $template->items()->create($item + ['position' => $position]);
        }

        return response()->json(['data' => $template->fresh('items')], 201);
    }

    /**
     * Archive rather than delete.
     *
     * Deleting a template would orphan nothing — instances carry their own copy
     * — but it also removes the definition somebody may need to explain what an
     * old inspection meant. Archiving keeps it out of the picker and out of the
     * way.
     */
    public function archive(Request $request, ChecklistTemplate $template): JsonResponse
    {
        $this->authorizeManage($request);

        $template->forceFill(['is_active' => false])->save();

        return response()->json(['data' => $template]);
    }

    /**
     * Editing templates is gated; filling one in is not.
     *
     * Falls CLOSED. Whoever may change what gets inspected is a different
     * question from who carries out the inspection, and answering it with "any
     * signed-in user" is how a compliance checklist stops meaning anything.
     */
    private function authorizeManage(Request $request): void
    {
        abort_unless(
            $request->user()?->can('manage-checklist-templates') === true,
            403,
            'You do not have permission to change checklist templates.',
        );
    }
}
