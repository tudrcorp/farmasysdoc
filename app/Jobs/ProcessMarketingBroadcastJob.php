<?php

namespace App\Jobs;

use App\Enums\MarketingBroadcastSendMode;
use App\Enums\MarketingBroadcastStatus;
use App\Mail\MarketingBroadcastMailable;
use App\Models\Client;
use App\Models\MarketingBroadcast;
use App\Models\MarketingBroadcastRecipient;
use App\Services\Marketing\MarketingSegmentClientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessMarketingBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MarketingBroadcast $broadcast,
    ) {}

    public function handle(MarketingSegmentClientResolver $segmentResolver): void
    {
        $broadcast = $this->broadcast->fresh();
        if ($broadcast === null) {
            return;
        }

        if (! in_array($broadcast->status, [
            MarketingBroadcastStatus::Draft,
            MarketingBroadcastStatus::Scheduled,
            MarketingBroadcastStatus::Failed,
        ], true)) {
            return;
        }

        $broadcast->update([
            'status' => MarketingBroadcastStatus::Processing,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        try {
            MarketingBroadcastRecipient::query()
                ->where('marketing_broadcast_id', $broadcast->id)
                ->delete();

            $clientIds = $this->resolveClientIds($broadcast, $segmentResolver);

            foreach ($clientIds as $clientId) {
                MarketingBroadcastRecipient::query()->create([
                    'marketing_broadcast_id' => $broadcast->id,
                    'client_id' => $clientId,
                    'email_status' => 'pending',
                    'whatsapp_status' => 'pending',
                ]);
            }

            $subject = $broadcast->subject;
            $html = $broadcast->email_html ?? '';
            if ($broadcast->emailTemplate) {
                if ($subject === null || $subject === '') {
                    $subject = $broadcast->emailTemplate->subject;
                }
                if ($html === '') {
                    $html = $broadcast->emailTemplate->body_html;
                }
            }

            $broadcast->update(['subject' => $subject]);

            $recipients = MarketingBroadcastRecipient::query()
                ->where('marketing_broadcast_id', $broadcast->id)
                ->with('client')
                ->get();

            foreach ($recipients as $recipient) {
                $client = $recipient->client;
                if ($client === null) {
                    continue;
                }

                if ($broadcast->sendsEmail()) {
                    if (filled($client->email)) {
                        try {
                            Mail::to($client->email)->send(
                                new MarketingBroadcastMailable($client, $broadcast, $html)
                            );
                            $recipient->update([
                                'email_status' => 'sent',
                                'email_sent_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            $recipient->update([
                                'email_status' => 'failed',
                                'error_message' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $recipient->update(['email_status' => 'skipped']);
                    }
                }

                if ($broadcast->sendsWhatsapp()) {
                    if (filled($client->phone)) {
                        Log::info('marketing.whatsapp.stub', [
                            'broadcast_id' => $broadcast->id,
                            'client_id' => $client->id,
                            'phone' => $client->phone,
                            'body' => $broadcast->whatsapp_body,
                        ]);
                        $recipient->update([
                            'whatsapp_status' => 'sent',
                            'whatsapp_sent_at' => now(),
                        ]);
                    } else {
                        $recipient->update(['whatsapp_status' => 'skipped']);
                    }
                }
            }

            $broadcast->update([
                'status' => MarketingBroadcastStatus::Completed,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $broadcast->update([
                'status' => MarketingBroadcastStatus::Failed,
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return list<int>
     */
    protected function resolveClientIds(MarketingBroadcast $broadcast, MarketingSegmentClientResolver $segmentResolver): array
    {
        $mode = $broadcast->send_mode;
        $query = null;

        if ($mode === MarketingBroadcastSendMode::All) {
            $query = Client::query();
        } elseif ($mode === MarketingBroadcastSendMode::Segment && $broadcast->marketing_segment_id) {
            $segment = $broadcast->segment;
            if ($segment === null) {
                return [];
            }
            $query = $segmentResolver->queryForSegment($segment);
        } elseif ($mode === MarketingBroadcastSendMode::Selected) {
            return $broadcast->selectedClients()->pluck('id')->all();
        } else {
            return [];
        }

        if ($query === null) {
            return [];
        }

        if ($broadcast->sendsEmail() || $broadcast->sendsWhatsapp()) {
            $query->where(function ($q) use ($broadcast): void {
                if ($broadcast->sendsEmail()) {
                    $q->orWhere(function ($w): void {
                        $w->whereNotNull('email')->where('email', '!=', '');
                    });
                }
                if ($broadcast->sendsWhatsapp()) {
                    $q->orWhere(function ($w): void {
                        $w->whereNotNull('phone')->where('phone', '!=', '');
                    });
                }
            });
        }

        return $query->pluck('id')->all();
    }
}
