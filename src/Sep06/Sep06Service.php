<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep06;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\callback\IQuotesIntegration;
use ArgoNavis\PhpAnchorSdk\callback\ITransferIntegration;
use ArgoNavis\PhpAnchorSdk\callback\Sep38PricesRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositExchangeRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositResponse;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawExchangeRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawResponse;
use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use ArgoNavis\PhpAnchorSdk\config\ISep06Config;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSep10JwtData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use ArrayObject;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Exceptions\HorizonRequestException;
use Soneso\StellarSDK\StellarSDK;

use function count;
use function floatval;
use function is_numeric;
use function is_string;
use function json_encode;
use function str_contains;
use function strval;

/**
 * The Sep06Service handles Deposit and Withdrawal requests as defined by
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md">SEP-06</a>
 *
 * To create an instance of the service, you have to pass a business logic callback class that implements
 * ITransferIntegration to the service constructor. This is needed, so that the service can load
 * supported assets, fees, load and store transaction data and more. You must also pass a config class implementing
 * ISep06Config. It defines SEP-06 features supported by the server.
 *
 * After initializing the service it can be used within the server implementation by passing all
 * SEP-06 requests to its method handleRequest. It will handle them and return the corresponding response
 * that can be sent back to the client. During the handling it will call methods from the callback implementation
 * (ITransferIntegration) and the sep 06 config provided by the server.
 *
 * See: <a href="https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-06.md">SDK SEP-06 docs</a>
 */
class Sep06Service
{
    public IAppConfig $appConfig;
    /**
     * @var ISep06Config $sep06Config SEP-24 config containing info about the supported features.
     */
    public ISep06Config $sep06Config;

    /**
     * @var ITransferIntegration $sep06Integration the callback class containing the needed business
     * supported assets, fees, load and store transaction data and more. See ITransferIntegration description.
     */
    public ITransferIntegration $sep06Integration;

    public ?IQuotesIntegration $quotesIntegration = null;

    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    /**
     * Constructor
     *
     * @param IAppConfig $appConfig App config containing the network and horizon url.
     * @param ISep06Config $sep06Config SEP-06 config containing info about the supported features.
     * @param ITransferIntegration $sep06Integration the callback class containing the needed business
     * supported assets, fees, load and store transaction data and more. See ITransferIntegration description.
     * @param IQuotesIntegration|null $quotesIntegration the callback class for quotes if the anchor supports SEP-38
     * - Quotes. If the quotes integration is not provided, the deposit-exchange and withdraw-exchange endpoints
     * can not be supported.
     */
    public function __construct(
        IAppConfig $appConfig,
        ISep06Config $sep06Config,
        ITransferIntegration $sep06Integration,
        ?IQuotesIntegration $quotesIntegration = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->appConfig = $appConfig;
        $this->sep06Config = $sep06Config;
        $this->sep06Integration = $sep06Integration;
        $this->quotesIntegration = $quotesIntegration;
        $this->logger = $logger ?? new NullLogger();
        Sep10Jwt::setLogger($this->logger);
        Sep06RequestValidator::setLogger($this->logger);
        Sep06RequestParser::setLogger($this->logger);
        MemoHelper::setLogger($this->logger);
    }

    /**
     * Handles a forwarded client request specified by SEP-06. Builds and returns the corresponding response,
     * that can be sent back to the client.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     * @param Sep10Jwt|null $token the validated jwt token obtained earlier by SEP-10.
     * Only relevant for endpoints that require authentication.
     *
     * @return ResponseInterface the response that should be sent back to the client.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>
     */
    public function handleRequest(ServerRequestInterface $request, ?Sep10Jwt $token = null,): ResponseInterface
    {
        $requestTarget = $request->getRequestTarget();
        $this->logger->info(
            'Handling incoming request.',
            ['context' => 'sep06', 'method' => $request->getMethod()],
        );
        if ($request->getMethod() === 'GET' && str_contains($requestTarget, '/info')) {
            $this->logger->info(
                'Handling SEP-06 info request.',
                ['context' => 'sep06', 'operation' => 'info'],
            );

            return $this->buildInfo();
        }

        // all other cases require authentication.

        if ($token === null) {
            //403  forbidden
            $this->logger->error(
                'Handling SEP-06 request failed.',
                ['error' => 'Authentication required', 'http_status_code' => '403', 'context' => 'sep06'],
            );

            return new JsonResponse(['type' => 'authentication_required'], 403);
        }

        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/deposit-exchange')) {
                $this->logger->info(
                    'Handling SEP-06 request for deposit exchange.',
                    ['context' => 'sep06', 'operation' => 'deposit_exchange'],
                );

                return self::handleDepositExchangeRequest($request, $token);
            } elseif (str_contains($requestTarget, '/withdraw-exchange')) {
                $this->logger->info(
                    'Executing withdraw exchange.',
                    ['context' => 'sep06', 'operation' => 'withdraw_exchange'],
                );

                return self::handleWithdrawExchangeRequest($request, $token);
            } elseif (str_contains($requestTarget, '/deposit')) {
                $this->logger->info(
                    'Executing deposit.',
                    ['context' => 'sep06', 'operation' => 'deposit'],
                );

                return self::handleDepositRequest($request, $token);
            } elseif (str_contains($requestTarget, '/withdraw')) {
                $this->logger->info(
                    'Executing withdraw.',
                    ['context' => 'sep06', 'operation' => 'withdraw'],
                );

                return self::handleWithdrawRequest($request, $token);
            } elseif (str_contains($requestTarget, '/transactions')) {
                $this->logger->info(
                    'Retrieving transactions.',
                    ['context' => 'sep06', 'operation' => 'transactions'],
                );

                return $this->handleGetTransactionsRequest($request, $token);
            } elseif (str_contains($requestTarget, '/transaction')) {
                $this->logger->info(
                    'Retrieving a transaction.',
                    ['context' => 'sep06', 'operation' => 'transaction'],
                );

                return $this->handleGetTransactionRequest($request, $token);
            } else {
                $this->logger->error(
                    'Invalid request. Unknown endpoint.',
                    ['http_status_code' => '404', 'operation' => $requestTarget, 'context' => 'sep06'],
                );

                return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
            }
        } else {
            $this->logger->error(
                'Invalid request.',
                ['error' => 'Method not supported',
                    'context' => 'sep06', 'operation' => $requestTarget, 'http_status_code' => '404',
                ],
            );

            return new JsonResponse(['error' => 'Invalid request. Method not supported.'], 404);
        }
    }

    /**
     * Handles a deposit exchange request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return ResponseInterface to be sent back to the client.
     */
    private function handleDepositExchangeRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            return new JsonResponse($this->depositExchange($request, $jwtToken)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            $code = $e->getCode() !== 0 ? $e->getCode() : 400;
            $this->logger->error(
                'Failed to handle deposit exchange request.',
                ['error' => $e->getMessage(), 'http_status_code' => $code, 'exception' => $e,
                    'context' => 'sep06', 'operation' => 'deposit_exchange',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Handles a deposit request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return ResponseInterface to be sent back to the client.
     */
    private function handleDepositRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            return new JsonResponse($this->deposit($request, $jwtToken)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            $code = $e->getCode() !== 0 ? $e->getCode() : 400;
            $this->logger->error(
                'Failed to handle deposit request.',
                ['error' => $e->getMessage(), 'http_status_code' => $code, 'exception' => $e,
                    'context' => 'sep06', 'operation' => 'deposit',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    private function handleWithdrawRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            return new JsonResponse($this->withdraw($request, $jwtToken)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            $code = $e->getCode() !== 0 ? $e->getCode() : 400;
            $this->logger->error(
                'Failed to handle withdraw request.',
                ['error' => $e->getMessage(), 'http_status_code' => $code, 'exception' => $e,
                    'context' => 'sep06', 'operation' => 'withdraw',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    private function handleWithdrawExchangeRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            return new JsonResponse($this->withdrawExchange($request, $jwtToken)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            $code = $e->getCode() !== 0 ? $e->getCode() : 400;
            $this->logger->error(
                'Failed to handle withdraw exchange request.',
                ['error' => $e->getMessage(), 'http_status_code' => $code, 'exception' => $e,
                    'context' => 'sep06', 'operation' => 'withdraw_exchange',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Handles a deposit exchange request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return StartDepositResponse the response from the anchor containing the instructions for the deposit.
     *
     * @throws InvalidSepRequest if there is an error in the request data. e.g. unknown asset code, invalid amount, etc.
     * @throws InvalidSep10JwtData if the data in the jwt token is invalid e.g. invalid account id.
     * @throws AnchorFailure if the anchor failed to handle the deposit request.
     */
    private function depositExchange(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): StartDepositResponse {
        if ($this->quotesIntegration === null) {
            $this->logger->debug(
                'The $quotesIntegration is null.',
                ['context' => 'sep06', 'operation' => 'deposit_exchange'],
            );

            throw new AnchorFailure('Unable to access quotes.', 500);
        }
        $queryParameters = $request->getQueryParams();
        $this->logger->info(
            'Executing SEP-06 deposit exchange.',
            ['query_parameters' => json_encode($queryParameters), 'context' => 'sep06',
                'operation' => 'deposit_exchange',
            ],
        );
        $buyAssetCode = Sep06RequestParser::getDestinationAssetCodeFromRequestData($queryParameters);
        $sellAsset = Sep06RequestParser::getSourceAssetFromRequestData($queryParameters);
        $amount = Sep06RequestParser::getAmountFromRequestData($queryParameters);
        if ($amount === null) {
            throw new InvalidSepRequest('missing amount');
        }
        $account = Sep06RequestParser::getAccountFromRequestData($queryParameters);
        $memo = Sep06RequestParser::getMemoFromRequestData($queryParameters);
        $quoteId = Sep06RequestParser::getQuoteIdFromRequestData($queryParameters);
        $email = Sep06RequestParser::getEmailAddressFromRequestData($queryParameters);
        $type = Sep06RequestParser::getTypeFromRequestData($queryParameters);
        $lang = Sep06RequestParser::getLangFromRequestData($queryParameters);
        $callbackUrl = Sep06RequestParser::getOnChangeCallbackUrlFromRequestData($queryParameters);
        $countryCode = Sep06RequestParser::getCountryCodeFromRequestData($queryParameters);
        $claimableBalancesSupported = Sep06RequestParser::getClaimableBalanceSupportedRequestData($queryParameters);
        $customerId = Sep06RequestParser::getCustomerIdFromRequestData($queryParameters);
        $locationId = Sep06RequestParser::getLocationIdFromRequestData($queryParameters);
        $this->logger->debug(
            'The request parameters after processing.',
            [
                'buy_asset_code' => $buyAssetCode,
                'sell_asset' => $sellAsset,
                'amount' => $amount,
                'account' => $account,
                'memo' => $memo,
                'quote_id' => $quoteId,
                'email' => $email,
                'type' => $type,
                'lang' => $lang,
                'callback_url' => $callbackUrl,
                'country_code' => $countryCode,
                'claimable_balances_supported' => $claimableBalancesSupported,
                'customer_id' => $customerId,
                'location_id' => $locationId,
                'context' => 'sep06',
                'operation' => 'deposit_exchange',
            ],
        );

        //check buy asset
        $supportedAssets = $this->sep06Integration->supportedAssets();
        $buyAsset = Sep06RequestValidator::getDestinationAsset($buyAssetCode, $supportedAssets);

        /**
         * @var ?string $sep10AccountMemo
         */
        $sep10AccountMemo = null;

        $sep10AccountData = $jwtToken->getValidatedAccountData();
        if (isset($sep10AccountData['account_id'])) {
            $sep10AccountId = $sep10AccountData['account_id'];
        } else {
            throw new InvalidSepRequest('invalid jwt token');
        }
        if (isset($sep10AccountData['account_memo'])) {
            $sep10AccountMemo = $sep10AccountData['account_memo'];
        }

        // check if the account must exist
        if (!$this->sep06Config->isAccountCreationSupported()) {
            try {
                $sdk = new StellarSDK($this->appConfig->getHorizonUrl());
                $exists = $sdk->accountExists($account);
                if (!$exists) {
                    throw new InvalidSepRequest(
                        'Account creation not supported. Account ' . $account . ' not found.',
                    );
                }
            } catch (HorizonRequestException $ex) {
                throw new AnchorFailure('Could not check if account ' . $account . ' exists', 500);
            }
        }

        // check sell asset
        /**
         * The off-chain asset the Anchor will receive from the user. The value must match one of the asset
         * values included in a SEP-38 GET /prices?buy_asset=stellar:<destination_asset>:<asset_issuer>
         * response using SEP-38 Asset Identification Format.
         */
        $pricesRequest = new Sep38PricesRequest(
            sellAsset: $sellAsset,
            sellAmount: strval($amount),
            sellDeliveryMethod: $type,
            countryCode: $countryCode,
            accountId: $sep10AccountId,
            accountMemo: $sep10AccountMemo,
        );
        $this->logger->debug(
            'SEP-06 deposit exchange ',
            ['context' => 'sep06', 'operation' => 'deposit_exchange'],
        );
        $sep38BuyAssets = $this->quotesIntegration->getPrices($pricesRequest);
        $buyAssetFound = false;
        foreach ($sep38BuyAssets as $sep38BuyAsset) {
            if ($sep38BuyAsset->asset->getCode() === $buyAssetCode) {
                $buyAssetFound = true;

                break;
            }
        }
        if (!$buyAssetFound) {
            $error = 'invalid operation for asset ' . $sellAsset->getStringRepresentation();
            $this->logger->debug(
                'Buy asset not found, ' . $error,
                ['context' => 'sep06', 'operation' => 'deposit_exchange'],
            );

            throw new InvalidSepRequest('invalid operation for asset ' . $sellAsset->getStringRepresentation());
        }

        // validate type
        if ($type !== null && $buyAsset->depositOperation->methods !== null) {
            Sep06RequestValidator::validateType($type, $buyAssetCode, $buyAsset->depositOperation->methods);
        }

        // validate amount
        Sep06RequestValidator::validateAmount(
            $amount,
            $buyAsset->asset->getCode(),
            $buyAsset->depositOperation->minAmount,
            $buyAsset->depositOperation->maxAmount,
        );

        // validate with quote data
        if ($quoteId !== null) {
            $this->logger->debug(
                'Quote found',
                ['context' => 'sep06', 'operation' => 'deposit_exchange', 'quote_id' => $quoteId],
            );
            $quote = $this->quotesIntegration->getQuoteById(
                id: $quoteId,
                accountId: $sep10AccountId,
                accountMemo: $sep10AccountMemo,
            );
            if ($quote->sellAsset->getStringRepresentation() !== $sellAsset->getStringRepresentation()) {
                throw new InvalidSepRequest(
                    'quote sell asset does not match source_asset ' . $sellAsset->getStringRepresentation(),
                );
            }
            if ($quote->buyAsset->getStringRepresentation() !== $buyAsset->asset->getStringRepresentation()) {
                $this->logger->debug(
                    'The quote buy asset does not match.',
                    ['context' => 'sep06', 'operation' => 'deposit_exchange',
                        'destination_asset' => $buyAsset->asset->getStringRepresentation(),
                    ],
                );

                throw new InvalidSepRequest(
                    'quote buy asset does not match destination_asset ' .
                    $buyAsset->asset->getStringRepresentation(),
                );
            }
            $amountStr = Sep06RequestParser::getStringValueFromRequestData('amount', $queryParameters);
            if ($quote->sellAmount !== $amountStr) {
                if (!is_numeric($quote->sellAmount) || floatval($quote->sellAmount) !== $amount) {
                    throw new InvalidSepRequest('quote amount does not match request amount');
                }
            }
        }

        $startDepositExchangeRequest = new StartDepositExchangeRequest(
            destinationAsset: $buyAsset,
            sourceAsset: $sellAsset,
            amount: $amount,
            account: $account,
            sep10Account: $sep10AccountId,
            sep10AccountMemo: $sep10AccountMemo,
            memo: $memo,
            quoteId: $quoteId,
            email: $email,
            type: $type,
            lang:$lang,
            onChangeCallbackUrl: $callbackUrl,
            countryCode: $countryCode,
            claimableBalanceSupported: $claimableBalancesSupported,
            customerId: $customerId,
            locationId: $locationId,
            clientDomain: $jwtToken->clientDomain,
        );
        $this->logger->debug(
            'Start deposit exchange request.',
            ['context' => 'sep06', 'operation' => 'deposit_exchange',
                'start_deposit_exchange_request' => json_encode($startDepositExchangeRequest),
            ],
        );

        $depositExchange = $this->sep06Integration->depositExchange($startDepositExchangeRequest);
        $this->logger->debug(
            'The deposit.',
            ['context' => 'sep06', 'operation' => 'deposit_exchange',
                'deposit_exchange' => json_encode($depositExchange),
            ],
        );

        return $depositExchange;
    }

    /**
     * Handles a deposit request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return StartDepositResponse the response from the anchor containing the instructions for the deposit.
     *
     * @throws InvalidSepRequest if there is an error in the request data. e.g. unknown asset code, invalid amount, etc.
     * @throws InvalidSep10JwtData if the data in the jwt token is invalid e.g. invalid account id.
     * @throws AnchorFailure if the anchor failed to handle the deposit request.
     */
    private function deposit(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): StartDepositResponse {
        $queryParameters = $request->getQueryParams();
        $this->logger->info(
            'Executing SEP-06 deposit.',
            ['context' => 'sep06', 'operation' => 'deposit', 'query_parameters' => json_encode($queryParameters)],
        );

        $assetCode = Sep06RequestParser::getAssetCodeFromRequestData($queryParameters);
        $account = Sep06RequestParser::getAccountFromRequestData($queryParameters);
        $memo = Sep06RequestParser::getMemoFromRequestData($queryParameters);
        $email = Sep06RequestParser::getEmailAddressFromRequestData($queryParameters);
        $type = Sep06RequestParser::getTypeFromRequestData($queryParameters);
        $lang = Sep06RequestParser::getLangFromRequestData($queryParameters);
        $callbackUrl = Sep06RequestParser::getOnChangeCallbackUrlFromRequestData($queryParameters);
        $amount = Sep06RequestParser::getAmountFromRequestData($queryParameters);
        $countryCode = Sep06RequestParser::getCountryCodeFromRequestData($queryParameters);
        $claimableBalancesSupported = Sep06RequestParser::getClaimableBalanceSupportedRequestData($queryParameters);
        $customerId = Sep06RequestParser::getCustomerIdFromRequestData($queryParameters);
        $locationId = Sep06RequestParser::getLocationIdFromRequestData($queryParameters);

        $this->logger->debug(
            'The request parameters after processing.',
            [
                'buy_asset_code' => $assetCode,
                'sell_asset' => $account,
                'amount' => $amount,
                'account' => $account,
                'memo' => $memo,
                'quote_id' => $email,
                'email' => $type,
                'type' => $lang,
                'lang' => $callbackUrl,
                'callback_url' => $amount,
                'country_code' => $countryCode,
                'claimable_balances_supported' => $claimableBalancesSupported,
                'customer_id' => $customerId,
                'location_id' => $locationId,
                'context' => 'sep06',
                'operation' => 'deposit',
            ],
        );

        //check asset
        $supportedAssets = $this->sep06Integration->supportedAssets();
        $depositAsset = Sep06RequestValidator::getDepositAsset($assetCode, $supportedAssets);

        // validate type
        if ($type !== null && $depositAsset->depositOperation->methods !== null) {
            Sep06RequestValidator::validateType($type, $assetCode, $depositAsset->depositOperation->methods);
        }

        // validate amount
        if ($amount !== null) {
            Sep06RequestValidator::validateAmount(
                $amount,
                $assetCode,
                $depositAsset->depositOperation->minAmount,
                $depositAsset->depositOperation->maxAmount,
            );
        }

        // check if the account must exist
        if (!$this->sep06Config->isAccountCreationSupported()) {
            try {
                $sdk = new StellarSDK($this->appConfig->getHorizonUrl());
                $exists = $sdk->accountExists($account);
                if (!$exists) {
                    throw new InvalidSepRequest(
                        'Account creation not supported. Account ' . $account . ' not found.',
                    );
                }
            } catch (HorizonRequestException $ex) {
                $error = 'Could not check if account ' . $account . ' exists';

                throw new AnchorFailure($error, 500);
            }
        }

        /**
         * @var ?string $sep10AccountMemo
         */
        $sep10AccountMemo = null;

        $sep10AccountData = $jwtToken->getValidatedAccountData();
        if (isset($sep10AccountData['account_id'])) {
            $sep10AccountId = $sep10AccountData['account_id'];
        } else {
            throw new InvalidSepRequest('invalid jwt token');
        }
        if (isset($sep10AccountData['account_memo'])) {
            $sep10AccountMemo = $sep10AccountData['account_memo'];
        }

        $startDepositRequest = new StartDepositRequest(
            depositAsset: $depositAsset,
            account: $account,
            sep10Account: $sep10AccountId,
            sep10AccountMemo: $sep10AccountMemo,
            memo: $memo,
            email: $email,
            type: $type,
            lang:$lang,
            onChangeCallbackUrl: $callbackUrl,
            amount: $amount,
            countryCode: $countryCode,
            claimableBalanceSupported: $claimableBalancesSupported,
            customerId: $customerId,
            locationId: $locationId,
            clientDomain: $jwtToken->clientDomain,
        );
        $this->logger->debug(
            'Start deposit request.',
            ['context' => 'sep06', 'operation' => 'deposit',
                'start_deposit_request' => json_encode($startDepositRequest),
            ],
        );

        $deposit = $this->sep06Integration->deposit($startDepositRequest);
        $this->logger->debug(
            'The deposit.',
            ['context' => 'sep06', 'operation' => 'deposit',
                'deposit_exchange' => json_encode($deposit),
            ],
        );

        return $deposit;
    }

    /**
     * Handles a withdrawal request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return StartWithdrawResponse the response from the anchor containing the instructions for the withdrawal.
     *
     * @throws InvalidSepRequest if there is an error in the request data. e.g. unknown asset code, invalid amount, etc.
     * @throws InvalidSep10JwtData if the data in the jwt token is invalid e.g. invalid account id.
     * @throws AnchorFailure if the anchor failed to handle the withdrawal request.
     */
    private function withdraw(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): StartWithdrawResponse {
        $queryParameters = $request->getQueryParams();
        $this->logger->info(
            'Executing SEP-06 withdraw.',
            ['context' => 'sep06', 'operation' => 'withdraw', 'query_parameters' => json_encode($queryParameters)],
        );

        $assetCode = Sep06RequestParser::getAssetCodeFromRequestData($queryParameters);
        $type = Sep06RequestParser::getTypeFromRequestData($queryParameters);
        if ($type === null) {
            throw new InvalidSepRequest('missing type');
        }

        $accountOpt = Sep06RequestParser::getAccountOptionalFromRequestData($queryParameters);
        $lang = Sep06RequestParser::getLangFromRequestData($queryParameters);
        $callbackUrl = Sep06RequestParser::getOnChangeCallbackUrlFromRequestData($queryParameters);
        $amount = Sep06RequestParser::getAmountFromRequestData($queryParameters);
        $countryCode = Sep06RequestParser::getCountryCodeFromRequestData($queryParameters);
        $customerId = Sep06RequestParser::getCustomerIdFromRequestData($queryParameters);
        $locationId = Sep06RequestParser::getLocationIdFromRequestData($queryParameters);
        $refundMemo = Sep06RequestParser::getRefundMemoFromRequestData($queryParameters);
        $this->logger->debug(
            'The request parameters after processing.',
            [
                'account_opt' => $accountOpt,
                'lang' => $lang,
                'callback_url' => $callbackUrl,
                'amount' => $amount,
                'country_code' => $countryCode,
                'customer_id' => $customerId,
                'location_id' => $locationId,
                'refund_memo' => $refundMemo,
                'context' => 'sep06',
                'operation' => 'withdraw',
            ],
        );

        //check asset
        $supportedAssets = $this->sep06Integration->supportedAssets();
        $withdrawAsset = Sep06RequestValidator::getWithdrawAsset($assetCode, $supportedAssets);

        // validate type
        if ($withdrawAsset->withdrawOperation->methods !== null) {
            Sep06RequestValidator::validateType($type, $assetCode, $withdrawAsset->withdrawOperation->methods);
        }

        // validate amount
        if ($amount !== null) {
            Sep06RequestValidator::validateAmount(
                $amount,
                $assetCode,
                $withdrawAsset->withdrawOperation->minAmount,
                $withdrawAsset->withdrawOperation->maxAmount,
            );
        }

        /**
         * @var ?string $sep10AccountMemo
         */
        $sep10AccountMemo = null;

        $sep10AccountData = $jwtToken->getValidatedAccountData();
        if (isset($sep10AccountData['account_id'])) {
            $sep10AccountId = $sep10AccountData['account_id'];
        } else {
            throw new InvalidSepRequest('invalid jwt token');
        }
        if (isset($sep10AccountData['account_memo'])) {
            $sep10AccountMemo = $sep10AccountData['account_memo'];
        }

        $account = $accountOpt ?? $sep10AccountId;

        $startWithdrawRequest = new StartWithdrawRequest(
            asset: $withdrawAsset,
            type: $type,
            sep10Account: $sep10AccountId,
            sep10AccountMemo: $sep10AccountMemo,
            account: $account,
            lang:$lang,
            onChangeCallbackUrl: $callbackUrl,
            amount: $amount,
            countryCode: $countryCode,
            refundMemo: $refundMemo,
            customerId: $customerId,
            locationId: $locationId,
            clientDomain: $jwtToken->clientDomain,
        );
        $this->logger->debug(
            'Start withdraw request.',
            ['context' => 'sep06', 'operation' => 'withdraw',
                'start_withdraw_request' => json_encode($startWithdrawRequest),
            ],
        );

        $withdraw = $this->sep06Integration->withdraw($startWithdrawRequest);
        $this->logger->debug(
            'Start withdraw request.',
            ['context' => 'sep06', 'operation' => 'withdraw',
                'withdraw' => json_encode($withdraw),
            ],
        );

        return $withdraw;
    }

    /**
     * Handles a withdraw exchange request.
     *
     * @param ServerRequestInterface $request as received from the client
     * @param Sep10Jwt $jwtToken jwt token previously received from SEP-10
     *
     * @return StartWithdrawResponse the response from the anchor containing the instructions for the withdrawal.
     *
     * @throws InvalidSepRequest if there is an error in the request data. e.g. unknown asset code, invalid amount, etc.
     * @throws InvalidSep10JwtData if the data in the jwt token is invalid e.g. invalid account id.
     * @throws AnchorFailure if the anchor failed to handle the deposit request.
     */
    private function withdrawExchange(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): StartWithdrawResponse {
        if ($this->quotesIntegration === null) {
            $this->logger->debug(
                'The $quotesIntegration is null.',
                ['context' => 'sep06', 'operation' => 'withdraw_exchange'],
            );

            throw new AnchorFailure('Unable to access quotes.', 500);
        }
        $queryParameters = $request->getQueryParams();
        $this->logger->info(
            'Executing SEP-06 withdraw exchange.',
            ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                'query_parameters' => json_encode($queryParameters),
            ],
        );
        $sellAssetCode = Sep06RequestParser::getSourceAssetCodeFromRequestData($queryParameters);
        $buyAsset = Sep06RequestParser::getDestinationAssetFromRequestData($queryParameters);
        $amount = Sep06RequestParser::getAmountFromRequestData($queryParameters);
        if ($amount === null) {
            throw new InvalidSepRequest('missing amount');
        }
        $account = Sep06RequestParser::getAccountOptionalFromRequestData($queryParameters);
        $quoteId = Sep06RequestParser::getQuoteIdFromRequestData($queryParameters);
        $type = Sep06RequestParser::getTypeFromRequestData($queryParameters);
        if ($type === null) {
            throw new InvalidSepRequest('missing type');
        }
        $lang = Sep06RequestParser::getLangFromRequestData($queryParameters);
        $callbackUrl = Sep06RequestParser::getOnChangeCallbackUrlFromRequestData($queryParameters);
        $countryCode = Sep06RequestParser::getCountryCodeFromRequestData($queryParameters);
        $customerId = Sep06RequestParser::getCustomerIdFromRequestData($queryParameters);
        $locationId = Sep06RequestParser::getLocationIdFromRequestData($queryParameters);
        $refundMemo = Sep06RequestParser::getRefundMemoFromRequestData($queryParameters);

        $this->logger->debug(
            'The request parameters after processing.',
            [
                'sell_asset_code' => $sellAssetCode,
                'buyAsset' => $buyAsset,
                'amount' => $amount,
                'account' => $account,
                'quote_id' => $quoteId,
                'type' => $type,
                'lang' => $lang,
                'callback_url' => $callbackUrl,
                'country_code' => $countryCode,
                'customer_id' => $customerId,
                'location_id' => $locationId,
                'refund_memo' => $refundMemo,
                'context' => 'sep06',
                'operation' => 'withdraw_exchange',
            ],
        );

        //check buy asset
        $supportedAssets = $this->sep06Integration->supportedAssets();
        $sellAsset = Sep06RequestValidator::getSourceAsset($sellAssetCode, $supportedAssets);

        /**
         * @var ?string $sep10AccountMemo
         */
        $sep10AccountMemo = null;

        $sep10AccountData = $jwtToken->getValidatedAccountData();
        if (isset($sep10AccountData['account_id'])) {
            $sep10AccountId = $sep10AccountData['account_id'];
        } else {
            throw new InvalidSepRequest('invalid jwt token');
        }
        if (isset($sep10AccountData['account_memo'])) {
            $sep10AccountMemo = $sep10AccountData['account_memo'];
        }

        // check destination asset
        /**
         * The off-chain asset the Anchor will deliver to the user's account. The value must match one of the asset
         * values included in a SEP-38 GET /prices?sell_asset=stellar:<source_asset>:<asset_issuer> response
         * using SEP-38 Asset Identification Format.
         */
        $pricesRequest = new Sep38PricesRequest(
            sellAsset: $sellAsset->asset,
            sellAmount: strval($amount),
            buyDeliveryMethod: $type,
            countryCode: $countryCode,
            accountId: $sep10AccountId,
            accountMemo: $sep10AccountMemo,
        );
        $this->logger->debug(
            'The price request.',
            ['context' => 'sep06', 'operation' => 'withdraw_exchange', 'prices_request' => json_encode($pricesRequest)],
        );
        $sep38BuyAssets = $this->quotesIntegration->getPrices($pricesRequest);
        $buyAssetFound = false;
        foreach ($sep38BuyAssets as $sep38BuyAsset) {
            if ($sep38BuyAsset->asset->getCode() === $buyAsset->getCode()) {
                $buyAssetFound = true;

                break;
            }
        }
        if (!$buyAssetFound) {
            throw new InvalidSepRequest('invalid operation for asset ' . $buyAsset->getStringRepresentation());
        }

        // validate type
        if ($sellAsset->withdrawOperation->methods !== null) {
            Sep06RequestValidator::validateType($type, $sellAssetCode, $sellAsset->withdrawOperation->methods);
        }

        // validate amount
        Sep06RequestValidator::validateAmount(
            $amount,
            $sellAsset->asset->getCode(),
            $sellAsset->withdrawOperation->minAmount,
            $sellAsset->withdrawOperation->maxAmount,
        );

        // validate with quote data
        if ($quoteId !== null) {
            $quote = $this->quotesIntegration->getQuoteById(
                id: $quoteId,
                accountId: $sep10AccountId,
                accountMemo: $sep10AccountMemo,
            );
            if ($quote->sellAsset->getStringRepresentation() !== $sellAsset->asset->getStringRepresentation()) {
                $this->logger->debug(
                    'Quote sell asset does not match source asset',
                    ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                        'source_asset' => $sellAsset->asset->getStringRepresentation(),
                    ],
                );

                throw new InvalidSepRequest(
                    'quote sell asset does not match source_asset ' .
                    $sellAsset->asset->getStringRepresentation(),
                );
            }
            if ($quote->buyAsset->getStringRepresentation() !== $buyAsset->getStringRepresentation()) {
                $this->logger->debug(
                    'Quote buy asset does not match destination asset',
                    ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                        'destination_asset' => $buyAsset->getStringRepresentation(),
                    ],
                );

                throw new InvalidSepRequest(
                    'quote buy asset does not match destination_asset ' .
                    $buyAsset->getStringRepresentation(),
                );
            }
            $amountStr = Sep06RequestParser::getStringValueFromRequestData('amount', $queryParameters);
            if ($quote->sellAmount !== $amountStr) {
                if (!is_numeric($quote->sellAmount) || floatval($quote->sellAmount) !== $amount) {
                    $this->logger->debug(
                        'Quote amount does not match request amount',
                        ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                            'buy_asset' => $buyAsset->getStringRepresentation(),
                            'quote_amount' => $quote->sellAmount, 'amount' => $amount,
                        ],
                    );

                    throw new InvalidSepRequest('quote amount does not match request amount');
                }
            }
        }

        $startWithdrawExchangeRequest = new StartWithdrawExchangeRequest(
            sourceAsset: $sellAsset,
            destinationAsset: $buyAsset,
            amount: $amount,
            sep10Account: $sep10AccountId,
            sep10AccountMemo: $sep10AccountMemo,
            account: $account,
            type: $type,
            quoteId: $quoteId,
            lang: $lang,
            onChangeCallbackUrl: $callbackUrl,
            countryCode: $countryCode,
            refundMemo: $refundMemo,
            customerId: $customerId,
            locationId: $locationId,
            clientDomain: $jwtToken->clientDomain,
        );
        $this->logger->debug(
            'Start withdraw exchange request.',
            ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                'start_withdraw_exchange_request' => json_encode($startWithdrawExchangeRequest),
            ],
        );

        $withdrawExchange = $this->sep06Integration->withdrawExchange($startWithdrawExchangeRequest);
        $this->logger->debug(
            'Start withdraw exchange request.',
            ['context' => 'sep06', 'operation' => 'withdraw_exchange',
                'withdraw_exchange' => json_encode($withdrawExchange),
            ],
        );

        return $withdrawExchange;
    }

    /**
     * Handles a get transaction request.
     *
     * @param ServerRequestInterface $request the request as obtained from the client.
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleGetTransactionRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            $accountData = $token->getValidatedAccountData();
            $accountMemo = null;
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            } else {
                throw new InvalidSepRequest('account id not found in jwt token');
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }
            $queryParameters = $request->getQueryParams();
            $this->logger->info(
                'Executing SEP-06 transaction.',
                ['context' => 'sep06', 'operation' => 'transaction',
                    'query_parameters' => json_encode($queryParameters),
                ],
            );

            $lang = null;
            if (isset($queryParameters['lang'])) {
                if (is_string($queryParameters['lang'])) {
                    $lang = $queryParameters['lang'];
                }
            }
            if (isset($queryParameters['id'])) {
                if (!is_string($queryParameters['id'])) {
                    $this->logger->debug(
                        'Transaction id must be a string.',
                        ['context' => 'sep06', 'operation' => 'transaction'],
                    );

                    throw new InvalidSepRequest('id must be a string');
                }
                $id = $queryParameters['id'];
                $result = $this->sep06Integration->findTransactionById(
                    $id,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } elseif (isset($queryParameters['stellar_transaction_id'])) {
                if (!is_string($queryParameters['stellar_transaction_id'])) {
                    throw new InvalidSepRequest('stellar_transaction_id must be a string');
                }
                $stellarTransactionId = $queryParameters['stellar_transaction_id'];
                $result = $this->sep06Integration->findTransactionByStellarTransactionId(
                    $stellarTransactionId,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } elseif (isset($queryParameters['external_transaction_id'])) {
                if (!is_string($queryParameters['external_transaction_id'])) {
                    throw new InvalidSepRequest('external_transaction_id must be a string');
                }
                $externalTransactionId = $queryParameters['external_transaction_id'];
                $result = $this->sep06Integration->findTransactionByExternalTransactionId(
                    $externalTransactionId,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } else {
                $error = 'One of id, stellar_transaction_id or external_transaction_id is required.';

                throw new InvalidSepRequest($error);
            }
            if ($result !== null) {
                $resultJson = $result->toJson();
                $this->logger->debug(
                    'Executing SEP-06 transaction.',
                    ['context' => 'sep06', 'operation' => 'transaction',
                        'transaction' => $resultJson,
                    ],
                );

                return new JsonResponse(['transaction' => $resultJson], 200);
            } else {
                $this->logger->error(
                    'Transaction not found',
                    ['context' => 'sep06', 'operation' => 'transaction', 'http_status_code' => 404],
                );

                return new JsonResponse(['error' => 'transaction not found'], 404);
            }
        } catch (InvalidSep10JwtData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to retrieve the transaction.',
                ['context' => 'sep06', 'operation' => 'transaction', 'http_status_code' => 400, 'exception' => $e],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handles a get transactions request (transaction history).
     *
     * @param ServerRequestInterface $request the request as obtained from the client.
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10
     *
     * @return ResponseInterface the response that can be sent back to the client.
     */
    private function handleGetTransactionsRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            $accountData = $token->getValidatedAccountData();
            $accountMemo = null;
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            } else {
                throw new InvalidSepRequest('account id not found in jwt token');
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }
            $queryParameters = $request->getQueryParams();
            $this->logger->info(
                'Executing SEP-06 transactions.',
                ['context' => 'sep06', 'operation' => 'transactions',
                    'query_parameters' => json_encode($queryParameters),
                ],
            );

            $request = Sep06RequestParser::getTransactionsRequestFromRequestData($queryParameters);

            $result = $this->sep06Integration->getTransactionHistory($request, $accountId, $accountMemo);

            if ($result === null || count($result) === 0) {
                $this->logger->info(
                    'The transactions list is empty.',
                    ['context' => 'sep06', 'operation' => 'transactions'],
                );

                return new JsonResponse([], 200);
            } else {
                $this->logger->info(
                    'Transactions found.',
                    ['context' => 'sep06', 'operation' => 'transactions', 'no_transactions' => count($result)],
                );

                $transactionsJson = [];
                foreach ($result as $tx) {
                    $transactionsJson[] = $tx->toJson();
                }

                $this->logger->debug(
                    'Executing SEP-06 transaction.',
                    ['context' => 'sep06', 'operation' => 'transactions',
                        'transactions' => $transactionsJson,
                    ],
                );

                return new JsonResponse(['transactions' => $transactionsJson], 200);
            }
        } catch (InvalidSep10JwtData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to retrieve the transactions.',
                ['context' => 'sep06', 'operation' => 'transactions', 'http_status_code' => 400, 'exception' => $e],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Composes the formatted info response by loading the needed data from the server callback and config.
     *
     * @return ResponseInterface info response that can be sent back to the client.
     */
    private function buildInfo(): ResponseInterface
    {
        $this->logger->info(
            'Executing SEP-06 info.',
            ['context' => 'sep06', 'operation' => 'info'],
        );
        $supportedAssets = $this->sep06Integration->supportedAssets();

        /**
         * @var array<string, mixed> $data
         */
        $data = [];

        /**
         * @var array<string, mixed> $depositData
         */
        $depositData = [];

        /**
         * @var array<string, mixed> $withdrawData
         */
        $withdrawData = [];

        /**
         * @var array<string, mixed> $depositExchangeData
         */
        $depositExchangeData = [];

        /**
         * @var array<string, mixed> $withdrawExchangeData
         */
        $withdrawExchangeData = [];

        $this->logger->debug(
            'The list of all assets.',
            ['context' => 'sep06', 'operation' => 'info', 'supported_assets' => json_encode($supportedAssets)],
        );
        foreach ($supportedAssets as $supportedAsset) {
            $code = $supportedAsset->asset->getCode();
            $depositOp = $supportedAsset->depositOperation;
            /**
             * @var array<string, mixed> $depositValues
             */
            $depositValues = ['enabled' => $depositOp->enabled];
            if ($depositOp->enabled) {
                $depositValues += ['authentication_required' => true];

                if ($depositOp->minAmount !== null) {
                    $depositValues += ['min_amount' => $depositOp->minAmount];
                }
                if ($depositOp->maxAmount !== null) {
                    $depositValues += ['max_amount' => $depositOp->maxAmount];
                }
                if ($depositOp->feeFixed !== null) {
                    $depositValues += ['fee_fixed' => $depositOp->feeFixed];
                }
                if ($depositOp->feePercent !== null) {
                    $depositValues += ['fee_percent' => $depositOp->feePercent];
                }
                if ($depositOp->methods !== null && count($depositOp->methods) > 0) {
                    $depositValues += [
                        'fields' =>
                            ['type' =>
                                [
                                    'description' => 'type of deposit to make',
                                    'choices' => $depositOp->methods,
                                ],
                            ],
                    ];
                }
            }

            $depositData += [$code => $depositValues];
            if ($supportedAsset->depositExchangeEnabled) {
                $depositExchangeData += [$code => $depositValues];
            }

            $withdrawOp = $supportedAsset->withdrawOperation;
            /**
             * @var array<string, mixed> $withdrawValues
             */
            $withdrawValues = ['enabled' => $withdrawOp->enabled];

            if ($withdrawOp->enabled) {
                $withdrawValues += ['authentication_required' => true];

                if ($withdrawOp->minAmount !== null) {
                    $withdrawValues += ['min_amount' => $withdrawOp->minAmount];
                }
                if ($withdrawOp->maxAmount !== null) {
                    $withdrawValues += ['max_amount' => $withdrawOp->maxAmount];
                }
                if ($withdrawOp->feeFixed !== null) {
                    $withdrawValues += ['fee_fixed' => $withdrawOp->feeFixed];
                }
                if ($withdrawOp->feePercent !== null) {
                    $withdrawValues += ['fee_percent' => $withdrawOp->feePercent];
                }
                if ($withdrawOp->methods !== null && count($withdrawOp->methods) > 0) {
                    /**
                     * @var array<string, mixed> $methods
                     */
                    $methods = [];
                    foreach ($withdrawOp->methods as $method) {
                        $methods += [$method => ['fields' => new ArrayObject()]];
                    }
                    $withdrawValues += ['types' => $methods];
                }
            }
            $withdrawData += [$code => $withdrawValues];

            if ($supportedAsset->withdrawExchangeEnabled) {
                $withdrawExchangeData += [$code => $withdrawValues];
            }
        }

        if (count($depositData) > 0) {
            $data += ['deposit' => $depositData];
        }
        if (count($depositExchangeData) > 0) {
            $data += ['deposit-exchange' => $depositExchangeData];
        }
        if (count($withdrawData) > 0) {
            $data += ['withdraw' => $withdrawData];
        }
        if (count($withdrawExchangeData) > 0) {
            $data += ['withdraw-exchange' => $withdrawExchangeData];
        }
        $data += ['fee' => ['enabled' => false, 'description' => 'Fee endpoint is not supported.']];
        $data += ['transaction' => ['enabled' => true, 'authentication_required' => true]];
        $data += ['transactions' => ['enabled' => true, 'authentication_required' => true]];
        $data += [
            'features' => [
                'account_creation' => $this->sep06Config->isAccountCreationSupported(),
                'claimable_balances' => $this->sep06Config->areClaimableBalancesSupported(),
            ],
        ];

        $this->logger->debug(
            'The assembled data.',
            ['context' => 'sep06', 'operation' => 'info', 'data' => json_encode($data)],
        );

        return new JsonResponse($data, 200);
    }
}
