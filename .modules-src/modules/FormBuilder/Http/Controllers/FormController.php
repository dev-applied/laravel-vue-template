<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Support\SchemaValidator;

/**
 * Authoring. Gated by `manage-forms`.
 */
class FormController extends Controller
{
    public function __construct(private readonly SchemaValidator $validator) {}

    public function index(): JsonResponse
    {
        $forms = Form::query()
            ->withCount('submissions')
            ->latest('id')
            ->vuetifyPaginate();

        return response()->json($forms);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $data['slug'] = $this->uniqueSlug($data['name']);

        $form = Form::create($data);

        return response()->json($form, 201);
    }

    public function show(Form $form): JsonResponse
    {
        return response()->json($form->loadCount('submissions'));
    }

    public function update(Request $request, Form $form): JsonResponse
    {
        $form->update($this->validated($request, $form));

        return response()->json($form->fresh());
    }

    public function destroy(Form $form): JsonResponse
    {
        // Submissions cascade. A form with responses is worth a confirm in the
        // UI, which is why the count is on every read.
        $form->delete();

        return response()->json(['message' => 'Form deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Form $form = null): array
    {
        $data = $request->validate([
            'name'            => [$form ? 'sometimes' : 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'schema'          => [$form ? 'sometimes' : 'required', 'array', 'max:100'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'is_published'    => ['sometimes', 'boolean'],
            'is_public'       => ['sometimes', 'boolean'],
            'closes_at'       => ['nullable', 'date'],
        ]);

        if (isset($data['schema'])) {
            // Checked here rather than left to the renderer: a builder that
            // saves a broken definition produces a form that 500s for the
            // public and works fine for its author.
            $problems = $this->validator->problems($data['schema']);

            if ($problems !== []) {
                throw new AppException(implode(' ', $problems), 422);
            }

            $data['schema'] = $this->validator->normalise($data['schema']);
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        $slug = $base;
        $n    = 2;

        while (Form::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
