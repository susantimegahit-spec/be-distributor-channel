<?php

namespace App\Services\Mailbox\Providers;

use App\Services\Mailbox\Contracts\MailboxDataProviderInterface;

class JsonProvider implements MailboxDataProviderInterface
{
    protected ?string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath;
    }

    public function getName(): string
    {
        return 'json';
    }

    public function getMailboxList(array $options = []): array
    {
        $path = $options['file_path'] ?? $this->filePath;
        $content = $options['content'] ?? null;

        if (!$content && $path && file_exists($path)) {
            $content = file_get_contents($path);
        }

        if (empty($content)) {
            throw new \Exception("Data JSON kosong atau file tidak ditemukan.");
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \Exception("Format JSON tidak valid.");
        }

        // Could be direct array or wrapped in 'data'
        $items = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;

        $normalized = [];

        foreach ($items as $item) {
            $email = trim((string) ($item['email'] ?? $item['login'] ?? ''));
            if (empty($email) || !str_contains($email, '@')) continue;

            $quota = $item['quota_bytes'] ?? $item['_diskquota'] ?? $item['diskquota'] ?? 0;
            $usage = $item['current_usage_bytes'] ?? $item['_diskused'] ?? $item['diskused'] ?? 0;
            $name  = $item['user_name'] ?? $item['name'] ?? null;

            $normalized[] = [
                'email'               => strtolower($email),
                'user_name'           => $name ?: ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $email)[0])),
                'quota_bytes'         => (int) $quota,
                'current_usage_bytes' => (int) $usage,
                'domain'              => strtolower($item['domain'] ?? explode('@', $email)[1] ?? 'susantimegah.com'),
                'suspended_incoming'  => !empty($item['suspended_incoming']),
                'suspended_login'     => !empty($item['suspended_login']),
            ];
        }

        return $normalized;
    }
}
