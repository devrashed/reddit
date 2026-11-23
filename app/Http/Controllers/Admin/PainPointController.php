<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PainPointController extends Controller
{
    /**
     * Display the discovered pain points.
     */
    public function index(): View
    {
        return view('admin.pain-points.index');
    }
}
