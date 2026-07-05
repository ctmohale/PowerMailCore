<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesEmailAttachments
{
    /**
     * @return array<string, mixed>
     */
    private function attachmentValidationRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<int, array{path:string,name:string,mime:?string}>
     */
    private function uploadedAttachmentsFromRequest(Request $request): array
    {
        return collect($request->file('attachments', []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile && $file->isValid())
            ->map(fn (UploadedFile $file): array => [
                'path' => (string) $file->getRealPath(),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{path:string,name:string,mime:?string}>
     */
    private function storeAttachmentsFromRequest(Request $request, string $directory): array
    {
        return collect($request->file('attachments', []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile && $file->isValid())
            ->map(function (UploadedFile $file) use ($directory): array {
                $name = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $storedName = Str::uuid()->toString().($extension ? '.'.$extension : '');
                $path = $file->storeAs($directory, $storedName);

                return [
                    'path' => $path,
                    'name' => $name,
                    'mime' => $file->getMimeType(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, array{path:string,name:string,mime:?string}>
     */
    private function storedAttachmentsForSending(array $attachments): array
    {
        return collect($attachments)
            ->map(function (array $attachment): ?array {
                $path = (string) ($attachment['path'] ?? '');

                if ($path === '' || ! Storage::exists($path)) {
                    return null;
                }

                return [
                    'path' => Storage::path($path),
                    'name' => (string) ($attachment['name'] ?? basename($path)),
                    'mime' => $attachment['mime'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
