<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = auth()->user();

        // جلب الإشعارات مرتبة من الأحدث
        $notifications = $user->notifications()->latest()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead($id)
    {
        /** @var User $user */
        $user = auth()->user();

        // البحث عن الإشعار وتحديث حقل is_read إلى 1
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->update(['is_read' => 1]);
            \Illuminate\Support\Facades\Cache::forget('layout.unread.' . $user->id);
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        /** @var User $user */
        $user = auth()->user();

        // تحديث جميع إشعارات المستخدم غير المقروءة لتصبح مقروءة (is_read = 1)
        $user->notifications()->where('is_read', 0)->update(['is_read' => 1]);
        \Illuminate\Support\Facades\Cache::forget('layout.unread.' . $user->id);

        return back()->with('success', 'تم تحديد كل الإشعارات كمقروءة.');
    }
}
