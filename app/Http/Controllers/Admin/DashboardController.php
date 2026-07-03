<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ReceivedEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesTenantData;

    public function __invoke(): View
    {
        $accountStats = $this->countFlags(
            $this->scopeEmailAccounts(EmailAccount::query()),
            ['active' => 'is_active', 'inbox' => 'inbox_enabled'],
        );
        $templateStats = $this->countFlags(
            $this->scopeClient(EmailTemplate::query()),
            ['active' => 'is_active'],
        );
        $apiKeyStats = $this->countFlags(
            $this->scopeClient(ApiKey::query()),
            ['active' => 'is_active'],
        );
        $logStats = $this->emailLogStats();
        $deliveryTrend = $this->deliveryTrend();

        return view('admin.dashboard', [
            'isAdmin' => $this->isAdmin(),
            'counts' => [
                'clients' => $this->isAdmin() ? Client::count() : (int) filled($this->currentClientId()),
                'domains' => $this->scopeClient(Domain::query())->count(),
                'accounts' => $accountStats['total'],
                'activeAccounts' => $accountStats['active'],
                'inboxAccounts' => $accountStats['inbox'],
                'templates' => $templateStats['total'],
                'activeTemplates' => $templateStats['active'],
                'apiKeys' => $apiKeyStats['total'],
                'activeApiKeys' => $apiKeyStats['active'],
                'logs' => $logStats['total'],
                'received' => $this->scopeEmailAccountData(ReceivedEmail::query())->count(),
                'sent' => $logStats[EmailLog::STATUS_SENT],
                'failed' => $logStats[EmailLog::STATUS_FAILED],
                'pending' => $logStats[EmailLog::STATUS_PENDING],
            ],
            'recentLogs' => $this->scopeEmailAccountData(EmailLog::query())
                ->with(['client', 'emailAccount', 'emailTemplate'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentReceived' => $this->scopeEmailAccountData(ReceivedEmail::query())
                ->with(['client', 'emailAccount'])
                ->latest('received_at')
                ->latest()
                ->limit(8)
                ->get(),
            'deliveryTrend' => $deliveryTrend,
        ]);
    }

    /**
     * @param  array<string, string>  $flags
     * @return array<string, int>
     */
    private function countFlags($query, array $flags): array
    {
        $select = ['COUNT(*) as total'];

        foreach ($flags as $alias => $column) {
            $select[] = "SUM(CASE WHEN {$column} = 1 THEN 1 ELSE 0 END) as {$alias}";
        }

        $row = $query->selectRaw(implode(', ', $select))->first();

        return collect(array_keys($flags))
            ->mapWithKeys(fn (string $alias): array => [$alias => (int) ($row->{$alias} ?? 0)])
            ->put('total', (int) ($row->total ?? 0))
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function emailLogStats(): array
    {
        $row = $this->scopeEmailAccountData(EmailLog::query())
            ->selectRaw(
                'COUNT(*) as total, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending',
                [EmailLog::STATUS_SENT, EmailLog::STATUS_FAILED, EmailLog::STATUS_PENDING],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            EmailLog::STATUS_SENT => (int) ($row->sent ?? 0),
            EmailLog::STATUS_FAILED => (int) ($row->failed ?? 0),
            EmailLog::STATUS_PENDING => (int) ($row->pending ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{label: string, sent: int, failed: int, received: int}>
     */
    private function deliveryTrend(): Collection
    {
        $start = Carbon::today()->subDays(6);
        $end = Carbon::today()->endOfDay();

        $logRows = $this->scopeEmailAccountData(EmailLog::query())
            ->selectRaw('DATE(created_at) as day, status, COUNT(*) as aggregate')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [EmailLog::STATUS_SENT, EmailLog::STATUS_FAILED])
            ->groupByRaw('DATE(created_at), status')
            ->get()
            ->mapWithKeys(fn (EmailLog $row): array => [$row->day.'|'.$row->status => (int) $row->aggregate]);

        $receivedRows = $this->scopeEmailAccountData(ReceivedEmail::query())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->mapWithKeys(fn (ReceivedEmail $row): array => [$row->day => (int) $row->aggregate]);

        return collect(range(6, 0))->map(function (int $daysAgo) use ($logRows, $receivedRows): array {
            $date = Carbon::today()->subDays($daysAgo);
            $key = $date->toDateString();

            return [
                'label' => $date->format('M j'),
                'sent' => (int) ($logRows[$key.'|'.EmailLog::STATUS_SENT] ?? 0),
                'failed' => (int) ($logRows[$key.'|'.EmailLog::STATUS_FAILED] ?? 0),
                'received' => (int) ($receivedRows[$key] ?? 0),
            ];
        });
    }
}
