<?php

namespace App\Http\Controllers;

use App\Support\ListPageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $messages = DB::table('chat_support')
            ->orderByDesc('created_at')
            ->paginate(ListPageSize::from($request->limit))
            ->withQueryString();

        return view('mainAdmin.chat.index', compact('messages'));
    }
}
