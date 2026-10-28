<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientCabinetController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $masterCards = $user->myClientCards()->with('master')->get();

        return view('me.index', compact('masterCards'));
    }

    /**
     * Список записей клиента: /me/appointments
     */
    public function appointments(Request $request)
    {
        $user = Auth::user();

        $tab = $request->input('tab', 'upcoming');   // upcoming | past | cancelled

        $query = Appointment::where('client_user_id', $user->id)
            ->with(['master', 'service'])
            ->orderBy('start_time', $tab === 'upcoming' ? 'asc' : 'desc');

        if ($tab === 'upcoming') {
            $query->where('start_time', '>=', now())
                ->whereIn('status', ['planned', 'completed']);
        } elseif ($tab === 'past') {
            $query->where(function ($q) {
                $q->where('start_time', '<', now())
                    ->orWhere('status', 'no_show');
            })
                ->whereNotIn('status', ['cancelled']);
        } elseif ($tab === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $appointments = $query->get();

        return view('me.appointments', compact('appointments', 'tab'));
    }
}
