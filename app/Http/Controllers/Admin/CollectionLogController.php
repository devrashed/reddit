<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CollectionLogController extends Controller
{
    /**
     * Display the collection run logs.
     */
    public function index(): View
    {
        return view('admin.collection-logs.index');
    }
}
