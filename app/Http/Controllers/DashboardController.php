<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics): View
    {
        return view('dashboard', ['m' => $metrics->for($request->user())]);
    }
}
