<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MoreController extends Controller
{
    public function index(): View
    {
        return view('staff.more');
    }
}
