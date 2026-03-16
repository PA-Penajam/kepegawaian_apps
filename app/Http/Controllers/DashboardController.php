<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStatService $service): Response
    {
        return Inertia::render('dashboard', [
            'stats' => $service->getStats(),
        ]);
    }
}
