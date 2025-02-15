<?php

namespace App\Console\Commands;

use App\Models\CustomerSite;
use App\Models\MonitoringLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyUser extends Command
{
    protected $signature = 'notify-user';

    protected $description = 'Notify user for website down';

    public function handle(): void
    {
        if (empty(config('services.telegram_notifier.token'))) {
            return;
        }

        $customerSites = CustomerSite::where('is_active', 1)->get();

        foreach ($customerSites as $customerSite) {
            if (!$customerSite->canNotifyUser()) {
                continue;
            }
            
            $responseTimes = MonitoringLog::query()
                ->where('customer_site_id', $customerSite->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(['response_time', 'created_at', 'status_code']);

            $responseTimeAverage = $responseTimes->avg('response_time');

            $responseNotSuccess = MonitoringLog::query()
                ->where('customer_site_id', $customerSite->id)
                ->where('status_code', '!=', '200')
                ->orderBy('created_at', 'desc')
                ->latest();

            if ($responseNotSuccess) {
                $this->notifyUser($customerSite, $responseTimes);
                $customerSite->last_notify_user_at = Carbon::now();
                $customerSite->save();
            } else if ($responseTimeAverage >= ($customerSite->down_threshold * 0.9)) {
                $this->notifyUser($customerSite, $responseTimes);
                $customerSite->last_notify_user_at = Carbon::now();
                $customerSite->save();
            }
        }

        $this->info('Done!');
    }

    private function notifyUser(CustomerSite $customerSite, Collection $responseTimes): void
    {
        if (is_null($customerSite->owner)) {
            Log::channel('daily')->info('Missing customer site owner', $customerSite->toArray());
            return;
        }

        $telegramChatId = $customerSite->owner->telegram_chat_id;
        if (is_null($telegramChatId)) {
            Log::channel('daily')->info('Missing telegram_chat_id form owner', $customerSite->toArray());
            return;
        }

        $endpoint = 'https://api.telegram.org/bot'.config('services.telegram_notifier.token').'/sendMessage';
        $text = "";
        $text .= "Uptime: Website Down";
        $text .= "\n\n".$customerSite->name.' ('.$customerSite->url.')';
        $text .= "\n\nLast 5 response time:";
        $text .= "\n";
        foreach ($responseTimes as $responseTime) {
            $text .= $responseTime->created_at->format('H:i:s').':   '.$responseTime->response_time.' ms' . ' HTTP STATUS '.$responseTime->status_code;
            $text .= "\n";
        }
        $text .= "\nCheck here:";
        $text .= "\n".route('customer_sites.show', [$customerSite->id]);
        Http::post($endpoint, [
            'chat_id' => $telegramChatId,
            'text' => $text,
        ]);
    }
}
