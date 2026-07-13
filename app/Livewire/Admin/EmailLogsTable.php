<?php

namespace App\Livewire\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Models\EmailLog;
use App\Models\MarketingContact;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmailLogsTable extends Component
{
    use ScopesTenantData;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'client_id', except: '')]
    public string $clientId = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $opened = '';

    #[Url(as: 'contact_id', except: '')]
    public string $contactId = '';

    public int $perPage = 25;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'clientId', 'status', 'opened', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'clientId', 'status', 'opened', 'contactId');
        $this->resetPage();
    }

    public function render(): View
    {
        $query = $this->scopeEmailAccountData(EmailLog::query())
            ->with(['client', 'emailTemplate', 'apiKey', 'marketingContact'])
            ->latest();

        if ($this->isAdmin() && filled($this->clientId)) {
            $query->where('client_id', (int) $this->clientId);
        }

        if (filled($this->status) && in_array($this->status, $this->statuses(), true)) {
            $query->where('status', $this->status);
        }

        match ($this->opened) {
            'opened' => $query->whereNotNull('opened_at'),
            'not_opened' => $query->whereNull('opened_at'),
            default => null,
        };

        if (filled($this->search)) {
            $search = trim($this->search);

            $query->where(function ($query) use ($search): void {
                $query->where('from_email', 'like', "%{$search}%")
                    ->orWhere('to_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('provider_message_id', 'like', "%{$search}%")
                    ->orWhereHas('marketingContact', function ($query) use ($search): void {
                        $query->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        if (filled($this->contactId)) {
            $contact = $this->scopeClient(MarketingContact::query())->find((int) $this->contactId);

            $query->where('marketing_contact_id', $contact?->id ?? 0);
        }

        return view('livewire.admin.email-logs-table', [
            'clients' => $this->cachedClients(),
            'statuses' => $this->statuses(),
            'logs' => $query->paginate($this->perPage),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            EmailLog::STATUS_PENDING,
            EmailLog::STATUS_SENT,
            EmailLog::STATUS_FAILED,
            EmailLog::STATUS_OPENED,
            EmailLog::STATUS_CLICKED,
        ];
    }

    /**
     * @return Collection<int, object{id: int, name: string}>
     */
    private function cachedClients(): Collection
    {
        $rows = Cache::remember(
            'livewire.email-logs.clients.v2.user.'.$this->currentUser()->id,
            now()->addMinutes(5),
            fn (): array => $this->clientsForUser()
                ->map(fn ($client): array => [
                    'id' => (int) $client->id,
                    'name' => (string) $client->name,
                ])
                ->values()
                ->all(),
        );

        return collect($rows)->map(fn (array $client): object => (object) $client);
    }
}
