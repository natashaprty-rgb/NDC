<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Users only ever see and change their own notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->notifications();
        if ($request->query('filter') === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'logs' => \App\Models\NotificationLog::query()->where('user_id', $request->user()->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Only follow links inside this application.
        if (! is_string($url) || $url === '' || str_starts_with($url, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return redirect()->route('notifications.index');
        }

        return redirect('/'.ltrim($url, '/'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('notifications.all_read'));
    }
}
