<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateChurchBrandingRequest;
use App\Models\Church;
use App\Support\Audit\DomainAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChurchBrandingController extends Controller
{
    public function edit(Church $church): Response
    {
        return Inertia::render('churches/settings/branding', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'branding' => $this->branding($church),
        ]);
    }

    public function update(UpdateChurchBrandingRequest $request, Church $church): RedirectResponse
    {
        $data = $request->validated();
        $settings = $church->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $branding['tagline'] = $data['tagline'] ?? null;
        $branding['primary_color'] = $data['primary_color'] ?? null;
        $branding['accent_color'] = $data['accent_color'] ?? null;
        $branding['service_times'] = $data['service_times'] ?? null;

        if ($request->boolean('remove_logo') && isset($branding['logo_path'])) {
            Storage::disk('public')->delete($branding['logo_path']);
            $branding['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if (isset($branding['logo_path'])) {
                Storage::disk('public')->delete($branding['logo_path']);
            }

            $branding['logo_path'] = $request->file('logo')->store('church-logos', 'public');
        }

        $settings['branding'] = $branding;
        $church->settings = $settings;
        $church->save();

        DomainAudit::recordForChurch(
            $church,
            DomainAudit::CHURCH_BRANDING_UPDATED,
            $church,
            [
                'hasLogo' => ! empty($branding['logo_path']),
                'primaryColor' => $branding['primary_color'] ?? null,
                'accentColor' => $branding['accent_color'] ?? null,
            ],
        );

        return back()->with('success', 'Marca de la iglesia actualizada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function branding(Church $church): array
    {
        $branding = $church->settings['branding'] ?? [];
        $logoPath = $branding['logo_path'] ?? null;

        return [
            'tagline' => $branding['tagline'] ?? null,
            'primaryColor' => $branding['primary_color'] ?? null,
            'accentColor' => $branding['accent_color'] ?? null,
            'serviceTimes' => $branding['service_times'] ?? null,
            'logoPath' => $logoPath,
            'logoUrl' => $logoPath === null
                ? null
                : Storage::disk('public')->url($logoPath),
        ];
    }
}
