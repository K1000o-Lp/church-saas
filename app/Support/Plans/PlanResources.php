<?php

namespace App\Support\Plans;

final class PlanResources
{
    public const PEOPLE = 'people';

    public const ACTIVE_USERS = 'active_users';

    public const CURRENCIES = 'currencies';

    public const EVENTS_PER_MONTH = 'events_per_month';

    public const WORKFLOWS = 'workflows';

    public const EXECUTIONS_PER_MONTH = 'executions_per_month';

    public const INTEGRATIONS = 'integrations';

    /**
     * @return array<string, array{label: string, description: string, unit: string}>
     */
    public static function all(): array
    {
        return [
            self::PEOPLE => [
                'label' => 'Personas',
                'description' => 'Personas activas e inactivas; las archivadas no cuentan.',
                'unit' => 'personas',
            ],
            self::ACTIVE_USERS => [
                'label' => 'Usuarios activos',
                'description' => 'Membresías activas con acceso a la iglesia.',
                'unit' => 'usuarios',
            ],
            self::CURRENCIES => [
                'label' => 'Monedas',
                'description' => 'Monedas habilitadas para contribuciones.',
                'unit' => 'monedas',
            ],
            self::EVENTS_PER_MONTH => [
                'label' => 'Eventos por mes',
                'description' => 'Eventos creados durante el mes calendario en curso.',
                'unit' => 'eventos',
            ],
            self::WORKFLOWS => [
                'label' => 'Workflows',
                'description' => 'Workflows configurados en la iglesia.',
                'unit' => 'workflows',
            ],
            self::EXECUTIONS_PER_MONTH => [
                'label' => 'Ejecuciones por mes',
                'description' => 'Ejecuciones de workflow iniciadas en el mes en curso.',
                'unit' => 'ejecuciones',
            ],
            self::INTEGRATIONS => [
                'label' => 'Integraciones',
                'description' => 'Integraciones externas conectadas.',
                'unit' => 'integraciones',
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
     * @return array{label: string, description: string, unit: string}
     */
    public static function definition(string $resource): array
    {
        return self::all()[$resource] ?? [
            'label' => $resource,
            'description' => '',
            'unit' => 'unidades',
        ];
    }

    public static function label(string $resource): string
    {
        return self::definition($resource)['label'];
    }
}
