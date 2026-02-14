<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    public function fireIfNeeded(string $type, string $subject, array $data): void
    {
        if (! config('event-lens.alerts.enabled', false)) {
            return;
        }

        $allowedTypes = config('event-lens.alerts.on', []);
        if (! in_array($type, $allowedTypes, true)) {
            return;
        }

        $cooldownMinutes = (int) config('event-lens.alerts.cooldown_minutes', 15);
        $cooldownKey = "event-lens:alert-cooldown:{$type}:{$subject}";

        if (! Cache::add($cooldownKey, true, now()->addMinutes($cooldownMinutes))) {
            return;
        }

        $channels = config('event-lens.alerts.channels', []);
        $message = "[EventLens] {$type}: {$subject}";

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'slack' => $this->sendSlack($message, $data),
                    'mail' => $this->sendMail($message, $data),
                    'log' => $this->sendLog($message, $data),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::debug("EventLens: Alert channel '{$channel}' failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendSlack(string $message, array $data): void
    {
        $webhook = config('event-lens.alerts.slack_webhook');
        if (! $webhook) {
            return;
        }

        Http::post($webhook, [
            'text' => $message,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message . "\n" . json_encode($data, JSON_PRETTY_PRINT),
                    ],
                ],
            ],
        ]);
    }

    protected function sendMail(string $message, array $data): void
    {
        $to = config('event-lens.alerts.mail_to');
        if (! $to) {
            return;
        }

        $body = $message . "\n\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Mail::raw($body, function ($mail) use ($to, $message) {
            $mail->to($to)->subject($message);
        });
    }

    protected function sendLog(string $message, array $data): void
    {
        $channel = config('event-lens.alerts.log_channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();
        $logger->warning($message, $data);
    }
}
