<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('mobile/account', [
            'name' => $user->display_name ?: $user->name,
            'email' => $user->email,
            'department' => $user->department?->name,
        ]);
    }
}
