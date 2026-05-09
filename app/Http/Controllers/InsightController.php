<?php

namespace App\Http\Controllers;

use App\Services\InsightAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightController extends Controller
{
    public function __invoke(Request $request, InsightAnalysisService $insights): View
    {
        return view('insights.index', $insights->analyse($request->user()->company));
    }
}
