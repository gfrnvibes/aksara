<?php

namespace App\Services\Notifikasi;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Throwable;

class KlienExpoPush
{
    public const string ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send push notifications in batches of 100 using Expo Push API.
     *
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>, sound?: string}>  $messages
     */
    public function kirim(array $messages): int
    {
        if ($messages === []) {
            return 0;
        }

        $sent = 0;

        foreach (array_chunk($messages, 100) as $batch) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(15)
                    ->post(static::ENDPOINT, $batch);

                if (! $response->successful()) {
                    continue;
                }

                $results = $response->json('data');
                if (! is_array($results)) {
                    $sent += count($batch);

                    continue;
                }

                foreach ($results as $index => $ticket) {
                    if (! is_array($ticket)) {
                        continue;
                    }

                    if (($ticket['status'] ?? null) === 'ok') {
                        $sent++;

                        continue;
                    }

                    $errorCode = $ticket['details']['error'] ?? null;
                    if ($errorCode === 'DeviceNotRegistered' && isset($batch[$index]['to'])) {
                        PushToken::query()->where('token', $batch[$index]['to'])->delete();
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
