<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

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
}
