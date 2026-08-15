<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditLogService
 *
 * Provides minimal audit log recording functionality for financial mutations (Phase 6 foundation).
 * Satisfies FT-030, FT-015, FT-016 audit record requirements without exposing sensitive data.
 */
class AuditLogService
{
    /**
     * Record an audit log entry within the active database transaction.
     *
     * @param string $action           Financial action name (e.g. 'income_updated', 'income_cancelled')
     * @param Model $auditable         Target model entity
     * @param array|null $details      Concise before/after snapshot or state details
     * @param User|int|null $user      Performing user or user ID (falls back to auth()->id() if null)
     * @return AuditLog
     */
    public function record(string $action, Model $auditable, ?array $details = null, User|int|null $user = null): AuditLog
    {
        $userId = null;
        if ($user instanceof User) {
            $userId = $user->id;
        } elseif (is_int($user)) {
            $userId = $user;
        } elseif (auth()->check()) {
            $userId = auth()->id();
        }

        return AuditLog::create([
            'action'         => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id'   => $auditable->getKey(),
            'user_id'        => $userId,
            'details'        => $details,
        ]);
    }
}
