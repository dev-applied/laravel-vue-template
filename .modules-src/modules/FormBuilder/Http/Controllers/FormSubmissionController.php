<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\FormBuilder\Support\FieldType;

/**
 * Filling a form in and reading what came back.
 */
class FormSubmissionController extends Controller
{
    /**
     * The public read: what to render. Never the whole model — a draft's
     * internal notes and its author are nobody else's business.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $form = $this->openForm($slug, $request);

        return response()->json([
            'name'           => $form->name,
            'slug'           => $form->slug,
            'description'    => $form->description,
            'schema'         => $form->fields(),
            'successMessage' => $form->success_message,
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $form = $this->openForm($slug, $request);

        $fields = $form->fields();

        // Rules are built from the SERVER's copy of the schema. Trusting a
        // schema sent by the client would let anyone drop the `required` off a
        // field, or invent one.
        $rules    = [];
        $messages = [];

        foreach ($fields as $field) {
            $key = $field['key'];

            $rules['answers.'.$key]                = FieldType::rulesFor($field);
            $messages['answers.'.$key.'.required'] = ($field['label'] ?? $key).' is required.';
        }

        $validated = Validator::make($request->all(), $rules, $messages)->validate();

        // Only declared keys are kept. Anything else the client sent is
        // discarded rather than stored — a submissions export is read by
        // people, and unexpected keys are how junk gets in front of them.
        $answers = [];

        foreach ($fields as $field) {
            $key           = $field['key'];
            $answers[$key] = $validated['answers'][$key] ?? null;
        }

        $submission = FormSubmission::create([
            'form_id' => $form->getKey(),
            'user_id' => $request->user()?->getKey(),
            'answers' => $answers,
            // Frozen at submit time. Editing the form afterwards must not
            // rewrite the meaning of answers already collected.
            'schema_snapshot' => $fields,
            'ip_address'      => $request->ip(),
        ]);

        return response()->json([
            'message' => $form->success_message ?: 'Thanks — your response has been recorded.',
            'id'      => $submission->getKey(),
        ], 201);
    }

    /**
     * Reading responses. Gated by `manage-forms`.
     */
    public function index(Form $form): JsonResponse
    {
        $submissions = $form->submissions()
            ->with('user')
            ->latest('id')
            ->vuetifyPaginate();

        $submissions->setCollection(
            $submissions->getCollection()->map(fn (FormSubmission $s) => [
                'id'        => $s->id,
                'answers'   => $s->labelled(),
                'user'      => $s->user?->only(['id', 'email']),
                'createdAt' => $s->created_at?->toIso8601String(),
            ])->collect()
        );

        return response()->json($submissions);
    }

    private function openForm(string $slug, Request $request): Form
    {
        $form = Form::query()->where('slug', $slug)->first();

        // One answer, whatever the reason.
        //
        // A 404 for "no such form" beside a 401 for "private form, sign in"
        // let an anonymous caller enumerate internal form slugs by the status
        // code alone — and the slugs are the leak: nobody needs the contents of
        // `exec-comp-review-2026` to learn something from its existence.
        //
        // The message carries the sign-in hint so a real visitor arriving
        // signed-out on a legitimate private link still knows what to do. That
        // costs the prober nothing, because they get it too.
        $unavailable = new AppException(
            'That form is not available. If you were sent a link to it, sign in and try again.',
            404
        );

        if ($form === null || ! $form->isOpen()) {
            throw $unavailable;
        }

        if (! $form->is_public && $request->user() === null) {
            throw $unavailable;
        }

        return $form;
    }
}
