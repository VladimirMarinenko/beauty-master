<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MasterInviteController extends Controller
{
    public function show()
    {
        $master = Auth::user();

        if (empty($master->slug)) {
            $master->slug = User::generateUniqueSlug($master->name, $master->id);
            $master->save();
        }
        if (empty($master->invite_token)) {
            $master->invite_token = User::generateUniqueInviteToken();
            $master->save();
        }

        // publicPageUrl() формирует URL с slug + token
        $inviteUrl = $master->publicPageUrl();
        $qrDataUri = null;

        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            try {
                $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode(
                        \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                            ->size(280)->margin(1)->generate($inviteUrl)
                    );
            } catch (\Throwable $e) {
                $qrDataUri = null;
            }
        }

        return view('master.invite', compact('master', 'inviteUrl', 'qrDataUri'));
    }

    public function regenerate()
    {
        $master = Auth::user();
        $master->regenerateInviteToken();

        return redirect()->route('master.invite')
            ->with('success', 'Ссылка обновлена. Старая больше не работает.');
    }
}
