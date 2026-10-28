<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterPublicController extends Controller
{
    /**
     * Публичная страница мастера: /m/{slug}/{token}
     */
    public function show(string $slug, string $token)
    {
        $master = User::where('role', User::ROLE_MASTER)
            ->where('slug', $slug)
            ->where('invite_token', $token)
            ->firstOrFail();

        $services = $master->services()->orderBy('name')->get();

        $loggedUser = auth()->user();
        $alreadyAttached = false;

        if ($loggedUser && $loggedUser->isClient()) {
            $alreadyAttached = $loggedUser->myClientCards()
                ->where('master_id', $master->id)
                ->exists();
        }

        return view('master.public', compact('master', 'services', 'loggedUser', 'alreadyAttached'));
    }

    /**
     * Привязка ЗАЛОГИНЕННОГО клиента к мастеру.
     * POST /m/{slug}/{token}/attach
     */
    public function attach(string $slug, string $token)
    {
        $master = User::where('role', User::ROLE_MASTER)
            ->where('slug', $slug)
            ->where('invite_token', $token)
            ->firstOrFail();

        $user = Auth::user();

        if ($user->isMaster()) {
            return redirect()->route('appointments.index')
                ->with('error', 'Мастер не может привязаться к другому мастеру.');
        }

        if (!$user->isClient()) {
            abort(403);
        }

        $this->bindClientToMaster($user, $master);

        return redirect()->route('me.index')
            ->with('success', 'Вы привязаны к мастеру ' . $master->name . '.');
    }

    /**
     * Регистрация НОВОГО клиента по ссылке мастера.
     * POST /m/{slug}/{token}/register
     *
     * ВАЖНО: password передаётся «как есть» — каст 'hashed' в User
     * захеширует его автоматически. НЕ вызывать Hash::make() — иначе
     * пароль хешируется дважды, и вход не работает.
     */
    public function registerClient(Request $request, string $slug, string $token)
    {
        $master = User::where('role', User::ROLE_MASTER)
            ->where('slug', $slug)
            ->where('invite_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => [
                'required',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    $normalized = User::normalizePhone($value);

                    if (!$normalized) {
                        $fail('Введите корректный номер телефона.');
                        return;
                    }

                    $digits = preg_replace('/\D+/', '', $normalized);
                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Введите корректный номер телефона.');
                        return;
                    }

                    $exists = User::where('role', User::ROLE_CLIENT)
                        ->where('phone_normalized', $normalized)
                        ->exists();

                    if ($exists) {
                        $fail('Этот телефон уже зарегистрирован. Войдите в аккаунт и привяжитесь к мастеру.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.unique' => 'Этот телефон уже зарегистрирован. Войдите в аккаунт и привяжитесь к мастеру.',
            'email.unique' => 'Этот email уже используется другим аккаунтом.',
        ]);

        $user = DB::transaction(function () use ($validated, $master) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'password' => $validated['password'],   // каст 'hashed' сделает Hash::make
                'role' => User::ROLE_CLIENT,
            ]);

            // Автологин
            Auth::login($user);

            // Автопривязка к мастеру (к существующей карточке или новая)
            $this->bindClientToMaster($user, $master);

            return $user;
        });

        return redirect()->route('me.index')
            ->with('success', 'Добро пожаловать, ' . $user->name . '! Вы привязаны к мастеру ' . $master->name . '.');
    }

    /**
     * Привязка клиента к мастеру:
     *  - ищет существующую карточку этого мастера по телефону (8↔7),
     *  - если нашёл — привязывает, дополняет пустые поля, имя НЕ меняет;
     *  - если нет — создаёт новую карточку.
     */
    private function bindClientToMaster(User $user, User $master): Client
    {
        // Уже привязан?
        $existing = $user->myClientCards()
            ->where('master_id', $master->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Ищем карточку у мастера по телефону (свободную, без client_user_id)
        $card = null;

        if ($user->phone_normalized) {
            $variants = self::phoneVariants($user->phone_normalized);

            // Сначала точное совпадение
            $card = Client::where('master_id', $master->id)
                ->whereNull('client_user_id')
                ->whereIn('phone_normalized', $variants)
                ->first();

            // Потом частичное (на случай, если в карточке телефон записан с другим форматом)
            if (!$card) {
                $card = Client::where('master_id', $master->id)
                    ->whereNull('client_user_id')
                    ->where(function ($q) use ($variants) {
                        foreach ($variants as $v) {
                            $q->orWhere('phone_normalized', 'like', "%{$v}%");
                        }
                    })
                    ->orderBy('id')
                    ->first();
            }
        }

        return DB::transaction(function () use ($card, $user, $master) {
            if ($card) {
                // Привязываемся к существующей. Имя карточки НЕ меняем.
                $card->client_user_id = $user->id;

                if (empty($card->phone) && $user->phone) {
                    $card->phone = $user->phone;
                }
                if (empty($card->contact) && $user->email) {
                    $card->contact = $user->email;
                }
                $card->save();

                return $card;
            }

            // Создаём новую карточку у мастера
            return Client::create([
                'master_id' => $master->id,
                'client_user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'contact' => $user->email,
                'is_permanent' => false,
            ]);
        });
    }

    /**
     * Варианты телефона для поиска: 8↔7 в начале.
     */
    private static function phoneVariants(string $digits): array
    {
        if ($digits === '') {
            return [];
        }
        $variants = [$digits];
        if (strlen($digits) >= 2) {
            if ($digits[0] === '8') {
                $variants[] = '7' . substr($digits, 1);
            } elseif ($digits[0] === '7') {
                $variants[] = '8' . substr($digits, 1);
            }
        }
        return array_values(array_unique($variants));
    }
}
