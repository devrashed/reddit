<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class RedditPostController extends Controller
{
    /**
     * Display the collected Reddit posts.
     */
    public function index(): View
    {
        return view('admin.reddit-posts.index');
    }
}
