<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin:create
        {email? : Correo de la cuenta de plataforma}
        {--name= : Nombre visible}
        {--password= : Contraseña inicial}';

    protected $description = 'Crea una cuenta exclusiva de backoffice de plataforma.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('Correo de plataforma'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Nombre visible')));
        $password = (string) ($this->option('password') ?: $this->secret('Contraseña (mínimo 12 caracteres)'));

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error('Ese correo ya pertenece a un usuario de iglesia. Usa un correo distinto.');

            return self::FAILURE;
        }

        if (PlatformAdmin::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error('Ya existe una cuenta de plataforma con ese correo.');

            return self::FAILURE;
        }

        PlatformAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->info("Cuenta de plataforma creada: {$email}");

        return self::SUCCESS;
    }
}
