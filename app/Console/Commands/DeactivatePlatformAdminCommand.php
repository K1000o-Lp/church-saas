<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DeactivatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin:deactivate {email : Correo de la cuenta de plataforma}';

    protected $description = 'Desactiva una cuenta de backoffice de plataforma.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $admin = PlatformAdmin::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($admin === null) {
            $this->error('No existe una cuenta de plataforma con ese correo.');

            return self::FAILURE;
        }

        $admin->forceFill(['is_active' => false])->save();

        $this->info("Cuenta de plataforma desactivada: {$admin->email}");

        return self::SUCCESS;
    }
}
