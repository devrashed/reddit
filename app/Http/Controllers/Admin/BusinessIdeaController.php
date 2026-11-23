<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BusinessIdeaController extends Controller
{
    /**
     * Display the generated business ideas.
     */
    public function index(): View
    {
        return view('admin.business-ideas.index');
    }
}
