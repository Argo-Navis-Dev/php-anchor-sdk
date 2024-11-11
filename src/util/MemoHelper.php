<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\util;

use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\Xdr\XdrMemoType;
use Throwable;

use function base64_decode;
use function is_numeric;
use function is_string;
use function strlen;

class MemoHelper
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    public static function memoTypeAsString(int $memoType): string
    {
        return match ($memoType) {
            XdrMemoType::MEMO_ID => 'id',
            XdrMemoType::MEMO_TEXT => 'text',
            XdrMemoType::MEMO_HASH => 'hash',
            XdrMemoType::MEMO_NONE => 'none',
            XdrMemoType::MEMO_RETURN => 'return',
            default => 'unknown',
        };
    }

    /**
     * @throws InvalidSepRequest
     */
    public static function makeMemoFromSepRequestData(string $memo, string $memoType): ?Memo
    {
        self::getLogger()->debug(
            'Parsing memo string.',
            ['context' => 'util', 'memo' => $memo, 'memo_type' => $memoType],
        );

        if (strlen($memo) === 0) {
            self::getLogger()->debug('Memo is empty.', ['context' => 'util']);

            return null;
        }
        if (strlen($memoType) === 0) {
            self::getLogger()->debug('Memo type is empty.', ['context' => 'util']);

            throw new InvalidSepRequest(
                message: 'memo_type is required if memo is specified',
                messageKey: 'shared_lang.error.request.memo.type_missing',
            );
        }

        switch ($memoType) {
            case 'id':
                if (!is_numeric($memo)) {
                    self::getLogger()->debug('Memo type is id, but memo is not an int.', ['context' => 'util']);

                    throw new InvalidSepRequest(
                        message: 'Invalid memo ' . $memo . ' of type: id',
                        messageKey: 'shared_lang.error.request.memo.invalid_by_id',
                        messageParams: ['memo' => $memo],
                    );
                }

                return Memo::id((int) $memo);
            case 'text':
                if (strlen($memo) > 28) {
                    self::getLogger()->debug(
                        'Memo type is text, the memo is greater than 28 characters.',
                        ['context' => 'util'],
                    );

                    throw new InvalidSepRequest(
                        message: 'Invalid memo ' . $memo . ' of type: text',
                        messageKey: 'shared_lang.error.request.memo.invalid_by_text',
                        messageParams: ['memo' => $memo],
                    );
                }

                return Memo::text($memo);
            case 'none':
                return Memo::none();
            case 'hash':
                $decoded = base64_decode($memo, true);
                try {
                    if (is_string($decoded)) {
                        return Memo::hash($decoded);
                    } else {
                        return Memo::hash($memo);
                    }
                } catch (Throwable $th) {
                    self::getLogger()->debug(
                        'Failed to parse the memo.',
                        ['context' => 'util', 'error' => $th->getMessage(), 'exception' => $th],
                    );

                    throw new InvalidSepRequest(
                        message: 'Invalid memo ' . $memo . ' of type: hash',
                        messageKey: 'shared_lang.error.request.memo.invalid_by_hash',
                        messageParams: ['memo' => $memo],
                    );
                }
            case 'return':
                throw new InvalidSepRequest(
                    message: 'Unsupported memo type value: ' . $memoType,
                    messageKey: 'shared_lang.error.request.memo.unsupported_memo_type_value',
                    messageParams: ['memoType' => $memoType],
                );
            default:
                throw new InvalidSepRequest(
                    message: 'Invalid memo type: ' . $memoType,
                    messageKey: 'shared_lang.error.request.memo.invalid_memo_type',
                    messageParams: ['memoType' => $memoType],
                );
        }
    }

    /**
     * Sets the logger in static context.
     */
    public static function setLogger(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger ?? new NullLogger();
    }

    /**
     * Returns the logger (initializes if null).
     */
    private static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }
}
