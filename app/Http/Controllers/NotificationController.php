<?php

namespace App\Http\Controllers;

use App\Support\ListPageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = DB::table('notifications')
            ->orderByDesc('created_at')
            ->paginate(ListPageSize::from($request->limit))
            ->withQueryString();

        return view('mainAdmin.notifications.index', compact('notifications'));
    }
}
