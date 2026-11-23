<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AiAnalysisController extends Controller
{
    /**
     * Display the AI analysis runs.
     */
    public function index(): View
    {
        return view('admin.ai-analysis.index');
    }
}
