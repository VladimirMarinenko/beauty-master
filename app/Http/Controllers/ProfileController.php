<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $workHoursInput = $data['work_hours'] ?? null;
        unset($data['work_hours']);

        $user->fill($data);

        if ($user->isMaster()) {
            if (is_array($workHoursInput)) {
                $user->work_hours = $this->buildWorkHours($workHoursInput);
            }
            if (isset($data['slot_step'])) {
                $user->slot_step = (int) $data['slot_step'];
            }
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Преобразует массив из формы в JSON-структуру work_hours.
     * Выходной день = null.
     */
    private function buildWorkHours(array $input): array
    {
        $result = [];

        for ($i = 1; $i <= 7; $i++) {
            $day = $input[$i] ?? $input[(string) $i] ?? [];
            $enabled = !empty($day['enabled']);

            if (!$enabled) {
                $result[(string) $i] = null;
                continue;
            }

            $start = $day['start'] ?? '10:00';
            $end = $day['end'] ?? '19:00';

            $breakStart = $day['break_start'] ?? null;
            $breakEnd = $day['break_end'] ?? null;

            if ($breakStart === '') $breakStart = null;
            if ($breakEnd === '') $breakEnd = null;

            // Обед учитываем только если заданы оба времени и обед внутри смены
            if (!$breakStart || !$breakEnd || $breakStart >= $breakEnd || $breakStart < $start || $breakEnd > $end) {
                $breakStart = $breakEnd = null;
            }

            $result[(string) $i] = [
                'start' => $start,
                'end' => $end,
                'break_start' => $breakStart,
                'break_end' => $breakEnd,
            ];
        }

        return $result;
    }
}
