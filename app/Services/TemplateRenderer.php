<?php

declare(strict_types=1);

namespace App\Services;

class TemplateRenderer
{
    public function render(string $content, array $data, bool $escapeHtml = false): string
    {
        $flat = $this->dot($data);

        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $matches) use ($flat, $escapeHtml): string {
            $value = $flat[$matches[1]] ?? '';
            $value = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $escapeHtml ? e($value) : $value;
        }, $content) ?? $content;
    }

    private function dot(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->dot($value, $newKey);
            } else {
                $flat[$newKey] = $value;
            }
        }

        return $flat;
    }
}
