<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Support\SmtpMailer;

final class EmailNotifier implements NotifierInterface
{
    public function __construct(
        private readonly SmtpMailer $mailer,
        private readonly string $recipient,
    ) {
    }

    public function notify(string $event, array $context): void
    {
        if ($this->recipient === '') {
            return;
        }

        [$subject, $body] = match ($event) {
            'content.ready_for_review' => [
                "Ready for review: {$context['keyword']}",
                "\"{$context['keyword']}\" is ready for review on {$context['site_domain']}.\n\n"
                . "Title: {$context['seo_title']}\n"
                . "Approve it via POST /api/v1/content-queue/{$context['content_queue_id']}/approve",
            ],
            'content.published' => [
                "Published: {$context['keyword']}",
                "\"{$context['keyword']}\" was published to {$context['site_domain']} as a WordPress draft.\n\n"
                . "URL: {$context['published_url']}",
            ],
            default => ["Visibility Engine: {$event}", json_encode($context)],
        };

        $this->mailer->send($this->recipient, $subject, $body);
    }
}
