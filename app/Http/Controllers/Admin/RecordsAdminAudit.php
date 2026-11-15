<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Writes an audit trail row for destructive or moderation actions taken in the admin area. */
trait RecordsAdminAudit
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAdminAction(Request $request, string $action, Model $subject, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
