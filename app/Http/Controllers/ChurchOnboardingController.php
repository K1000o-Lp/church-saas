<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchRequest;
use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Support\Church\OnboardingOptions;
use App\Support\Plans\PlanMatrix;
use App\Support\Plans\PlanResources;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChurchOnboardingController extends Controller
{
    public function create(Request $request): Response
    {
        $plan = PlanMatrix::defaultPlan()?->load('limits');

        return Inertia::render('onboarding/church', [
            'countries' => OnboardingOptions::countries(),
            'currencies' => OnboardingOptions::currencies(),
            'hasMemberships' => ChurchMembership::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->where('status', 'active')
                ->exists(),
            'initialPlan' => [
                'name' => $plan?->name ?? 'Comunidad',
                'currencyLimit' => $plan?->limitFor(PlanResources::CURRENCIES)?->limit_value,
            ],
        ]);
    }

    public function store(StoreChurchRequest $request, CurrentChurch $currentChurch): RedirectResponse
    {
        /** @var array{name: string, slug: string, country_code: string, timezone: string, currency_code: string, additional_currencies?: array<int, string>} $data */
        $data = $request->validated();

        $currencyLimit = PlanMatrix::defaultPlan()
            ?->load('limits')
            ->limitFor(PlanResources::CURRENCIES)
            ?->limit_value;

        $selectedCurrencies = 1 + count($data['additional_currencies'] ?? []);

        if ($currencyLimit !== null && $selectedCurrencies > $currencyLimit) {
            throw ValidationException::withMessages([
                'additional_currencies' => "El plan inicial permite {$currencyLimit} moneda(s). Podrás habilitar más al cambiar de plan.",
            ]);
        }

        $church = DB::transaction(function () use ($data, $currentChurch, $request): Church {
            $church = Church::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'country_code' => $data['country_code'],
                'timezone' => $data['timezone'],
                'currency_code' => $data['currency_code'],
                'locale' => 'es',
                'status' => 'active',
            ]);

            try {
                $currentChurch->set($church);

                ChurchCurrency::create([
                    'currency_code' => $data['currency_code'],
                    'is_default' => true,
                ]);

                foreach ($data['additional_currencies'] ?? [] as $currencyCode) {
                    ChurchCurrency::create([
                        'currency_code' => $currencyCode,
                        'is_default' => false,
                    ]);
                }
            } finally {
                $currentChurch->clear();
            }

            ChurchMembership::create([
                'church_id' => $church->getKey(),
                'user_id' => $request->user()->getAuthIdentifier(),
                'role' => 'admin',
                'status' => 'active',
            ]);

            return $church;
        });

        $request->session()->put('current_church_id', $church->getKey());

        return redirect()
            ->route('churches.show', $church)
            ->with('success', 'Iglesia creada. Tu membresía de administrador ya está activa.');
    }
}
