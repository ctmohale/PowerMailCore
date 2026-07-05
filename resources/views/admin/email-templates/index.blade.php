@extends('layouts.app')

@section('title', 'Templates | PowerMail Core')

@section('content')
    @php
        $defaultHtmlBody = <<<'HTML'
<!doctype html>
<html>
<body style="margin:0;background:#f6f7fb;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:24px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px;border-bottom:1px solid #e5e7eb;">
                            <h1 style="margin:0;font-size:22px;">Hello {{ name }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;font-size:15px;line-height:1.65;">
                            {{ body }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#f9fafb;color:#6b7280;font-size:12px;">
                            Sent by PowerMail Core
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        $defaultTextBody = "Hello {{ name }},\n\n{{ body }}\n\nSent by PowerMail Core";
    @endphp

    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Content</p>
            <h1>Templates</h1>
            <p class="lede">Template library.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Template List</h2>
                <p>{{ $templates->count() }} template{{ $templates->count() === 1 ? '' : 's' }} available.</p>
            </div>
            <div class="panel-header-actions">
                @if (auth()->user()->isAdmin())
                    <form class="table-filter-bar" method="GET" action="{{ route('email-templates.index') }}" data-auto-submit-filter>
                        <div class="field">
                            <select id="client_id" name="client_id">
                                <option value="">All companies</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="table-filter-actions">
                            <a class="button secondary" href="{{ route('email-templates.index') }}">Reset</a>
                        </div>
                    </form>
                @endif
                <button type="button" data-open-dialog="create-template-dialog">Create Template</button>
            </div>
        </div>

        <dialog class="edit-dialog template-dialog" id="create-template-dialog" data-auto-open="{{ old('_dialog') === 'create-template-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('email-templates.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-template-dialog">
                <div class="edit-dialog-body">
                    <h2>Create Template</h2>
                    <p>Use @{{ body }} where the compose message should appear.</p>
                    <div class="template-builder" data-template-preview>
                        <div class="template-builder-fields">
                            <div class="form-grid" style="margin-top: 18px;">
                                <div class="field">
                                    <label for="create_template_client_id">Client</label>
                                    <select id="create_template_client_id" name="client_id" required>
                                        <option value="">Select client</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="create_template_key">Template Key</label>
                                    <input id="create_template_key" name="key" value="{{ old('key') }}" placeholder="welcome" required>
                                </div>
                                <div class="field">
                                    <label for="create_template_name">Name</label>
                                    <input id="create_template_name" name="name" value="{{ old('name') }}" placeholder="Welcome Email" required>
                                </div>
                                <div class="field">
                                    <label for="create_template_subject">Subject</label>
                                    <input id="create_template_subject" name="subject" value="{{ old('subject') }}" placeholder="Welcome to @{{ name }}" required data-template-subject>
                                </div>
                                <div class="field">
                                    <label for="create_template_type">Type</label>
                                    <select id="create_template_type" name="type" required>
                                        <option value="{{ \App\Models\EmailTemplate::TYPE_COMMUNICATION }}" @selected(old('type', \App\Models\EmailTemplate::TYPE_COMMUNICATION) === \App\Models\EmailTemplate::TYPE_COMMUNICATION)>Communication</option>
                                        <option value="{{ \App\Models\EmailTemplate::TYPE_MARKETING }}" @selected(old('type') === \App\Models\EmailTemplate::TYPE_MARKETING)>Marketing</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="create_template_body_html">HTML Body</label>
                                    <textarea id="create_template_body_html" class="template-html-editor" name="body_html" required data-template-html>{{ old('body_html', $defaultHtmlBody) }}</textarea>
                                </div>
                                <div class="field full">
                                    <label for="create_template_body_text">Text Body</label>
                                    <textarea id="create_template_body_text" class="template-text-editor" name="body_text">{{ old('body_text', $defaultTextBody) }}</textarea>
                                </div>
                                <input type="hidden" name="is_active" value="0">
                                <label class="field checkbox">
                                    <input name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                                    Active
                                </label>
                            </div>
                        </div>
                        <aside class="template-preview-panel">
                            <div class="template-preview-head">
                                <span>HTML Preview</span>
                                <strong data-template-preview-subject>Subject preview</strong>
                            </div>
                            <iframe title="Template HTML preview" sandbox data-template-preview-frame></iframe>
                        </aside>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Create Template</button>
                </div>
            </form>
        </dialog>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Name</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>{{ $template->key }}</td>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->client?->name }}</td>
                            <td><span class="badge {{ $template->isMarketing() ? 'pending' : 'active' }}">{{ $template->isMarketing() ? 'Marketing' : 'Communication' }}</span></td>
                            <td class="wrap">{{ $template->subject }}</td>
                            <td><span class="badge {{ $template->is_active ? 'active' : 'failed' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-template-{{ $template->id }}">Edit</button>
                                    <form method="POST" action="{{ route('email-templates.destroy', $template) }}" data-confirm="Delete template {{ $template->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog template-dialog" id="edit-template-{{ $template->id }}">
                                    <form method="POST" action="{{ route('email-templates.update', $template) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="edit-dialog-body">
                                            <h2>Edit Template</h2>
                                            <p>{{ $template->name }}. Use @{{ body }} where the compose message should appear.</p>
                                            <div class="template-builder" data-template-preview>
                                                <div class="template-builder-fields">
                                                    <div class="form-grid" style="margin-top: 18px;">
                                                        <div class="field">
                                                            <label for="template_client_{{ $template->id }}">Client</label>
                                                            <select id="template_client_{{ $template->id }}" name="client_id" required>
                                                                @foreach ($clients as $client)
                                                                    <option value="{{ $client->id }}" @selected(old('client_id', $template->client_id) == $client->id)>{{ $client->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="field">
                                                            <label for="key_{{ $template->id }}">Key</label>
                                                            <input id="key_{{ $template->id }}" name="key" value="{{ old('key', $template->key) }}" required>
                                                        </div>
                                                        <div class="field">
                                                            <label for="name_{{ $template->id }}">Name</label>
                                                            <input id="name_{{ $template->id }}" name="name" value="{{ old('name', $template->name) }}" required>
                                                        </div>
                                                        <div class="field">
                                                            <label for="subject_{{ $template->id }}">Subject</label>
                                                            <input id="subject_{{ $template->id }}" name="subject" value="{{ old('subject', $template->subject) }}" required data-template-subject>
                                                        </div>
                                                        <div class="field">
                                                            <label for="type_{{ $template->id }}">Type</label>
                                                            <select id="type_{{ $template->id }}" name="type" required>
                                                                <option value="{{ \App\Models\EmailTemplate::TYPE_COMMUNICATION }}" @selected(old('type', $template->type) === \App\Models\EmailTemplate::TYPE_COMMUNICATION)>Communication</option>
                                                                <option value="{{ \App\Models\EmailTemplate::TYPE_MARKETING }}" @selected(old('type', $template->type) === \App\Models\EmailTemplate::TYPE_MARKETING)>Marketing</option>
                                                            </select>
                                                        </div>
                                                        <div class="field full">
                                                            <label for="body_html_{{ $template->id }}">HTML Body</label>
                                                            <textarea id="body_html_{{ $template->id }}" class="template-html-editor" name="body_html" required data-template-html>{{ old('body_html', $template->body_html) }}</textarea>
                                                        </div>
                                                        <div class="field full">
                                                            <label for="body_text_{{ $template->id }}">Text Body</label>
                                                            <textarea id="body_text_{{ $template->id }}" class="template-text-editor" name="body_text">{{ old('body_text', $template->body_text) }}</textarea>
                                                        </div>
                                                        <input type="hidden" name="is_active" value="0">
                                                        <label class="field checkbox">
                                                            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $template->is_active ? '1' : '0') === '1')>
                                                            Active
                                                        </label>
                                                    </div>
                                                </div>
                                                <aside class="template-preview-panel">
                                                    <div class="template-preview-head">
                                                        <span>HTML Preview</span>
                                                        <strong data-template-preview-subject>Subject preview</strong>
                                                    </div>
                                                    <iframe title="Template HTML preview" sandbox data-template-preview-frame></iframe>
                                                </aside>
                                            </div>
                                        </div>
                                        <div class="edit-dialog-actions">
                                            <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No templates yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (() => {
            const previewValues = {
                name: 'Preview Recipient',
                first_name: 'Preview',
                last_name: 'Recipient',
                company: 'PowerMail Core',
                email: 'preview@example.com',
                body: '<p>This is where the compose message will appear.</p><p>Second paragraph preview.</p>',
                message: '<p>This is where the compose message will appear.</p><p>Second paragraph preview.</p>',
                unsubscribe_url: '#unsubscribe-preview',
            };

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const placeholderPattern = new RegExp('\\{\\{\\s*([A-Za-z0-9_.-]+)\\s*\\}\\}', 'g');
            const renderTemplate = (template, html = false) => template.replace(placeholderPattern, (match, key) => {
                if (!Object.hasOwn(previewValues, key)) {
                    return '';
                }

                if (html && ['body', 'message'].includes(key)) {
                    return previewValues[key];
                }

                return html ? escapeHtml(previewValues[key]) : previewValues[key];
            });

            const previewFitStyles = `
                <style>
                    html,
                    body {
                        max-width: 100% !important;
                        overflow-x: hidden !important;
                    }

                    body {
                        margin-left: auto !important;
                        margin-right: auto !important;
                    }

                    *,
                    *::before,
                    *::after {
                        box-sizing: border-box !important;
                    }

                    table,
                    tbody,
                    thead,
                    tfoot,
                    tr,
                    td,
                    th {
                        max-width: 100% !important;
                    }

                    table[width] {
                        width: 100% !important;
                    }

                    img,
                    video,
                    canvas {
                        max-width: 100% !important;
                        height: auto !important;
                    }

                    pre,
                    code,
                    p,
                    span,
                    a,
                    div,
                    h1,
                    h2,
                    h3 {
                        overflow-wrap: anywhere;
                    }
                </style>
            `;

            const fitPreviewDocument = (html) => {
                if (/<\/head>/i.test(html)) {
                    return html.replace(/<\/head>/i, `${previewFitStyles}</head>`);
                }

                if (/<body[^>]*>/i.test(html)) {
                    return html.replace(/<body([^>]*)>/i, `<head>${previewFitStyles}</head><body$1>`);
                }

                return `<!doctype html><html><head>${previewFitStyles}</head><body>${html}</body></html>`;
            };

            document.querySelectorAll('[data-template-preview]').forEach((builder) => {
                const html = builder.querySelector('[data-template-html]');
                const subject = builder.querySelector('[data-template-subject]');
                const previewSubject = builder.querySelector('[data-template-preview-subject]');
                const frame = builder.querySelector('[data-template-preview-frame]');

                const refresh = () => {
                    if (previewSubject && subject) {
                        previewSubject.textContent = renderTemplate(subject.value || 'Subject preview');
                    }

                    if (frame && html) {
                        frame.srcdoc = fitPreviewDocument(renderTemplate(html.value, true));
                    }
                };

                html?.addEventListener('input', refresh);
                subject?.addEventListener('input', refresh);
                refresh();
            });
        })();
    </script>
@endsection
