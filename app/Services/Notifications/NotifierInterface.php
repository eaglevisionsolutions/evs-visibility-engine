<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Fired on content_queue state changes worth a human's attention
 * (ready_for_review, published — see CLAUDE.md Phase 2 scope). Interface
 * kept small and channel-agnostic so a Slack (or other) notifier can sit
 * behind it later without touching callers.
 */
interface NotifierInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function notify(string $event, array $context): void;
}
