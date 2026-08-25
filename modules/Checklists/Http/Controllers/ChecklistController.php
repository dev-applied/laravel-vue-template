<?php

declare(strict_types=1);

namespace Modules\Checklists\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Checklists\Http\Requests\AnswerChecklistItemRequest;
use Modules\Checklists\Models\Checklist;
use Modules\Checklists\Models\ChecklistResponse;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Support\ChecklistSubjects;

class ChecklistController extends Controller
{
    public function __construct(private readonly ChecklistSubjects $subjects) {}

    /** Checklists against one subject. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string', Rule::in($this->subjects->keys())],
            'subject_id'   => ['required'],
        ]);

        $subject = $this->subjects->resolve($data['subject_type'], $data['subject_id']);

        $checklists = Checklist::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('responses')
            ->latest('id')
            ->get();

        return response()->json(['data' => $checklists]);
    }

    public function show(Checklist $checklist): JsonResponse
    {
        return response()->json([
            'data'        => $checklist->load('responses'),
            'outstanding' => $checklist->outstanding(),
        ]);
    }

    /** Start a checklist from a template, against a registered subject. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:checklist_templates,id'],
            // Validated against the REGISTRY, so this endpoint cannot be used to
            // look up an arbitrary model by class name.
            'subject_type' => ['required', 'string', Rule::in($this->subjects->keys())],
            'subject_id'   => ['required'],
        ]);

        $template = ChecklistTemplate::query()->with('items')->findOrFail($data['template_id']);

        abort_unless($template->is_active, 422, 'That checklist template is not active.');

        $subject = $this->subjects->resolve($data['subject_type'], $data['subject_id']);

        return response()->json(['data' => Checklist::instantiate($template, $subject)], 201);
    }

    public function answer(
        AnswerChecklistItemRequest $request,
        Checklist $checklist,
        ChecklistResponse $response,
    ): JsonResponse {
        abort_unless($response->checklist_id === $checklist->getKey(), 404);

        // A completed checklist is a record. Editing one after sign-off means
        // the signature no longer describes what it signed.
        abort_if($checklist->status === Checklist::STATUS_COMPLETE, 422, 'That checklist is already complete.');

        $response->fill($request->safe()->only(['status', 'note', 'file_id']));
        $response->answered_at = now();
        $response->save();

        return response()->json([
            'data'        => $checklist->fresh(['responses']),
            'outstanding' => $checklist->fresh(['responses'])->outstanding(),
        ]);
    }

    public function complete(Request $request, Checklist $checklist): JsonResponse
    {
        $data = $request->validate(['signed_by' => ['nullable', 'string', 'max:255']]);

        $checklist->load('responses');
        $outstanding = $checklist->outstanding();

        if ($outstanding !== []) {
            // 422 with EVERY reason, not the first. A checklist that reveals one
            // missing item per attempt is the reason people stop using them.
            return response()->json([
                'message'     => 'This checklist is not finished yet.',
                'outstanding' => $outstanding,
            ], 422);
        }

        $checklist->forceFill([
            'status'       => Checklist::STATUS_COMPLETE,
            'completed_at' => now(),
            'signed_by'    => $data['signed_by'] ?? $request->user()?->getAuthIdentifier(),
        ])->save();

        return response()->json(['data' => $checklist->fresh(['responses'])]);
    }
}
