<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SubredditController extends Controller
{
    /**
     * Display the tracked subreddits.
     */
    public function index(): View
    {
        return view('admin.subreddits.index');
    }
}
