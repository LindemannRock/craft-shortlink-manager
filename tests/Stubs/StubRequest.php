<?php

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Stubs;

use yii\web\Request;

/**
 * Test double for `yii\web\Request` used by the analytics tracking suite.
 *
 * Yii's real Request resolves IP/UA/referrer from `$_SERVER` and trusted
 * proxy headers — fine in production but brittle in a console-bootstrapped
 * test environment. This stub returns deterministic values fixed at
 * construction so tests can assert the exact bytes the tracking service
 * writes to the analytics table.
 *
 * @since 5.19.0
 */
final class StubRequest extends Request
{
    public function __construct(
        public string $userIp = '203.0.113.42',
        public string $userAgent = 'Mozilla/5.0 (Test) ShortLinkManagerStub/1.0',
        public ?string $referrer = 'https://example.com/some/page',
    ) {
        parent::__construct();
    }

    public function getUserIP(): ?string
    {
        return $this->userIp;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }
}
