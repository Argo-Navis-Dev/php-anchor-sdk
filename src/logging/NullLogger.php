<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\logging;

use Override;
use Psr\Log\LoggerInterface;

/**
 * @phpcsSuppress
 */
final class NullLogger implements LoggerInterface
{
    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function emergency($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function alert($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function critical($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function error($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function warning($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function notice($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function info($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function debug($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Empty implementation, do nothing.
     *
     * @param $level
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    #[Override]
    public function log(mixed $level, $message, array $context = []): void
    {
        // Do nothing
    }
}
