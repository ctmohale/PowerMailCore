<aside class="mail-rail inbox-sidebar-loading" aria-busy="true">
    @if ($canSendEmails)
        <div class="mail-compose-wrap">
            <button class="mail-compose-button" type="button" data-open-dialog="compose-email-dialog">Compose</button>
        </div>
    @endif

    <section class="mail-section">
        <div class="panel-header">
            <div>
                <h2>Mailboxes</h2>
                <p>Loading linked inbox accounts.</p>
            </div>
        </div>
        <div class="inbox-sidebar-loading-state">
            <span class="inbox-spinner" aria-hidden="true"></span>
            <span>Loading mailboxes...</span>
        </div>
        <div class="inbox-sidebar-skeleton-list" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </section>

    <section class="mail-section">
        <div class="panel-header">
            <div>
                <h2>Folders</h2>
                <p>Loading folder counts.</p>
            </div>
        </div>
        <div class="inbox-sidebar-skeleton-list" aria-hidden="true">
            <span></span>
            <span></span>
        </div>
    </section>

    <section class="mail-section">
        <div class="panel-header">
            <div>
                <h2>Filters</h2>
                <p>Loading filters.</p>
            </div>
        </div>
        <div class="inbox-sidebar-skeleton-form" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </section>

    @if ($canManageAccounts)
        <section class="mail-section">
            <div class="panel-header">
                <div>
                    <h2>Settings</h2>
                    <p>Loading IMAP access.</p>
                </div>
            </div>
            @if ($hasUnreadableImapPassword)
                <p class="muted inbox-sidebar-password-note">Re-enter password to reconnect inbox</p>
            @endif
            <div class="inbox-sidebar-skeleton-list" aria-hidden="true">
                <span></span>
                <span></span>
            </div>
        </section>
    @endif
</aside>
