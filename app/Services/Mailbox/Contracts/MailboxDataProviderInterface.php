<?php

namespace App\Services\Mailbox\Contracts;

interface MailboxDataProviderInterface
{
    /**
     * Get mailbox list from data provider.
     *
     * Returns an array of normalized mailbox items:
     * [
     *   [
     *     'email'               => string,
     *     'user_name'           => string|null,
     *     'quota_bytes'         => int,   // 0 for unlimited
     *     'current_usage_bytes' => int,
     *     'domain'              => string,
     *     'suspended_incoming'  => bool,
     *     'suspended_login'     => bool,
     *   ],
     *   ...
     * ]
     *
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function getMailboxList(array $options = []): array;

    /**
     * Name/identifier of the provider.
     */
    public function getName(): string;
}
