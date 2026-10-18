<?php

namespace App\Http\Controllers;

use App\Services\ApprovalInbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalInbox $inbox): View
    {
        return view('approvals.index', ['groups' => $inbox->for($request->user())]);
    }
}
