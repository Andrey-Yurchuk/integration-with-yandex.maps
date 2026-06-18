<?php

namespace App\Services\YandexMaps;

use App\Models\Organization;
use Illuminate\Support\Carbon;

final class BlockPolicy
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $jitterPercent,
        private readonly array $delaysMinutes,
    ) {}

    /**
     * Mark organization as blocked, increment attempts, and set cooldown
     */
    public function markBlocked(Organization $organization): Organization
    {
        $organization->blocked_attempts++;
        $delayMinutes = $this->nextDelayMinutes($organization->blocked_attempts);
        $organization->blocked_until = $this->calculateBlockedUntil($delayMinutes);
        $organization->save();

        return $organization;
    }

    /**
     * Clear blocked state after successful sync
     */
    public function clear(Organization $organization): Organization
    {
        $organization->blocked_attempts = 0;
        $organization->blocked_until = null;
        $organization->save();

        return $organization;
    }

    /**
     * Check if organization can be retried now
     */
    public function canRetry(Organization $organization): bool
    {
        if ($organization->blocked_until === null) {
            return false;
        }

        if ($organization->blocked_attempts <= 0) {
            return false;
        }

        if ($this->attemptsExceeded($organization)) {
            return false;
        }

        return $organization->blocked_until->lte(now());
    }

    /**
     * Check if max attempts exceeded
     */
    public function attemptsExceeded(Organization $organization): bool
    {
        return $organization->blocked_attempts >= $this->maxAttempts;
    }

    /**
     * Get delay in minutes for given attempt number
     */
    public function nextDelayMinutes(int $attempts): int
    {
        if ($attempts <= 0) {
            return $this->delaysMinutes[0];
        }

        $index = $attempts - 1;

        if ($index >= count($this->delaysMinutes)) {
            return $this->delaysMinutes[count($this->delaysMinutes) - 1];
        }

        return $this->delaysMinutes[$index];
    }

    /**
     * Calculate blocked_until timestamp with jitter
     */
    private function calculateBlockedUntil(int $delayMinutes): Carbon
    {
        if ($this->jitterPercent <= 0) {
            return now()->addMinutes($delayMinutes);
        }

        $jitterAmount = (int) ($delayMinutes * $this->jitterPercent / 100);
        $jitter = random_int(-$jitterAmount, $jitterAmount);

        return now()->addMinutes($delayMinutes + $jitter);
    }
}
