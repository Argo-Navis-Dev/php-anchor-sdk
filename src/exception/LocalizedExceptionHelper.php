<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use ArgoNavis\PhpAnchorSdk\config\IAppConfig;

/**
 * Helper class for exception related operations.
 */
class LocalizedExceptionHelper
{
    /**
     * @param InvalidRequestData|InvalidSepRequest|AnchorFailure|CustomerNotFoundForId|QuoteNotFoundForId $af
     * The exception.
     * @param IAppConfig $appConfig The app config.
     * @param string | null $lang The language code.
     *
     * @return string The localized error message.
     */
    public static function getLocalizedErrorMsgFromException(
        InvalidRequestData | InvalidSepRequest | AnchorFailure | CustomerNotFoundForId | QuoteNotFoundForId $af,
        IAppConfig $appConfig,
        ?string $lang = 'en',
    ): string {
        $messageKey = $af->getMessageKey();
        $previousError = null;
        if ($messageKey !== null) {
            if ($af->getPrevious() instanceof AnchorFailure) {
                $prevException = $af->getPrevious();
                $prevExceptionMsgKey = $prevException->getMessageKey();
                if ($prevExceptionMsgKey !== null) {
                    $previousError = $appConfig->getLocalizedText(
                        key: $prevExceptionMsgKey,
                        locale: $lang,
                        default: $prevException->getMessage(),
                        params: $prevException->getMessageParams(),
                    );
                }
            }
            $messageParams = $af->getMessageParams();
            if ($previousError !== null) {
                $messageParams = $messageParams ?? [];
                $messageParams['previous_exception'] = $previousError;
            }

            $errorMsg = $appConfig->getLocalizedText(
                key: $messageKey,
                locale: $lang,
                default: $af->getMessage(),
                params: $messageParams,
            );
        } else {
            $errorMsg = $af->getMessage();
        }

        return $errorMsg;
    }
}
