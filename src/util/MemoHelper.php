<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\util;

use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\Xdr\XdrMemoType;

use function is_numeric;
use function strlen;

class MemoHelper
{
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
        if (strlen($memo) === 0) {
            return null;
        }
        if (strlen($memoType) === 0) {
            throw new InvalidSepRequest('memo_type is required if memo is specified');
        }

        switch ($memoType) {
            case 'id':
                if (!is_numeric($memo)) {
                    throw new InvalidSepRequest('Invalid memo ' . $memo . ' of type: id');
                }

                return Memo::id((int) $memo);
            case 'text':
                if (strlen($memo) > 28) {
                    throw new InvalidSepRequest('Invalid memo ' . $memo . ' of type: text');
                }

                return Memo::text($memo);
            case 'none':
                return Memo::none();
            case 'hash':
                throw new InvalidSepRequest('Unsupported value: ' . $memoType);
            case 'return':
                throw new InvalidSepRequest('Unsupported value: ' . $memoType);
            default:
                throw new InvalidSepRequest('Invalid memo type: ' . $memoType);
        }
    }
}
