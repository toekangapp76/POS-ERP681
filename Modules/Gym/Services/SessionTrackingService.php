<?php

namespace Modules\Gym\Services;

use App\Transaction;
use App\Contact;
use App\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Gym\Entities\GymAttendance;
use Modules\Gym\Entities\GymBooking;
use Modules\Gym\Entities\GymPackage;

class SessionTrackingService
{
    /**
     * Get active subscription for a member with priority ordering
     * Returns the subscription that should be used for session tracking
     *
     * @param int $contactId
     * @param int $businessId
     * @return Transaction|null
     */
    public function getActiveSubscription(int $contactId, int $businessId): ?Transaction
    {
        $today = Carbon::today();

        return Transaction::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'gym_subscription')
            ->where('status', 'final')
            ->where(function ($query) use ($today) {
                $query->where('gym_package_start_date', '<=', $today)
                    ->where(function ($q) use ($today) {
                        // Not expired by date OR lifetime (null end date)
                        $q->where('gym_package_end_date', '>=', $today)
                            ->orWhereNull('gym_package_end_date');
                    });
            })
            ->where(function ($query) {
                // Not exhausted (has remaining minutes OR remaining sessions OR unlimited)
                $query->where('gym_session_status', '!=', 'exhausted')
                    ->orWhereNull('gym_session_status');
            })
            ->where(function ($query) {
                // Has remaining minutes OR remaining sessions OR no limit (null = unlimited)
                $query->where(function ($q) {
                    $q->where('gym_remaining_minutes', '>', 0)
                        ->orWhereNull('gym_remaining_minutes');
                })->where(function ($q) {
                    $q->where('gym_remaining_sessions', '>', 0)
                        ->orWhereNull('gym_remaining_sessions');
                });
            })
            ->orderBy('gym_priority', 'desc') // Higher priority first
            ->orderBy('gym_package_start_date', 'asc') // Older subscription first
            ->first();
    }

    /**
     * Get all active subscriptions for a member
     *
     * @param int $contactId
     * @param int $businessId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllActiveSubscriptions(int $contactId, int $businessId)
    {
        $today = Carbon::today();

        return Transaction::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'gym_subscription')
            ->where('status', 'final')
            ->where(function ($query) use ($today) {
                $query->where('gym_package_start_date', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->where('gym_package_end_date', '>=', $today)
                            ->orWhereNull('gym_package_end_date');
                    });
            })
            ->orderBy('gym_priority', 'desc')
            ->orderBy('gym_package_start_date', 'asc')
            ->get();
    }

    /**
     * Process check-in for a member
     * Links attendance to active subscription
     * Supports multiple check-ins per day
     *
     * @param int $contactId
     * @param int $businessId
     * @param string $inTime
     * @param string|null $date Optional date, defaults to today in business timezone
     * @return array
     */
    public function processCheckIn(int $contactId, int $businessId, string $inTime, ?string $date = null): array
    {
        $subscription = $this->getActiveSubscription($contactId, $businessId);
        
        // Use provided date or get today in business timezone
        if ($date) {
            $today = $date;
        } else {
            $business = \App\Business::find($businessId);
            $timezone = $business->time_zone ?? config('app.timezone');
            $today = Carbon::now($timezone)->toDateString();
        }

        // Check if there's an open session (checked in but not checked out)
        $openSession = GymAttendance::where('contact_id', $contactId)
            ->whereDate('date', $today)
            ->whereNotNull('in_time')
            ->whereNull('out_time')
            ->first();

        if ($openSession) {
            // Already has an open session, update it
            $openSession->in_time = $inTime;
            $openSession->transaction_id = $subscription?->id;
            $openSession->save();
            $attendance = $openSession;
        } else {
            // Find matching booking for auto-completion
            $matchingBooking = $this->findMatchingBooking($contactId, $businessId, $today, $inTime);
            
            // Create new session (allows multiple per day)
            $attendance = GymAttendance::create([
                'contact_id' => $contactId,
                'date' => $today,
                'in_time' => $inTime,
                'transaction_id' => $subscription?->id,
                'booking_id' => $matchingBooking?->id,
                'session_deducted' => false,
            ]);
            
            // Auto-complete matching booking
            if ($matchingBooking) {
                $matchingBooking->booking_status = GymBooking::STATUS_COMPLETED;
                $matchingBooking->save();
            }
        }

        $result = [
            'success' => true,
            'attendance' => $attendance,
            'subscription' => $subscription,
            'has_active_subscription' => $subscription !== null,
            'booking_completed' => isset($matchingBooking) && $matchingBooking !== null,
        ];

        if ($subscription) {
            $package = GymPackage::find($subscription->gym_package_id);
            $result['package'] = $package;
            $result['has_session_limit'] = $package?->hasSessionLimit() ?? false;
            $result['has_session_count_limit'] = $package?->hasSessionCountLimit() ?? false;
            $result['remaining_minutes'] = $subscription->gym_remaining_minutes;
            $result['remaining_sessions'] = $subscription->gym_remaining_sessions;
            
            // Deduct session count on check-in if session count limit enabled
            if ($package && $package->hasSessionCountLimit() && !$openSession) {
                $deductResult = $this->deductSessionCount($subscription, $package);
                $result = array_merge($result, $deductResult);
            }
        }

        return $result;
    }
    
    /**
     * Find matching booking for auto-completion
     *
     * @param int $contactId
     * @param int $businessId
     * @param string $date
     * @param string $inTime
     * @return GymBooking|null
     */
    protected function findMatchingBooking(int $contactId, int $businessId, string $date, string $inTime): ?GymBooking
    {
        $checkInTime = Carbon::parse($date . ' ' . $inTime);
        
        // Find booking for this member on this date that is confirmed/pending
        // Allow 1 hour tolerance before/after check-in time
        $startTolerance = $checkInTime->copy()->subHour();
        $endTolerance = $checkInTime->copy()->addHour();
        
        return GymBooking::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->whereDate('booking_start', $date)
            ->whereIn('booking_status', [GymBooking::STATUS_CONFIRMED, GymBooking::STATUS_PENDING])
            ->where('booking_start', '>=', $startTolerance)
            ->where('booking_start', '<=', $endTolerance)
            ->orderBy('booking_start', 'asc')
            ->first();
    }
    
    /**
     * Deduct session count from subscription (on check-in)
     *
     * @param Transaction $subscription
     * @param GymPackage $package
     * @return array
     */
    protected function deductSessionCount(Transaction $subscription, GymPackage $package): array
    {
        if (!$package->hasSessionCountLimit()) {
            return ['session_count_deducted' => false];
        }
        
        $remainingSessions = $subscription->gym_remaining_sessions ?? $package->session_count_limit;
        $newRemaining = max(0, $remainingSessions - 1);
        
        $subscription->gym_used_sessions = ($subscription->gym_used_sessions ?? 0) + 1;
        $subscription->gym_remaining_sessions = $newRemaining;
        
        // Mark as exhausted if no remaining sessions AND no remaining minutes (if both limits enabled)
        $shouldExhaust = false;
        if ($newRemaining <= 0) {
            if ($package->hasSessionLimit()) {
                // Both limits enabled - exhausted only if both are depleted
                if (($subscription->gym_remaining_minutes ?? 0) <= 0) {
                    $shouldExhaust = true;
                }
            } else {
                // Only session count limit - exhausted when count is 0
                $shouldExhaust = true;
            }
        }
        
        if ($shouldExhaust) {
            $subscription->gym_session_status = 'exhausted';
        }
        
        $subscription->save();
        
        return [
            'session_count_deducted' => true,
            'remaining_sessions' => $newRemaining,
            'used_sessions' => $subscription->gym_used_sessions,
            'sessions_exhausted' => $newRemaining <= 0,
        ];
    }

    /**
     * Process check-out for a member
     * Calculates duration and deducts from subscription if session limit enabled
     * Works with the latest open session
     *
     * @param int $contactId
     * @param int $businessId
     * @param string $outTime
     * @param string|null $date Optional date, defaults to today in business timezone
     * @return array
     */
    public function processCheckOut(int $contactId, int $businessId, string $outTime, ?string $date = null): array
    {
        // Use provided date or get today in business timezone
        if ($date) {
            $today = $date;
        } else {
            $business = \App\Business::find($businessId);
            $timezone = $business->time_zone ?? config('app.timezone');
            $today = Carbon::now($timezone)->toDateString();
        }
        
        // Find the latest open session (has in_time but no out_time)
        $attendance = GymAttendance::where('contact_id', $contactId)
            ->whereDate('date', $today)
            ->whereNotNull('in_time')
            ->whereNull('out_time')
            ->orderBy('id', 'desc')
            ->first();

        if (!$attendance || !$attendance->in_time) {
            return [
                'success' => false,
                'message' => __('gym::lang.no_check_in_found'),
            ];
        }

        // Calculate duration
        $inTime = Carbon::parse($today . ' ' . $attendance->in_time);
        $outTimeCarbon = Carbon::parse($today . ' ' . $outTime);

        // Handle past midnight checkout
        if ($outTimeCarbon < $inTime) {
            $outTimeCarbon->addDay();
        }

        $durationMinutes = $outTimeCarbon->diffInMinutes($inTime);

        // Update attendance with out_time and duration
        $attendance->out_time = $outTime;
        $attendance->duration_minutes = $durationMinutes;

        $result = [
            'success' => true,
            'attendance' => $attendance,
            'duration_minutes' => $durationMinutes,
            'session_deducted' => false,
        ];

        // Process session deduction if subscription exists
        if ($attendance->transaction_id && !$attendance->session_deducted) {
            $deductionResult = $this->deductSessionTime($attendance, $durationMinutes, $businessId);
            $result = array_merge($result, $deductionResult);
        }

        $attendance->save();

        return $result;
    }

    /**
     * Deduct session time from subscription
     *
     * @param GymAttendance $attendance
     * @param int $durationMinutes
     * @param int $businessId
     * @return array
     */
    protected function deductSessionTime(GymAttendance $attendance, int $durationMinutes, int $businessId): array
    {
        $subscription = Transaction::find($attendance->transaction_id);
        
        if (!$subscription) {
            return ['session_deducted' => false];
        }

        $package = GymPackage::find($subscription->gym_package_id);

        // If no session limit, just track usage without deduction
        if (!$package || !$package->hasSessionLimit()) {
            $subscription->gym_used_minutes = ($subscription->gym_used_minutes ?? 0) + $durationMinutes;
            $subscription->save();

            $attendance->session_deducted = true;
            $attendance->session_notes = 'Usage tracked (unlimited package)';

            return [
                'session_deducted' => true,
                'unlimited_package' => true,
                'total_used_minutes' => $subscription->gym_used_minutes,
            ];
        }

        // Has session limit - deduct from remaining
        $remainingMinutes = $subscription->gym_remaining_minutes ?? $package->session_limit_minutes;
        $newRemaining = max(0, $remainingMinutes - $durationMinutes);
        $actualDeducted = $remainingMinutes - $newRemaining;
        $overtime = $durationMinutes - $actualDeducted;

        DB::beginTransaction();
        try {
            $subscription->gym_used_minutes = ($subscription->gym_used_minutes ?? 0) + $actualDeducted;
            $subscription->gym_remaining_minutes = $newRemaining;

            // Mark as exhausted if no remaining time
            if ($newRemaining <= 0) {
                $subscription->gym_session_status = 'exhausted';
            }

            $subscription->save();

            $attendance->session_deducted = true;

            $result = [
                'session_deducted' => true,
                'deducted_minutes' => $actualDeducted,
                'remaining_minutes' => $newRemaining,
                'total_used_minutes' => $subscription->gym_used_minutes,
                'package_exhausted' => $newRemaining <= 0,
            ];

            // Handle overtime - try to deduct from next subscription
            if ($overtime > 0) {
                $attendance->session_notes = "Overtime: {$overtime} minutes";
                
                $nextSubscription = $this->getActiveSubscription($attendance->contact_id, $businessId);
                
                if ($nextSubscription && $nextSubscription->id !== $subscription->id) {
                    $result['overtime_minutes'] = $overtime;
                    $result['next_subscription_id'] = $nextSubscription->id;
                    $result['overtime_handled'] = 'transferred_to_next_package';
                    
                    // Deduct overtime from next subscription
                    $this->deductFromSubscription($nextSubscription, $overtime);
                } else {
                    $result['overtime_minutes'] = $overtime;
                    $result['overtime_handled'] = 'recorded_as_overtime';
                }
            }

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Session deduction failed: ' . $e->getMessage());
            return ['session_deducted' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deduct time from a specific subscription
     *
     * @param Transaction $subscription
     * @param int $minutes
     * @return void
     */
    protected function deductFromSubscription(Transaction $subscription, int $minutes): void
    {
        $package = GymPackage::find($subscription->gym_package_id);
        
        if (!$package || !$package->hasSessionLimit()) {
            $subscription->gym_used_minutes = ($subscription->gym_used_minutes ?? 0) + $minutes;
            $subscription->save();
            return;
        }

        $remainingMinutes = $subscription->gym_remaining_minutes ?? $package->session_limit_minutes;
        $newRemaining = max(0, $remainingMinutes - $minutes);

        $subscription->gym_used_minutes = ($subscription->gym_used_minutes ?? 0) + min($minutes, $remainingMinutes);
        $subscription->gym_remaining_minutes = $newRemaining;

        if ($newRemaining <= 0) {
            $subscription->gym_session_status = 'exhausted';
        }

        $subscription->save();
    }

    /**
     * Initialize session tracking for a new subscription
     *
     * @param Transaction $subscription
     * @param GymPackage $package
     * @return void
     */
    public function initializeSessionTracking(Transaction $subscription, GymPackage $package): void
    {
        // Initialize session time limit (hours/minutes)
        if ($package->hasSessionLimit()) {
            $subscription->gym_remaining_minutes = $package->session_limit_minutes;
        } else {
            $subscription->gym_remaining_minutes = null; // Unlimited
        }
        
        // Initialize session count limit (per visit)
        if ($package->hasSessionCountLimit()) {
            $subscription->gym_remaining_sessions = $package->session_count_limit;
        } else {
            $subscription->gym_remaining_sessions = null; // Unlimited
        }
        
        $subscription->gym_session_status = 'active';
        $subscription->gym_used_minutes = 0;
        $subscription->gym_used_sessions = 0;
        $subscription->save();
    }

    /**
     * Get session status summary for a member
     *
     * @param int $contactId
     * @param int $businessId
     * @return array
     */
    public function getMemberSessionStatus(int $contactId, int $businessId): array
    {
        $subscriptions = $this->getAllActiveSubscriptions($contactId, $businessId);
        
        $totalRemainingMinutes = 0;
        $totalRemainingSessions = 0;
        $hasUnlimitedTime = false;
        $hasUnlimitedSessions = false;
        $activePackages = [];

        foreach ($subscriptions as $subscription) {
            $package = GymPackage::find($subscription->gym_package_id);
            
            if (!$package) continue;

            $packageInfo = [
                'subscription_id' => $subscription->id,
                'package_name' => $package->name,
                'start_date' => $subscription->gym_package_start_date,
                'end_date' => $subscription->gym_package_end_date,
                'has_session_limit' => $package->hasSessionLimit(),
                'has_session_count_limit' => $package->hasSessionCountLimit(),
                'used_minutes' => $subscription->gym_used_minutes ?? 0,
                'used_sessions' => $subscription->gym_used_sessions ?? 0,
                'status' => $subscription->gym_session_status ?? 'active',
            ];

            // Time limit info
            if ($package->hasSessionLimit()) {
                $packageInfo['session_limit_minutes'] = $package->session_limit_minutes;
                $packageInfo['remaining_minutes'] = $subscription->gym_remaining_minutes ?? $package->session_limit_minutes;
                $totalRemainingMinutes += $packageInfo['remaining_minutes'];
            } else {
                $packageInfo['remaining_minutes'] = null;
                $hasUnlimitedTime = true;
            }
            
            // Session count limit info
            if ($package->hasSessionCountLimit()) {
                $packageInfo['session_count_limit'] = $package->session_count_limit;
                $packageInfo['remaining_sessions'] = $subscription->gym_remaining_sessions ?? $package->session_count_limit;
                $totalRemainingSessions += $packageInfo['remaining_sessions'];
            } else {
                $packageInfo['remaining_sessions'] = null;
                $hasUnlimitedSessions = true;
            }

            $activePackages[] = $packageInfo;
        }

        return [
            'has_active_subscription' => count($activePackages) > 0,
            'has_unlimited_time' => $hasUnlimitedTime,
            'has_unlimited_sessions' => $hasUnlimitedSessions,
            'total_remaining_minutes' => $hasUnlimitedTime ? null : $totalRemainingMinutes,
            'total_remaining_sessions' => $hasUnlimitedSessions ? null : $totalRemainingSessions,
            'active_packages' => $activePackages,
        ];
    }
}
