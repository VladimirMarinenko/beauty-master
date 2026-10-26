<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ClientCabinetController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $masterCards = $user->myClientCards()->with('master')->get();

        return view('me.index', compact('masterCards'));
    }
}
