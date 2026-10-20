<?php

namespace App\Support\Plans;

final class PlanFeatures
{
    public const MULTI_CURRENCY = 'multi_currency';

    public const VOLUNTEERS = 'volunteers';

    public const PASTORAL_PIPELINE = 'pastoral_pipeline';

    public const WHITE_LABEL = 'white_label';

    public const AUTOMATION = 'automation';

    public const PAYMENTS = 'payments';

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        return [
            self::MULTI_CURRENCY => [
                'label' => 'Multi-moneda',
                'description' => 'Varias monedas habilitadas para contribuciones.',
            ],
            self::VOLUNTEERS => [
                'label' => 'Voluntarios',
                'description' => 'Perfiles, equipos y asignaciones de voluntarios.',
            ],
            self::PASTORAL_PIPELINE => [
                'label' => 'Pipeline pastoral',
                'description' => 'Etapas y seguimiento estructurado de personas.',
            ],
            self::WHITE_LABEL => [
                'label' => 'White-label',
                'description' => 'Personalización de marca de la iglesia.',
            ],
            self::AUTOMATION => [
                'label' => 'Automatización',
                'description' => 'Workflows y ejecuciones automáticas.',
            ],
            self::PAYMENTS => [
                'label' => 'Pagos en línea',
                'description' => 'Integraciones de pago y conciliación automática.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{label: string, description: string}
     */
    public static function definition(string $feature): array
    {
        return self::all()[$feature] ?? [
            'label' => $feature,
            'description' => '',
        ];
    }

    public static function label(string $feature): string
    {
        return self::definition($feature)['label'];
    }
}
