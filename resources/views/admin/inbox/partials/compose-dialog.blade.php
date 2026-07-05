@php
    $composeContext = $composeContext ?? 'inbox';
    $composeTitle = $composeTitle ?? 'Compose Email';
    $composeDescription = $composeDescription ?? 'Send an email without leaving the inbox.';
    $isComposeContext = old('compose_context') === $composeContext;
    $selectedComposeAccountId = $isComposeContext
        ? old('email_account_id', $selectedComposeAccountId ?? null)
        : ($selectedComposeAccountId ?? null);
    $selectedTemplateId = $isComposeContext
        ? old('email_template_id', $selectedTemplateId ?? $defaultTemplateId ?? null)
        : ($selectedTemplateId ?? $defaultTemplateId ?? null);
    $composeToValue = $isComposeContext ? old('to') : ($composeTo ?? '');
    $composeSubjectValue = $isComposeContext ? old('subject') : ($composeSubject ?? '');
    $composeDataFallback = $composeDataJson ?? "{\n}";
    $composeDataValue = $isComposeContext
        ? old('data_json', $composeDataFallback)
        : $composeDataFallback;
    $decodedComposeData = json_decode((string) $composeDataValue, true);
    $composeTemplateData = is_array($decodedComposeData) ? $decodedComposeData : [];
    $oldTemplateData = $isComposeContext ? old('template_data', $composeTemplateData) : $composeTemplateData;
    $composeMessageValue = $isComposeContext
        ? old('message_body', $composeTemplateData['message'] ?? '')
        : ($composeTemplateData['message'] ?? '');
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
    $sendableAccountCount = $composeAccounts->filter(fn ($account) => $account->hasUsableSmtpPassword())->count();
    $hasStaleSmtpAccounts = $composeAccounts->contains(fn ($account) => $account->hasSmtpPassword() && ! $account->hasUsableSmtpPassword());
@endphp

@if ($canSendEmails)
    <dialog class="edit-dialog compose-dialog email-compose-dialog" id="compose-email-dialog" @if ($isComposeContext && $errors->any()) data-auto-open="true" @endif>
        <form class="gmail-compose-form" method="POST" action="{{ route('send-email.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="compose_context" value="{{ $composeContext }}">
            <div class="gmail-compose-header">
                <strong>{{ $composeTitle }}</strong>
                <button class="gmail-compose-close" type="button" data-close-dialog aria-label="Close compose">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="gmail-compose-body">
                @if ($sendableAccountCount === 0)
                    <div class="notice" style="margin-top: 16px;">
                        Add at least one active sending account before sending email.
                    </div>
                @endif
                @if ($hasStaleSmtpAccounts)
                    <div class="notice" style="margin-top: 16px;">
                        A saved SMTP password needs to be re-entered before that account can send mail.
                    </div>
                @endif

                <div class="gmail-compose-workspace" data-compose-preview>
                    <div class="gmail-compose-editor">
                        <div class="gmail-compose-options">
                            <div class="gmail-compose-row">
                                <label for="compose_email_account_id">From</label>
                                <select id="compose_email_account_id" name="email_account_id" required>
                                    <option value="">Select sender</option>
                                    @foreach ($composeAccounts as $account)
                                        @php($canUseAccount = $account->hasUsableSmtpPassword())
                                        <option value="{{ $account->id }}" @selected((string) $selectedComposeAccountId === (string) $account->id) @disabled(! $canUseAccount)>
                                            {{ $account->email }}{{ auth()->user()->isAdmin() ? ' | '.$account->client?->name : '' }}{{ $canUseAccount ? '' : ' | Needs SMTP password' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="gmail-compose-row">
                                <label for="compose_email_template_id">Template</label>
                                <select id="compose_email_template_id" name="email_template_id" data-compose-template>
                                    <option value="">No template</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                                            {{ $template->name }} ({{ $template->key }}){{ (int) $defaultTemplateId === (int) $template->id ? ' | Default' : '' }}{{ auth()->user()->isAdmin() ? ' | '.$template->client?->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="gmail-compose-line">
                            <label for="compose_to">To</label>
                            <input id="compose_to" name="to" type="email" value="{{ $composeToValue }}" placeholder="client@gmail.com" required>
                        </div>

                        <div class="gmail-compose-line">
                            <label for="compose_subject">Subject</label>
                            <input id="compose_subject" name="subject" value="{{ $composeSubjectValue }}" placeholder="Use template subject" data-compose-subject>
                        </div>

                        <textarea id="compose_message_body" name="message_body" aria-label="Message" placeholder="Write your email here." data-compose-message>{{ $composeMessageValue }}</textarea>
                        <div class="gmail-compose-line">
                            <label for="compose_attachments">Attach</label>
                            <input id="compose_attachments" name="attachments[]" type="file" multiple>
                        </div>
                        <textarea id="compose_data_json" name="data_json" hidden>{{ $composeDataValue }}</textarea>

                        <div id="compose_template_data_section" class="gmail-template-fields" hidden>
                            <div class="gmail-template-title">Template fields</div>
                            <div id="compose_template_data_fields" class="gmail-template-grid"></div>
                        </div>
                    </div>
                    <aside class="gmail-compose-preview">
                        <div class="gmail-compose-preview-head">
                            <span>Recipient Preview</span>
                            <strong data-compose-preview-subject>Subject preview</strong>
                        </div>
                        <iframe title="Rendered email preview" sandbox data-compose-preview-frame></iframe>
                    </aside>
                </div>
            </div>
            <div class="gmail-compose-footer">
                <button class="gmail-compose-submit" type="submit" @disabled($sendableAccountCount === 0)>Send</button>
                <label id="compose_default_template_row" class="gmail-compose-default checkbox" hidden>
                    <input name="save_template_default" type="checkbox" value="1" @checked($isComposeContext && old('save_template_default') === '1')>
                    Save as default template
                </label>
                <button class="gmail-compose-discard" type="button" data-close-dialog aria-label="Discard draft">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                </button>
            </div>
        </form>
        <script>
            (() => {
                const templateVariables = @json($templateVariables);
                const templatePreviewData = @json($templatePreviewData);
                let fieldValues = @json($oldTemplateData);
                const previewRoot = document.querySelector('#compose-email-dialog [data-compose-preview]');
                const templateSelect = document.getElementById('compose_email_template_id');
                const fieldsSection = document.getElementById('compose_template_data_section');
                const fieldsContainer = document.getElementById('compose_template_data_fields');
                const messageBody = document.getElementById('compose_message_body');
                const dataJson = document.getElementById('compose_data_json');
                const defaultTemplateRow = document.getElementById('compose_default_template_row');
                const refreshPreview = () => {
                    if (previewRoot) {
                        window.powerMailTemplatePreview?.refresh(previewRoot, templatePreviewData, fieldValues);
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
                    defaultTemplateRow.hidden = !templateSelect.value;

                    variables.forEach((key) => {
                        const field = document.createElement('div');
                        field.className = 'gmail-template-field';

                        const id = `compose_template_data_${key.replace(/[^A-Za-z0-9_-]/g, '_')}`;
                        const label = document.createElement('label');
                        label.setAttribute('for', id);
                        label.textContent = labelFor(key);

                        const input = document.createElement('input');
                        input.id = id;
                        input.name = `template_data[${key}]`;
                        input.value = fieldValues[key] || '';

                        field.append(label, input);
                        fieldsContainer.append(field);
                    });

                    refreshPreview();
                };

                window.powerMailComposeDialog = {
                    setData(values = {}) {
                        fieldValues = values && typeof values === 'object' && !Array.isArray(values) ? values : {};

                        if (dataJson) {
                            dataJson.value = JSON.stringify(fieldValues, null, 2);
                        }

                        if (messageBody) {
                            messageBody.value = fieldValues.message || '';
                        }

                        renderFields();
                    },
                };

                previewRoot?.addEventListener('input', refreshPreview);
                templateSelect.addEventListener('change', renderFields);
                renderFields();
                window.setTimeout(refreshPreview, 0);
            })();
        </script>
    </dialog>
@endif
