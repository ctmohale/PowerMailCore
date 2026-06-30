<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Arr;

class TemplateRenderer
{
    /**
     * Replace {{ keys }} with values from the provided data array.
     */
    public function render(string $content, array $data, bool $escapeHtml = false): string
    {
        $flatData = Arr::dot($data);

        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $matches) use ($flatData, $escapeHtml): string {
            $key = $matches[1];

            if (! array_key_exists($key, $flatData)) {
                return '';
            }

            $value = $this->stringify($flatData[$key]);

            return $escapeHtml ? e($value) : $value;
        }, $content) ?? $content;
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
