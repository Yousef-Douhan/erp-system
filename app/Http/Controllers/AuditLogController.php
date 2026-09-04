<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::with('user')->latest()->paginate(20);
        return view('audit_logs.index', compact('logs'));
    }

    public function show(AuditLog $auditLog)
    {
        return view('audit_logs.show', compact('auditLog'));
    }

    public function destroy(AuditLog $auditLog)
    {
        $auditLog->delete();

        return redirect()->route('audit-logs.index')->with('success', 'تم حذف السجل بنجاح.');
    }
}