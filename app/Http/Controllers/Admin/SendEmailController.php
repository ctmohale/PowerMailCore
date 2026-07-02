<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\EmailSendException;
use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Services\SendEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SendEmailController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request): View
    {
        $templates = $this->scopeClient(EmailTemplate::query())
            ->with('client')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $defaultTemplateId = $request->user()->default_email_template_id;

        return view('admin.send-email.index', [
            'accounts' => $this->scopeEmailAccounts(EmailAccount::query())
                ->with('client')
                ->where('is_active', true)
                ->orderBy('email')
                ->get(),
            'templates' => $templates,
            'defaultTemplateId' => $templates->contains('id', $defaultTemplateId) ? $defaultTemplateId : null,
        ]);
    }

    public function store(Request $request, SendEmailService $sender): RedirectResponse
    {
        $validated = $request->validate([
            'email_account_id' => ['required', 'exists:email_accounts,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'to' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string', 'max:20000'],
            'template_data' => ['nullable', 'array'],
            'data_json' => ['nullable', 'string'],
            'save_template_default' => ['nullable', 'boolean'],
        ]);

        $account = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('is_active', true)
            ->findOrFail($validated['email_account_id']);

        $template = ! empty($validated['email_template_id'])
            ? EmailTemplate::query()
                ->where('client_id', $account->client_id)
                ->where('is_active', true)
                ->findOrFail($validated['email_template_id'])
            : null;

        $messageBody = trim((string) ($validated['message_body'] ?? ''));

        if (! $template && $messageBody === '') {
            throw ValidationException::withMessages([
                'message_body' => 'Write a message or choose a template.',
            ]);
        }

        if ($template && $messageBody === '' && $this->templateRequiresMessageBody($template)) {
            throw ValidationException::withMessages([
                'message_body' => 'Write the message body for this template.',
            ]);
        }

        if (! $template && trim((string) ($validated['subject'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'subject' => 'Enter a subject when sending without a template.',
            ]);
        }

        $data = $this->templateDataFromRequest($request);

        if ($messageBody !== '') {
            $data['body'] = $messageBody;
            $data['message'] = $messageBody;
        }

        if ($template && $request->boolean('save_template_default')) {
            $request->user()->forceFill([
                'default_email_template_id' => $template->id,
            ])->save();
        }

        try {
            $log = $template
                ? $sender->sendForClient($account->client_id, [
                    'from_email' => $account->email,
                    'to' => $validated['to'],
                    'subject' => $validated['subject'] ?? null,
                    'template_key' => $template->key,
                    'data' => $data,
                ])
                : $sender->sendPlainForClient($account->client_id, [
                    'from_email' => $account->email,
                    'to' => $validated['to'],
                    'subject' => (string) $validated['subject'],
                    'message' => $messageBody,
                ]);
        } catch (EmailSendException $exception) {
            $deliveryError = $exception->emailLog?->error_message ?: $exception->getPrevious()?->getMessage();

            return back()
                ->withErrors(array_filter([
                    'send' => $exception->getMessage(),
                    'smtp' => $deliveryError,
                ]))
                ->withInput()
                ->with('delivery_error_detail', $deliveryError)
                ->with('delivery_error_hint', $this->deliveryErrorHint($deliveryError))
                ->with('email_log_id', $exception->emailLog?->id);
        }

        $message = $log->status === 'sent'
            ? 'Your email has been sent.'
            : 'Email processed with status: '.$log->status.'.';

        return back()
            ->with('success', $message)
            ->with('email_log_id', $log->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateDataFromRequest(Request $request): array
    {
        $templateData = $request->input('template_data', []);

        if (is_array($templateData) && $templateData !== []) {
            return collect($templateData)
                ->mapWithKeys(fn ($value, $key) => [(string) $key => is_string($value) ? trim($value) : $value])
                ->reject(fn ($value) => $value === null || $value === '')
                ->all();
        }

        return $this->decodeTemplateData((string) $request->input('data_json', ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTemplateData(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'data_json' => 'Template data must be valid JSON.',
            ]);
        }

        return $decoded;
    }

    private function templateRequiresMessageBody(EmailTemplate $template): bool
    {
        return preg_match('/{{\s*(body|message)\s*}}/', $template->body_html.' '.$template->body_text) === 1;
    }

    private function deliveryErrorHint(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        if (preg_match("/Peer certificate CN=`([^']+)' did not match expected CN=`([^']+)'/", $message, $matches)) {
            return "The SMTP certificate is for {$matches[1]}, but the account SMTP Host is {$matches[2]}. Update the email account SMTP Host to {$matches[1]} and keep the username as the full mailbox email address, or install a valid SSL certificate for {$matches[2]}.";
        }

        if (str_contains($message, 'saved SMTP password could not be decrypted')) {
            return 'Open Email Accounts, edit the sending account, re-enter the SMTP password, and save it again.';
        }

        if (str_contains($message, 'Failed to authenticate on SMTP server') || str_contains($message, '535 Incorrect authentication data')) {
            return 'The SMTP server rejected the saved credentials. Open Email Accounts, edit this sender, type the mailbox password into SMTP Password, and save it. Leaving the field blank keeps the old saved password.';
        }

        return null;
    }
}
