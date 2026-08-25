<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Settings\Support\SettingDefinition;
use Modules\Settings\Support\SettingRegistry;
use Modules\Settings\Support\SettingsManager;

class SettingController extends Controller
{
    /** What a secret's value is replaced with on the way out. */
    public const MASK = '********';

    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly SettingsManager $settings,
    ) {}

    /**
     * The management screen's whole payload: the declarations plus current
     * values, grouped. The UI generates itself from this rather than hardcoding
     * a form that drifts from the declarations.
     */
    public function index(): JsonResponse
    {
        $groups = [];

        foreach ($this->registry->grouped() as $group => $definitions) {
            $groups[] = [
                'group'    => $group,
                'settings' => array_map(fn (SettingDefinition $d) => [
                    'key'      => $d->key,
                    'label'    => $d->label,
                    'type'     => $d->type,
                    'help'     => $d->help,
                    'choices'  => $d->choices === [] ? null : $d->choices,
                    'isPublic' => $d->isPublic,
                    'isSecret' => $d->isSecret,
                    // A secret's real value never leaves the server. The UI
                    // shows whether one is set, not what it is.
                    'value' => $d->isSecret
                        ? ($this->settings->get($d->key) !== null ? self::MASK : null)
                        : $this->settings->get($d->key),
                ], $definitions),
            ];
        }

        return response()->json(['groups' => $groups]);
    }

    /**
     * Public settings, readable signed-out — an app name, a maintenance
     * banner. Secrets are excluded twice over.
     */
    public function publicIndex(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->publicValues()]);
    }

    public function update(Request $request): JsonResponse
    {
        $incoming = (array) $request->input('settings', []);

        if ($incoming === []) {
            throw new AppException('No settings were sent.', 422);
        }

        $rules    = [];
        $writable = [];

        foreach ($incoming as $key => $value) {
            if (! $this->registry->has($key)) {
                // Nothing undeclared is writable — that is the whole point of
                // the registry. Silently ignoring would let a typo look saved.
                throw new AppException("[{$key}] is not a known setting.", 422);
            }

            $definition = $this->registry->get($key);

            // A masked secret coming back unchanged means "leave it alone", not
            // "set the value to eight asterisks".
            if ($definition->isSecret && $value === self::MASK) {
                continue;
            }

            // Setting keys are dotted ("limits.per_page"), and an unescaped dot
            // in a rule key makes Laravel look for a NESTED field that does not
            // exist — so the rule silently matches nothing and everything
            // validates. The backslash pins it to the literal key.
            $rules['settings.'.str_replace('.', '\.', $key)] = ['nullable', ...$definition->validationRules()];
            $writable[$key]                                  = $value;
        }

        // Validate the RAW value, then cast. Casting first turns
        // "not a number" into 0 and the integer rule can never fail.
        Validator::make($request->all(), $rules)->validate();

        $accepted = [];

        foreach ($writable as $key => $value) {
            $accepted[$key] = $this->registry->get($key)->cast($value);
        }

        if ($accepted !== []) {
            $this->settings->setMany($accepted);
        }

        return $this->index();
    }
}
