<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobilePunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PunchController extends Controller
{
    public function __construct(private readonly MobilePunchService $punch) {}

    public function store(Request $request): RedirectResponse
    {
        $result = $this->punch->punch($request->user());

        if ($result === null) {
            return back()->with('info', "You've already completed your shift for today.");
        }

        return back()->with('success', $result['type'] === 'clockin'
            ? 'Clocked in. Have a productive day!'
            : 'Clocked out. Have a great rest of your day!');
    }
}
