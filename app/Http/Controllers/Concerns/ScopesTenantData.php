<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait ScopesTenantData
{
    private ?array $assignedEmailAccountIds = null;

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function isAdmin(): bool
    {
        return $this->currentUser()->isAdmin();
    }

    protected function currentClientId(): ?int
    {
        return $this->isAdmin() ? null : $this->currentUser()->client_id;
    }

    /**
     * @return Collection<int, Client>
     */
    protected function clientsForUser(): Collection
    {
        if ($this->isAdmin()) {
            return Client::orderBy('name')->get();
        }

        return Client::query()
            ->whereKey($this->currentClientId())
            ->orderBy('name')
            ->get();
    }

    protected function scopeClient(Builder $query, string $column = 'client_id'): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->where($column, $this->currentClientId());
    }

    protected function scopeEmailAccounts(Builder $query): Builder
    {
        $query = $this->scopeClient($query);

        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $this->allowedEmailAccountIds());
    }

    protected function scopeEmailAccountData(Builder $query, string $accountColumn = 'email_account_id', string $clientColumn = 'client_id'): Builder
    {
        $query = $this->scopeClient($query, $clientColumn);

        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereIn($accountColumn, $this->allowedEmailAccountIds());
    }

    protected function resolveClientId(?int $clientId): int
    {
        if ($this->isAdmin()) {
            abort_unless($clientId, 422, 'Client is required.');

            return $clientId;
        }

        abort_unless($this->currentClientId(), 403);

        return (int) $this->currentClientId();
    }

    protected function abortUnlessClientAllowed(?int $clientId): void
    {
        abort_if(! $this->isAdmin() && (int) $clientId !== (int) $this->currentClientId(), 403);
    }

    protected function abortUnlessEmailAccountAllowed(?int $clientId, ?int $emailAccountId): void
    {
        $this->abortUnlessClientAllowed($clientId);

        abort_if(
            ! $this->isAdmin()
            && (! $emailAccountId || ! in_array((int) $emailAccountId, $this->allowedEmailAccountIds(), true)),
            403,
        );
    }

    /**
     * @return array<int, int>
     */
    protected function allowedEmailAccountIds(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        if ($this->assignedEmailAccountIds !== null) {
            return $this->assignedEmailAccountIds;
        }

        $this->assignedEmailAccountIds = $this->currentUser()
            ->emailAccounts()
            ->pluck('email_accounts.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->assignedEmailAccountIds;
    }
}
