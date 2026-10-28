<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();
        $isMaster = $user?->isMaster();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                $isMaster ? 'required' : 'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ];

        if ($isMaster) {
            $rules['work_hours'] = ['nullable', 'array'];
            $rules['work_hours.*.enabled'] = ['nullable'];
            $rules['work_hours.*.start'] = ['nullable', 'date_format:H:i'];
            $rules['work_hours.*.end'] = ['nullable', 'date_format:H:i'];
            $rules['work_hours.*.break_start'] = ['nullable', 'date_format:H:i'];
            $rules['work_hours.*.break_end'] = ['nullable', 'date_format:H:i'];
            $rules['slot_step'] = ['nullable', 'integer', \Illuminate\Validation\Rule::in(\App\Models\User::allowedSlotSteps())];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->user()?->isMaster()) return;

            $workHours = $this->input('work_hours', []);
            if (!is_array($workHours)) return;

            $labels = User::weekDayLabels();

            foreach ($workHours as $day => $data) {
                if (empty($data['enabled'])) continue;

                $label = $labels[(int) $day] ?? "День $day";
                $start = $data['start'] ?? null;
                $end = $data['end'] ?? null;
                $bs = $data['break_start'] ?? null;
                $be = $data['break_end'] ?? null;

                if ($start && $end && $end <= $start) {
                    $validator->errors()->add("work_hours.$day.end", "$label: конец смены должен быть позже начала ($start)");
                    continue;
                }

                $hasBs = !empty($bs);
                $hasBe = !empty($be);

                if ($hasBs xor $hasBe) {
                    $validator->errors()->add("work_hours.$day.break_start", "$label: укажите обе границы обеда");
                    continue;
                }

                if ($hasBs && $hasBe) {
                    if ($be <= $bs) {
                        $validator->errors()->add("work_hours.$day.break_end", "$label: конец обеда должен быть позже начала ($bs)");
                        continue;
                    }
                    if ($start && $bs < $start) {
                        $validator->errors()->add("work_hours.$day.break_start", "$label: обед должен быть внутри смены (не раньше $start)");
                    }
                    if ($end && $be > $end) {
                        $validator->errors()->add("work_hours.$day.break_end", "$label: обед должен быть внутри смены (не позже $end)");
                    }
                }
            }
        });
    }
}
