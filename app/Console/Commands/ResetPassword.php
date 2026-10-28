<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetPassword extends Command
{
    protected $signature = 'user:reset-password {phone} {password}';
    protected $description = 'Сбросить пароль пользователя по телефону';

    public function handle(): int
    {
        $phone = User::normalizePhone($this->argument('phone'));
        $password = $this->argument('password');

        $user = User::where('phone_normalized', $phone)->first();

        if (!$user) {
            $this->error("Пользователь с телефоном {$phone} не найден.");
            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Пароль пользователя #{$user->id} ({$user->name}) обновлён на: {$password}");
        $this->line("Хеш длины: " . strlen($user->getRawOriginal('password')));
        return self::SUCCESS;
    }
}
