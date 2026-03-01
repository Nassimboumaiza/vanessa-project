<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity;

class AuditService
{
    /**
     * Log an authentication event.
     */
    public function logAuthentication(string $event, ?string $guard = null, array $properties = []): void
    {
        $logName = config('activitylog.log_names.authentication', 'auth');

        activity($logName)
            ->withProperties(array_merge([
                'guard' => $guard ?? Auth::getDefaultDriver(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $properties))
            ->log($event);
    }

    /**
     * Log a user login event.
     */
    public function logLogin(int|string $userId, bool $successful = true, ?string $reason = null): void
    {
        $event = $successful ? 'user.login' : 'user.login_failed';

        $this->logAuthentication($event, null, [
            'user_id' => $userId,
            'successful' => $successful,
            'reason' => $reason,
        ]);
    }

    /**
     * Log a user logout event.
     */
    public function logLogout(int|string $userId): void
    {
        $this->logAuthentication('user.logout', null, [
            'user_id' => $userId,
        ]);
    }

    /**
     * Log a password reset request.
     */
    public function logPasswordResetRequest(string $email, bool $successful): void
    {
        $event = $successful ? 'password.reset_requested' : 'password.reset_failed';

        $this->logAuthentication($event, null, [
            'email' => $email,
            'successful' => $successful,
        ]);
    }

    /**
     * Log an admin action.
     */
    public function logAdminAction(string $action, string $description, array $properties = []): void
    {
        $logName = config('activitylog.log_names.admin', 'admin');

        activity($logName)
            ->withProperties(array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $properties))
            ->log("admin.{$action}: {$description}");
    }

    /**
     * Log a security event.
     */
    public function logSecurityEvent(string $event, string $description, array $properties = []): void
    {
        $logName = config('activitylog.log_names.system', 'system');

        activity($logName)
            ->withProperties(array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ], $properties))
            ->log("security.{$event}: {$description}");
    }

    /**
     * Log a custom event with context.
     */
    public function log(string $logName, string $event, array $properties = []): void
    {
        activity($logName)
            ->withProperties(array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $properties))
            ->log($event);
    }

    /**
     * Log a model event with custom description.
     */
    public function logModelEvent(string $logName, object $model, string $event, array $oldValues = [], array $newValues = []): void
    {
        $activity = activity($logName)
            ->performedOn($model)
            ->withProperties([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old' => $oldValues,
                'new' => $newValues,
            ]);

        if (Auth::check()) {
            $activity->causedBy(Auth::user());
        }

        $activity->log($event);
    }

    /**
     * Get recent activities for a user.
     */
    public function getUserActivities(int|string $userId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return \Spatie\Activitylog\Models\Activity::where('causer_id', $userId)
            ->where('causer_type', \App\Models\User::class)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for a subject model.
     */
    public function getModelActivities(object $model, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return \Spatie\Activitylog\Models\Activity::where('subject_type', get_class($model))
            ->where('subject_id', $model->getKey())
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Clean old activity logs.
     */
    public function cleanOldLogs(): int
    {
        $days = config('activitylog.delete_records_older_than_days', 365);

        return \Spatie\Activitylog\Models\Activity::where('created_at', '<', now()->subDays($days))->delete();
    }
}
