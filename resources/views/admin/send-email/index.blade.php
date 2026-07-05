@extends('layouts.app')

@section('title', 'Send Email | PowerMail Core')

@section('content')
    @php
        $selectedTemplateId = old('email_template_id', $defaultTemplateId);
        $sendableAccountCount = $accounts->filter(fn ($account) => $account->hasUsableSmtpPassword())->count();
        $hasStaleSmtpAccounts = $accounts->contains(fn ($account) => $account->hasSmtpPassword() && ! $account->hasUsableSmtpPassword());
        $templateVariables = $templates->mapWithKeys(function ($template) {
            preg_match_all('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', $template->subject.' '.$template->body_html.' '.$template->body_text, $matches);

            return [
                $template->id => collect($matches[1] ?? [])->unique()->values()->all(),
            ];
        });
        $templatePreviewData = $templates->mapWithKeys(fn ($template) => [
            $template->id => [
                'subject' => $template->subject,
                'body_html' => $template->body_html,
            ],
        ]);
        $oldTemplateData = old('template_data', []);
    @endphp

    <div class="page-header mail-page-header">
        <div class="page-title">
            <p class="eyebrow">Compose</p>
            <h1>New Message</h1>
            <p class="lede">Send an email from an approved SMTP account.</p>
        </div>
        @if (session('email_log_id') && auth()->user()->canAccess(\App\Models\User::PERMISSION_VIEW_LOGS))
            <div class="actions">
                <a class="button secondary" href="{{ route('email-logs.show', session('email_log_id')) }}">View Log</a>
            </div>
        @endif
    </div>

    @if ($sendableAccountCount === 0)
        <div class="notice">
            Add at least one active sending account before sending email.
        </div>
    @endif

    @if ($hasStaleSmtpAccounts)
        <div class="notice delivery-notice">
            A saved SMTP password needs to be re-entered before that account can send mail.
        </div>
    @endif

    @if (session('delivery_error_detail'))
        <div class="notice delivery-notice">
            <strong>SMTP delivery detail:</strong>
            {{ session('delivery_error_detail') }}
            @if (session('delivery_error_hint'))
                <div class="delivery-hint">
                    <strong>Fix:</strong>
                    {{ session('delivery_error_hint') }}
                </div>
            @endif
        </div>
    @endif

    <section class="panel compose-mail-card">
        <div class="compose-mail-header">
            <span>New Message</span>
        </div>
        <form method="POST" action="{{ route('send-email.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="compose-mail-body">
                <div class="send-compose-layout" data-compose-preview>
                    <div class="send-compose-editor">
                        <div class="compose-mail-grid">
                            <div class="field">
                                <label for="email_account_id">From Account</label>
                                <select id="email_account_id" name="email_account_id" required>
                                    <option value="">Select sender</option>
                                    @foreach ($accounts as $account)
                                        @php($canUseAccount = $account->hasUsableSmtpPassword())
                                        <option value="{{ $account->id }}" @selected(old('email_account_id') == $account->id) @disabled(! $canUseAccount)>
                                            {{ $account->email }}{{ auth()->user()->isAdmin() ? ' | '.$account->client?->name : '' }}{{ $canUseAccount ? '' : ' | Needs SMTP password' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="email_template_id">Template</label>
                                <select id="email_template_id" name="email_template_id" data-compose-template>
                                    <option value="">No template</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                                            {{ $template->name }} ({{ $template->key }}){{ (int) $defaultTemplateId === (int) $template->id ? ' | Default' : '' }}{{ auth()->user()->isAdmin() ? ' | '.$template->client?->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="compose-line">
                            <label for="to">To</label>
                            <input id="to" name="to" type="email" value="{{ old('to') }}" placeholder="recipient@example.com" required>
                        </div>

                        <div class="compose-line">
                            <label for="subject">Subject</label>
                            <input id="subject" name="subject" value="{{ old('subject') }}" placeholder="Use template subject" data-compose-subject>
                        </div>

                        <div class="compose-data-field">
                            <div class="compose-data-label">
                                <label for="message_body">Message</label>
                            </div>
                            <textarea id="message_body" name="message_body" placeholder="Write your email here." data-compose-message>{{ old('message_body') }}</textarea>
                        </div>

                        <div class="compose-line">
                            <label for="attachments">Attach</label>
                            <input id="attachments" name="attachments[]" type="file" multiple>
                        </div>

                        <textarea id="data_json" name="data_json" hidden>{{ old('data_json') }}</textarea>

                        <div id="template-data-section" class="compose-data-field" hidden>
                            <div class="compose-data-label">
                                <label>Template Fields</label>
                            </div>
                            <div id="template-data-fields" class="form-grid" style="margin-top: 12px;"></div>
                        </div>

                        <label class="compose-default checkbox">
                            <input name="save_template_default" type="checkbox" value="1" @checked(old('save_template_default') === '1')>
                            Save selected template as my default
                        </label>
                    </div>
                    <aside class="gmail-compose-preview send-compose-preview">
                        <div class="gmail-compose-preview-head">
                            <span>Recipient Preview</span>
                            <strong data-compose-preview-subject>Subject preview</strong>
                        </div>
                        <iframe title="Rendered email preview" sandbox data-compose-preview-frame></iframe>
                    </aside>
                </div>
            </div>

            <div class="compose-mail-footer">
                <button type="submit" @disabled($sendableAccountCount === 0)>Send Email</button>
                @if (auth()->user()->canAccess(\App\Models\User::PERMISSION_VIEW_LOGS))
                    <a class="button secondary" href="{{ route('email-logs.index') }}">Open Logs</a>
                @endif
            </div>
        </form>
    </section>

    <script>
        (() => {
            const templateVariables = @json($templateVariables);
            const templatePreviewData = @json($templatePreviewData);
            const oldTemplateData = @json($oldTemplateData);
            const previewRoot = document.querySelector('[data-compose-preview]');
            const templateSelect = document.getElementById('email_template_id');
            const fieldsSection = document.getElementById('template-data-section');
            const fieldsContainer = document.getElementById('template-data-fields');
            const messageBody = document.getElementById('message_body');
            const refreshPreview = () => {
                if (previewRoot) {
                    window.powerMailTemplatePreview?.refresh(previewRoot, templatePreviewData, oldTemplateData);
                }
            };

            const labelFor = (key) => key
                .replace(/[_.-]+/g, ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());

            const renderFields = () => {
                    const allVariables = templateVariables[templateSelect.value] || [];
                    const requiresMessageBody = allVariables.some((key) => ['body', 'message'].includes(key));
                    const variables = allVariables.filter((key) => !['body', 'message'].includes(key));
                    fieldsContainer.innerHTML = '';
                    fieldsSection.hidden = variables.length === 0;
                    messageBody.required = !templateSelect.value || requiresMessageBody;

                variables.forEach((key) => {
                    const field = document.createElement('div');
                    field.className = 'field';

                    const id = `template_data_${key.replace(/[^A-Za-z0-9_-]/g, '_')}`;
                    const label = document.createElement('label');
                    label.setAttribute('for', id);
                    label.textContent = labelFor(key);

                    const input = document.createElement('input');
                    input.id = id;
                    input.name = `template_data[${key}]`;
                    input.value = oldTemplateData[key] || '';

                    field.append(label, input);
                    fieldsContainer.append(field);
                });

                refreshPreview();
            };

            previewRoot?.addEventListener('input', refreshPreview);
            templateSelect.addEventListener('change', renderFields);
            renderFields();
            window.setTimeout(refreshPreview, 0);
        })();
    </script>
@endsection
