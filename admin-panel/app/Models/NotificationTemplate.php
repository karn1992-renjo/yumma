<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'key',
        'channel',
        'label',
        'group',
        'title',
        'body',
        'placeholders',
        'is_active',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Render this template's title/body with {{placeholder}} / {placeholder}
     * substitution -- same token syntax SmsService already used, kept
     * consistent so admins only need to learn one convention.
     */
    public function render(array $variables): array
    {
        return [
            'title' => $this->title !== null ? self::interpolate($this->title, $variables) : null,
            'body' => self::interpolate($this->body, $variables),
        ];
    }

    /**
     * Look up an active template by key and render it; falls back to the
     * caller's hardcoded default if the template is missing/inactive so a
     * deleted row or unmigrated environment never breaks a live send.
     *
     * @return array{title: ?string, body: string}
     */
    public static function renderFor(string $key, array $variables, string $fallbackBody, ?string $fallbackTitle = null): array
    {
        $template = static::where('key', $key)->where('is_active', true)->first();

        if (! $template) {
            return [
                'title' => $fallbackTitle !== null ? self::interpolate($fallbackTitle, $variables) : null,
                'body' => self::interpolate($fallbackBody, $variables),
            ];
        }

        return $template->render($variables);
    }

    private static function interpolate(string $text, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }
}
