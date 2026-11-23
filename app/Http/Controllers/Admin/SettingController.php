<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Display the application settings.
     */
    public function index(): View
    {
        return view('admin.settings.index');
    }
}
