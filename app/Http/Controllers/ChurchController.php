<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Invoice;
use App\Support\Billing\InvoicePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChurchController extends Controller
{
    public function show(Request $request, Church $church): Response
    {
        /** @var ChurchMembership $membership */
        $membership = $request->attributes->get('currentMembership');

        $canViewBilling = in_array($membership->role, ['admin', 'finance'], true);
        $billing = null;

        if ($canViewBilling) {
            Invoice::markOverdue($church);

            $billing = [
                'canView' => true,
                'invoices' => $church->invoices()
                    ->with('payments')
                    ->orderByDesc('issued_at')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get()
                    ->map(fn (Invoice $invoice): array => InvoicePresenter::church($invoice))
                    ->values(),
            ];
        }

        return Inertia::render('churches/show', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
                'countryCode' => $church->country_code,
                'currencyCode' => $church->currency_code,
                'timezone' => $church->timezone,
            ],
            'membership' => [
                'role' => $membership->role,
            ],
            'currencies' => $church->currencies()
                ->orderByDesc('is_default')
                ->orderBy('currency_code')
                ->get()
                ->map(fn (ChurchCurrency $currency): array => [
                    'code' => $currency->currency_code,
                    'isDefault' => $currency->is_default,
                ])
                ->values(),
            'billing' => $billing,
        ]);
    }
}
