<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\GetCustomerResponse;
use ArgoNavis\PhpAnchorSdk\callback\ICustomerIntegration;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerCallbackRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerResponse;
use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use ArgoNavis\PhpAnchorSdk\config\ISep12Config;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\CustomerNotFoundForId;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\exception\LocalizedExceptionHelper;
use ArgoNavis\PhpAnchorSdk\exception\SepNotAuthorized;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Throwable;

use function array_pop;
use function explode;
use function intval;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function str_contains;
use function trim;

/**
 * The Sep12Service handles KYC requests as defined by
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md">SEP-12 KYC API</a>
 *
 * To create an instance of the service, you have to pass a business logic callback class that implements
 * ICustomerIntegration to the service constructor. This is needed, so that the service can load and store customer data.
 * Optionally, you can also pass a class implementing ISep12Config. It defines the maximum size and number
 * of files that can be uploaded. If not provided, default values will be used.
 *
 * After initializing the service it can be used within the server implementation by passing all
 * SEP-12 kyc requests to its method handleRequest. It will handle them and return the corresponding response
 * that can be sent back to the client. During the handling it will call methods from the callback implementation
 * (ICustomerIntegration) provided by the server.
 *
 *  See: <a href="https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-12.md">SDK SEP-12 docs</a>
 */
class Sep12Service
{
    public IAppConfig $appConfig;
    public ICustomerIntegration $customerIntegration;
    private ?ISep12Config $config;
    private int $uploadFileMaxSize = 2097152; // 2 MB
    private int $uploadFileMaxCount = 6;
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    /**
     * Constructor.
     *
     * @param ICustomerIntegration $customerIntegration the callback class containing the needed business
     * logic to load and store data, etc. See ICustomerIntegration description.
     * @param IAppConfig $appConfig the app config containing the needed configuration data.
     * @param ISep12Config|null $config SEP-12 config containing info about the max size and number of files
     * @param LoggerInterface|null $logger the PSR-3 logger to be used for logging.
     * allowed to be uploaded.
     */
    public function __construct(
        ICustomerIntegration $customerIntegration,
        IAppConfig $appConfig,
        ?ISep12Config $config = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        Sep10Jwt::setLogger($this->logger);
        $this->customerIntegration = $customerIntegration;
        $this->appConfig = $appConfig;
        $this->config = $config;
        if ($this->config !== null) {
            $fMaxSizeMb = $this->config->getUploadFileMaxSizeMb();
            if ($fMaxSizeMb !== null) {
                $this->uploadFileMaxSize = $fMaxSizeMb * 1048576;
            }
            $fMaxCount = $this->config->getUploadFileMaxCount();
            if ($fMaxCount !== null) {
                $this->uploadFileMaxCount = $fMaxCount;
            }
            $this->logger->debug(
                'Configuration attributes loaded successfully',
                ['context' => 'sep12', 'file_max_size_in_mb' => $fMaxSizeMb, 'file_max_count' => $fMaxCount],
            );
        }
        Sep12RequestParser::setLogger($this->logger);
        MemoHelper::setLogger($this->logger);
        RequestBodyDataParser::setLogger($this->logger);
    }

    /**
     * Handles a forwarded client request specified by SEP-12. Builds and returns the corresponding response,
     * that can be sent back to the client.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     * @param Sep10Jwt $token the validated jwt token obtained earlier by SEP-10
     *
     * @return ResponseInterface the response that should be sent back to the client.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>
     */
    public function handleRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        $this->logger->info(
            'Handling incoming request.',
            ['context' => 'sep12', 'method' => $request->getMethod()],
        );

        if ($request->getMethod() === 'GET') {
            $this->logger->info(
                'Executing get customer info request.',
                ['context' => 'sep12', 'operation' => 'customer_info'],
            );
            $customerRequest = null;
            try {
                $queryParams = $request->getQueryParams();
                $this->logger->debug(
                    'Query parameters before processing.',
                    ['context' => 'sep12', 'operation' => 'customer_info',
                        'query_parameters' => json_encode($queryParams),
                    ],
                );

                $customerRequest = Sep12RequestParser::getCustomerRequestFromRequestData($queryParams, $token);
                $customer = $this->getCustomer($token, $customerRequest);
                $this->logger->info(
                    'Request executed successfully.',
                    ['context' => 'sep12', 'operation' => 'customer_info'],
                );

                return new JsonResponse($customer->toJson(), 200);
            } catch (CustomerNotFoundForId $e) {
                $this->logger->error(
                    'Customer not found for ID.',
                    ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 404,
                        'customer_id' => $e->id,
                    ],
                );
                $localizedError = LocalizedExceptionHelper::getLocalizedErrorMsgFromException(
                    $e,
                    $this->appConfig,
                    $customerRequest?->lang ?? 'en',
                );

                return new JsonResponse(['error' => $localizedError], 404);
            } catch (SepNotAuthorized $e) {
                $this->logger->error(
                    'SEP not authorized.',
                    ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 401],
                );

                $localizedError = LocalizedExceptionHelper::getLocalizedErrorMsgFromException(
                    $e,
                    $this->appConfig,
                    $customerRequest?->lang ?? 'en',
                );

                return new JsonResponse(['error' => $localizedError], 401);
            } catch (InvalidSepRequest | InvalidSepRequest | AnchorFailure $e) {
                $this->logger->error(
                    'Invalid request.',
                    ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 400],
                );

                $localizedError = LocalizedExceptionHelper::getLocalizedErrorMsgFromException(
                    $e,
                    $this->appConfig,
                    $customerRequest?->lang ?? 'en',
                );

                return new JsonResponse(['error' => $localizedError], 400);
            }
        } elseif ($request->getMethod() === 'PUT') {
            $requestTarget = $request->getRequestTarget();
            $this->logger->info(
                'Executing incoming request by target.',
                ['context' => 'sep12', 'request_target' => $requestTarget],
            );

            if (str_contains($requestTarget, '/customer/verification')) {
                $this->logger->info(
                    'Executing customer verification request.',
                    ['context' => 'sep12', 'operation' => 'customer_verification'],
                );

                return $this->handlePutCustomerVerification($token, $request);
            } elseif (str_contains($requestTarget, '/customer/callback')) {
                $this->logger->info(
                    'Executing customer callback request.',
                    ['context' => 'sep12', 'operation' => 'put_customer_callback'],
                );

                return $this->handlePutCustomerCallback($token, $request);
            } elseif (str_contains($requestTarget, '/customer')) {
                $this->logger->info(
                    'Executing put customer request.',
                    ['context' => 'sep12', 'operation' => 'put_customer'],
                );

                return $this->handlePutCustomer($token, $request);
            } else {
                $this->logger->error(
                    'Invalid request, unknown endpoint.',
                    ['context' => 'sep12', 'http_status_code' => '404'],
                );

                return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
            }
        } elseif ($request->getMethod() === 'DELETE') {
            $this->logger->info(
                'Executing delete customer request.',
                ['context' => 'sep12', 'operation' => 'delete_customer'],
            );

            return $this->handleDeleteCustomer($token, $request);
        } else {
            $this->logger->error(
                'Invalid request, unknown endpoint.',
                ['context' => 'sep12', 'http_status_code' => '404'],
            );

            return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
        }
    }

    /**
     * Handles a put customer request.
     *
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10.
     * @param ServerRequestInterface $request the request from the client.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handlePutCustomer(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $requestData = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            $uploadedFiles = null;
            if ($requestData instanceof MultipartFormDataset) {
                $uploadedFiles = $requestData->uploadedFiles;
                $requestData = $requestData->bodyParams;
            }
            $putCustomerRequest = Sep12RequestParser::putCustomerRequestFormRequestData(
                $token,
                $requestData,
                $uploadedFiles,
            );
            $response = $this->putCustomer($token, $putCustomerRequest);
            $this->logger->info(
                'Request executed successfully.',
                ['context' => 'sep12', 'operation' => 'put_customer'],
            );

            return new JsonResponse($response->toJson(), 200);
        } catch (CustomerNotFoundForId $e) {
            $this->logger->error(
                'Customer not found by ID.',
                ['context' => 'sep12', 'operation' => 'put_customer',
                    'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 404, 'customer_id' => $e->id,
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (SepNotAuthorized $e) {
            $this->logger->error(
                'SEP not authorized.',
                ['context' => 'sep12', 'operation' => 'put_customer',
                    'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 401,
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 401);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to execute the request.',
                ['context' => 'sep12', 'operation' => 'put_customer',
                    'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer. ' . $e->getMessage();
            $this->logger->error(
                'Failed to put customer.',
                ['context' => 'sep12', 'operation' => 'put_customer',
                    'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 500,
                ],
            );

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    /**
     * Handles a put customer callback request.
     *
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10.
     * @param ServerRequestInterface $request the request from the client.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handlePutCustomerCallback(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $requestData = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            if ($requestData instanceof MultipartFormDataset) {
                $requestData = $requestData->bodyParams;
            }
            $putCustomerCallbackRequest = Sep12RequestParser::putCustomerCallbackRequestFormRequestData(
                $token,
                $requestData,
            );

            $this->putCustomerCallback($token, $putCustomerCallbackRequest);
            $this->logger->info(
                'Request executed successfully.',
                ['context' => 'sep12', 'operation' => 'put_customer_callback'],
            );

            return new Response\EmptyResponse(200);
        } catch (CustomerNotFoundForId $e) {
            $this->logger->error(
                'Customer not found for ID.',
                ['context' => 'sep12', 'error' => $e->getMessage(),
                    'exception' => $e, 'http_status_code' => 404, 'operation' => 'put_customer_callback',
                    'customer_id' => $e->id,
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (SepNotAuthorized $e) {
            $this->logger->error(
                'SEP not authorized.',
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 401, 'operation' => 'put_customer_callback',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 401);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to execute the request.',
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'put_customer_callback',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer callback. ' . $e->getMessage();
            $this->logger->error(
                $msg,
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'put_customer_callback',
                ],
            );

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    /**
     * Handles a customer data verification request.
     *
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10.
     * @param ServerRequestInterface $request the request from the client.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handlePutCustomerVerification(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $requestData = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            if ($requestData instanceof MultipartFormDataset) {
                $requestData = $requestData->bodyParams;
            }
            $putCustomerValidationRequest =
                Sep12RequestParser::putCustomerVerificationRequestFormRequestData($token, $requestData);

            $response = $this->customerIntegration->putCustomerVerification($putCustomerValidationRequest);
            $this->logger->info(
                'Request executed successfully.',
                ['context' => 'sep12', 'operation' => 'customer_verification'],
            );

            return new JsonResponse($response->toJson(), 200);
        } catch (CustomerNotFoundForId $e) {
            $this->logger->error(
                'Customer not found for ID.',
                ['context' => 'sep12', 'error' => $e->getMessage(),
                    'exception' => $e, 'http_status_code' => 404, 'operation' => 'customer_verification',
                    'customer_id' => $e->id,
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (SepNotAuthorized $e) {
            $this->logger->error(
                'SEP not authorized.',
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 401, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 401);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to execute the request.',
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer verification. ' . $e->getMessage();
            $this->logger->error(
                $msg,
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    /**
     * Handles a delete customer request.
     *
     * @param Sep10Jwt $token the jwt token previously obtained by SEP-10.
     * @param ServerRequestInterface $request the request from the client.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleDeleteCustomer(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            if ($data instanceof MultipartFormDataset) {
                $data = $data->bodyParams;
            }

            if (isset($data['memo_type'])) {
                if (is_string($data['memo_type'])) {
                    $memoType = $data['memo_type'];
                    $this->logger->debug(
                        'Memo type value',
                        ['context' => 'sep12', 'operation' => 'delete_customer', 'memo_type' => $memoType],
                    );

                    if ($memoType !== 'id') {
                        throw new InvalidSepRequest('only memo type id supported');
                    }
                } else {
                    throw new InvalidSepRequest('memo_type must be a string');
                }
            }

            $memo = null;
            if (isset($data['memo'])) {
                if (is_string($data['memo'])) {
                    $memoStr = $data['memo'];
                    $this->logger->debug(
                        'Memo value',
                        ['context' => 'sep12', 'operation' => 'delete_customer', 'memo' => $memoStr],
                    );
                    if (is_numeric($memoStr) && is_int($memoStr + 0)) {
                        $memo = intval($memoStr);
                    } else {
                        $error = 'invalid memo value';
                        $this->logger->debug(
                            $error,
                            ['context' => 'sep12', 'operation' => 'delete_customer', 'memo_string' => $memoStr],
                        );

                        throw new InvalidSepRequest($error . ': ' . $memoStr);
                    }
                } else {
                    throw new InvalidSepRequest('memo must be a string');
                }
            }

            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $account = array_pop($path);
            $this->logger->debug(
                'Account value',
                ['context' => 'sep12', 'operation' => 'delete_customer', 'account' => $account],
            );

            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable $th) {
                throw new InvalidSepRequest('invalid account id ' . $account);
            }

            if (trim($account) !== '') {
                $this->deleteCustomer($token, $account, $memo);
                $this->logger->info(
                    'Request executed successfully.',
                    ['context' => 'sep12', 'operation' => 'delete_customer'],
                );

                return new Response\EmptyResponse(200);
            } else {
                throw new InvalidSepRequest('missing account in request');
            }
        } catch (SepNotAuthorized $notAuthorized) {
            $this->logger->error(
                'SEP not authorized.',
                ['context' => 'sep12', 'error' => $notAuthorized->getMessage(), 'exception' => $notAuthorized,
                    'http_status_code' => 401, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $notAuthorized->getMessage()], 401);
        } catch (CustomerNotFoundForId $notFound) {
            $this->logger->error(
                'Customer not found for ID.',
                ['context' => 'sep12', 'error' => $notFound->getMessage(),
                    'exception' => $notFound, 'http_status_code' => 404, 'operation' => 'delete_customer',
                    'customer_id' => $notFound->id,
                ],
            );

            return new JsonResponse(['error' => $notFound->getMessage()], 404);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $e) {
            $this->logger->error(
                'Failed to execute the request.',
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to delete customer. ' . $e->getMessage();
            $this->logger->error(
                $msg,
                ['context' => 'sep12', 'error' => $e->getMessage(), 'exception' => $e,
                    'http_status_code' => 400, 'operation' => 'customer_verification',
                ],
            );

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    /**
     * Creates the get customer response for the given request data.
     * Checks if the caller is allowed to obtain the data.
     *
     * @param Sep10Jwt $token the token previously obtained by SEP-10
     * @param GetCustomerRequest $request the request data.
     *
     * @return GetCustomerResponse containing the data to be sent back to the caller.
     *
     * @throws SepNotAuthorized if the caller is not authorized to obtain the requested data.
     * @throws InvalidSepRequest if the request data is invalid.
     * @throws AnchorFailure if the server failed to load the data from its data source.
     */
    private function getCustomer(Sep10Jwt $token, GetCustomerRequest $request): GetCustomerResponse
    {
        $sep12RequestBase = Sep12CustomerRequestBase::fromGetCustomerRequest($request);
        $this->validateGetOrPutRequest($sep12RequestBase, $token);

        // ignore memo from initial request as it matches the tokens memo or is irrelevant.
        $request->memo = Sep12RequestParser::tokenAccountMemoAsInt($token);

        return $this->customerIntegration->getCustomer($request);
    }

    /**
     * Creates the put customer response for the given request data.
     * Checks if the caller is allowed to put the data.
     *
     * @param Sep10Jwt $token the token previously obtained by SEP-10
     * @param PutCustomerRequest $request the request data.
     *
     * @return PutCustomerResponse containing the data to be sent back to the caller.
     *
     * @throws SepNotAuthorized if the caller is not authorized to put the data.
     * @throws AnchorFailure if the server failed to put the data into its data source.
     * @throws InvalidSepRequest if the request data is invalid.
     */
    private function putCustomer(Sep10Jwt $token, PutCustomerRequest $request): PutCustomerResponse
    {
        $sep12RequestBase = Sep12CustomerRequestBase::fromPutCustomerRequest($request);
        $this->validateGetOrPutRequest($sep12RequestBase, $token);

        // ignore memo from initial request as it matches the tokens memo or is irrelevant.
        $request->memo = Sep12RequestParser::tokenAccountMemoAsInt($token);

        return $this->customerIntegration->putCustomer($request);
    }

    /**
     * Stores the customer callback from the request data.
     *
     * @param Sep10Jwt $token the token previously obtained by SEP-10
     * @param PutCustomerCallbackRequest $request the request data.
     *
     * @throws SepNotAuthorized if the caller is not authorized to put the data.
     * @throws AnchorFailure if the server failed to put the data into its data source.
     * @throws CustomerNotFoundForId if there is no customer for the defined customer id in the request.
     */
    private function putCustomerCallback(Sep10Jwt $token, PutCustomerCallbackRequest $request): void
    {
        $sep12RequestBase = Sep12CustomerRequestBase::fromPutCustomerCallbackRequest($request);
        $this->validateGetOrPutRequest($sep12RequestBase, $token);

        // ignore memo from initial request as it matches the tokens memo or is irrelevant.
        $request->memo = Sep12RequestParser::tokenAccountMemoAsInt($token);

        $this->customerIntegration->putCustomerCallback($request);
    }

    /**
     * Deletes the customer data for the given account id and memo.
     *
     * @param Sep10Jwt $sep10Jwt the token previously obtained by SEP-10
     * @param string $accountId the stellar account id of the customer
     * @param int|null $memo memo if the customer is identified by account id and memo
     *
     * @throws SepNotAuthorized if the caller is not authorized to delete the data.
     * @throws AnchorFailure if the server failed to delete the customer data.
     */
    private function deleteCustomer(
        Sep10Jwt $sep10Jwt,
        string $accountId,
        ?int $memo = null,
    ): void {
        // check if the account id of the customer to delete the data for matches tho the account id from the jwt token.
        $isAccountAuthenticated = ($sep10Jwt->accountId === $accountId || $sep10Jwt->muxedAccountId === $accountId);
        $this->logger->debug(
            'Is account authenticated',
            ['context' => 'sep12', 'operation' => 'delete_customer',
                'is_account_authenticated' => $isAccountAuthenticated,
            ],
        );

        // if the customer is identified by account and memo we also need to check the memo.
        $isMemoMissingAuthentication = false;
        $muxedId = $sep10Jwt->muxedId;
        $this->logger->debug(
            'The muxed id.',
            ['context' => 'sep12', 'operation' => 'delete_customer', 'muxed_id' => $muxedId],
        );

        if ($muxedId !== null) {
            if ($sep10Jwt->muxedAccountId !== $accountId) {
                $isMemoMissingAuthentication = ($muxedId !== $memo);
            }
        } elseif ($sep10Jwt->accountMemo !== null) {
            $isMemoMissingAuthentication = (Sep12RequestParser::tokenAccountMemoAsInt($sep10Jwt) !== $memo);
        }

        if (!$isAccountAuthenticated || $isMemoMissingAuthentication) {
            $this->logger->debug(
                'Not authorized to delete account.',
                ['context' => 'sep12', 'operation' => 'delete_customer',
                    'is_account_authenticated' => $isAccountAuthenticated,
                    'is_memo_missing_authentication' => $isMemoMissingAuthentication,
                ],
            );

            throw new SepNotAuthorized('Not authorized to delete account.');
        }
        $getCustomerRequest = new GetCustomerRequest($accountId, $memo);

        // in future (e.g. when implementing sep-31) this must be extended with a loop
        // that deletes the customer for all types. (the customer can have different ids depending on the type).
        // $getCustomerRequest->type = $type;

        $getCustomerResponse = $this->customerIntegration->getCustomer($getCustomerRequest);
        if ($getCustomerResponse->id !== null) {
            $this->customerIntegration->deleteCustomer($getCustomerResponse->id);
        } else {
            throw new CustomerNotFoundForId($accountId);
        }
    }

    /**
     * Validates the base request data by checking if the caller data corresponds to the
     * jwt token data (account id + memo)
     *
     * @param Sep12CustomerRequestBase $request base request data.
     * @param Sep10Jwt $token jwt token previously received by SEP-10
     *
     * @throws SepNotAuthorized if the caller is not authorized.
     * @throws InvalidSepRequest if the jwt token contains an invalid memo
     */
    private function validateGetOrPutRequest(Sep12CustomerRequestBase $request, Sep10Jwt $token): void
    {
        $tokenAccountId = $token->accountId;
        $tokenMuxedAccountId = $token->muxedAccountId;
        $customerAccountId = $request->account;

        // check if the caller is authorized to obtain the data.
        // not authorized if the customer from the jwt token does not match the data customer data from the request
        // account id + memo must match
        if (
            $customerAccountId !== null
            && ($tokenAccountId !== $customerAccountId && $tokenMuxedAccountId !== $customerAccountId)
        ) {
            $this->logger->error(
                'The account specified does not match authorization token',
                ['context' => 'sep12'],
            );

            throw new SepNotAuthorized('The account specified does not match authorization token');
        }

        $tokenSubMemo = Sep12RequestParser::tokenAccountMemoAsInt($token);
        $tokenMuxedId = $token->muxedId;
        $tokenMemo = $tokenMuxedId ?? $tokenSubMemo;

        // SEP-12 says: If the JWT's `sub` field does not contain a muxed account or memo then the memo
        // request parameters may contain any value.

        if ($tokenMemo === null) {
            return;
        } elseif ($request->memo !== null && $tokenMemo !== $request->memo) {
            throw new SepNotAuthorized('The memo specified does not match the memo ID authorized via SEP-10');
        }
    }
}
