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
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerResponse;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\CustomerNotFoundForId;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\exception\SepNotAuthorized;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Xdr\XdrMemoType;
use Throwable;

use function array_pop;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function str_contains;
use function strval;
use function trim;

class Sep12Service
{
    public ICustomerIntegration $customerIntegration;

    public function __construct(ICustomerIntegration $customerIntegration)
    {
        $this->customerIntegration = $customerIntegration;
    }

    public function handleRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            try {
                $queryParams = $request->getQueryParams();
                $customerRequest = Sep12RequestParser::getCustomerRequestFromRequestData($queryParams);
                $customer = $this->getCustomer($token, $customerRequest);

                return new JsonResponse($customer->toJson(), 200);
            } catch (CustomerNotFoundForId $e) {
                return new JsonResponse(['error' => $e->getMessage()], 404);
            } catch (InvalidSepRequest | AnchorFailure $e) {
                return new JsonResponse(['error' => $e->getMessage()], 400);
            }
        } elseif ($request->getMethod() === 'PUT') {
            $requestTarget = $request->getRequestTarget();
            if (str_contains($requestTarget, '/customer/verification')) {
                return $this->handlePutCustomerVerification($request);
            } elseif (str_contains($requestTarget, '/customer')) {
                return $this->handlePutCustomer($token, $request);
            } elseif (str_contains($requestTarget, '/customer/callback')) {
                return new JsonResponse(['error' => 'Not implemented.'], 404);
            } else {
                return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
            }
        } elseif ($request->getMethod() === 'DELETE') {
            return $this->handleDeleteCustomer($token, $request);
        } else {
            return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
        }
    }

    private function handlePutCustomer(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $requestData = $this->getBodyData($request);
            $uploadedFiles = $request->getUploadedFiles();
            $putCustomerRequest = Sep12RequestParser::putCustomerRequestFormRequestData($requestData, $uploadedFiles);
            $response = $this->putCustomer($token, $putCustomerRequest);

            return new JsonResponse($response->toJson(), 200);
        } catch (InvalidRequestData $invalid) {
            return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer. ' . $e->getMessage();

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    private function handlePutCustomerVerification(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $requestData = $this->getBodyData($request);
            $putCustomerValidationRequest =
                Sep12RequestParser::putCustomerVerificationRequestFormRequestData($requestData);
            $response = $this->customerIntegration->putCustomerVerification($putCustomerValidationRequest);

            return new JsonResponse($response->toJson(), 200);
        } catch (InvalidRequestData $invalid) {
            return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer validation. ' . $e->getMessage();

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    private function handleDeleteCustomer(Sep10Jwt $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->getBodyData($request);

            $memo = null;
            if (isset($data['memo'])) {
                if (is_string($data['memo'])) {
                    $memo = $data['memo'];
                } else {
                    throw new InvalidSepRequest('memo must be a string');
                }
            }

            $memoType = null;
            if (isset($data['memo_type'])) {
                if (is_string($data['memo_type'])) {
                    $memoType = $data['memo_type'];
                } else {
                    throw new InvalidSepRequest('memo_type must be a string');
                }
            }

            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $account = array_pop($path);
            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable) {
                throw new InvalidSepRequest('invalid account id ' . $account);
            }

            if (trim($account) !== '') {
                $this->deleteCustomer($token, $account, $memo, $memoType);

                return new Response\EmptyResponse(200);
            } else {
                throw new InvalidSepRequest('missing account in request');
            }
        } catch (InvalidRequestData | InvalidSepRequest $invalid) {
            return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
        } catch (SepNotAuthorized $notAuthorized) {
            return new JsonResponse(['error' => 'Invalid request. ' . $notAuthorized->getMessage()], 401);
        } catch (Throwable $e) {
            $msg = 'Failed to put customer validation. ' . $e->getMessage();

            return new JsonResponse(['error' => $msg], 500);
        }
    }

    /**
     * @return array<array-key, mixed> the body data
     *
     * @throws InvalidRequestData if the body data could not be parsed.
     */
    private function getBodyData(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if ($contentType === 'application/x-www-form-urlencoded' || $contentType === 'multipart/form-data') {
            $parsedBody = $request->getParsedBody();
            if ($parsedBody === null) {
                return [];
            }
            if (!is_array($parsedBody)) {
                throw new InvalidRequestData('Invalid body.');
            }

            return $parsedBody;
        } elseif ($contentType === 'application/json') {
            $content = $request->getBody()->__toString();
            $jsonData = @json_decode($content, true);
            if ($jsonData === null) {
                return [];
            }
            if (!is_array($jsonData)) {
                throw new InvalidRequestData('Invalid body.');
            }

            return $jsonData;
        } else {
            throw new InvalidRequestData('Invalid request type ' . $contentType);
        }
    }

    /**
     * @throws SepNotAuthorized
     * @throws InvalidSepRequest
     * @throws AnchorFailure
     */
    private function getCustomer(Sep10Jwt $token, GetCustomerRequest $request): GetCustomerResponse
    {
        $sep12RequestBase = Sep12CustomerRequestBase::fromGetCustomerRequest($request);
        $this->validateGetOrPutRequest($sep12RequestBase, $token);
        $request->memo = $sep12RequestBase->memo;
        $request->memoType = $sep12RequestBase->memoType;
        if ($request->id === null && $request->account === null && $token->accountId !== null) {
            $request->account = $token->accountId;
        }

        return $this->customerIntegration->getCustomer($request);
    }

    /**
     * @throws SepNotAuthorized
     * @throws AnchorFailure
     * @throws InvalidSepRequest
     */
    private function putCustomer(Sep10Jwt $token, PutCustomerRequest $request): PutCustomerResponse
    {
        $sep12RequestBase = Sep12CustomerRequestBase::fromPutCustomerRequest($request);
        $this->validateGetOrPutRequest($sep12RequestBase, $token);
        $request->memo = $sep12RequestBase->memo;
        $request->memoType = $sep12RequestBase->memoType;
        if ($request->account === null && $token->accountId !== null) {
            $request->account = $token->accountId;
        }

        return $this->customerIntegration->putCustomer($request);
    }

    /**
     * @throws SepNotAuthorized
     * @throws AnchorFailure
     */
    private function deleteCustomer(
        Sep10Jwt $sep10Jwt,
        string $accountId,
        ?string $memo = null,
        ?string $memoType = null,
    ): void {
        $isAccountAuthenticated = ($sep10Jwt->accountId === $accountId || $sep10Jwt->muxedAccountId === $accountId);
        $isMemoMissingAuthentication = false;
        $muxedId = $sep10Jwt->muxedId;
        if ($muxedId !== null) {
            if ($sep10Jwt->muxedAccountId !== $accountId) {
                $isMemoMissingAuthentication = (strval($muxedId) !== $memo);
            }
        } elseif ($sep10Jwt->accountMemo !== null) {
            $isMemoMissingAuthentication = (strval($sep10Jwt->accountMemo) !== $memo);
        }

        if (!$isAccountAuthenticated || $isMemoMissingAuthentication) {
            throw new SepNotAuthorized('Not authorized to delete account.');
        }

        $getCustomerRequest = new GetCustomerRequest();
        $getCustomerRequest->account = $accountId;
        $getCustomerRequest->memo = $memo;
        $getCustomerRequest->memoType = $memoType;

        // in future (e.g. when implementing sep-31) this must be extended with a loop
        // that deletes the customer for all types. (the customer can have different ids depending on the type).
        // $getCustomerRequest->type = $type;

        $getCustomerResponse = $this->customerIntegration->getCustomer($getCustomerRequest);
        if ($getCustomerResponse->id !== null) {
            $this->customerIntegration->deleteCustomer($getCustomerResponse->id);
        }
    }

    /**
     * @throws SepNotAuthorized
     * @throws InvalidSepRequest
     */
    private function validateGetOrPutRequest(Sep12CustomerRequestBase $request, Sep10Jwt $token): void
    {
        $this->validateRequestAndTokenAccounts($request, $token);
        $this->validateRequestAndTokenMemos($request, $token);
        $this->updateRequestMemoAndMemoType($request, $token);
    }

    /**
     * @throws SepNotAuthorized
     */
    private function validateRequestAndTokenAccounts(Sep12CustomerRequestBase $request, Sep10Jwt $token): void
    {
        $tokenAccountId = $token->accountId;
        $tokenMuxedAccountId = $token->muxedAccountId;
        $customerAccountId = $request->account;

        if (
            $customerAccountId !== null
            && ($tokenAccountId !== $customerAccountId && $tokenMuxedAccountId !== $customerAccountId)
        ) {
            throw new SepNotAuthorized('The account specified does not match authorization token');
        }
    }

    /**
     * @throws SepNotAuthorized
     */
    private function validateRequestAndTokenMemos(Sep12CustomerRequestBase $request, Sep10Jwt $token): void
    {
        $tokenSubMemo = $token->accountMemo;
        $tokenMuxedAccountId = $token->muxedAccountId;
        $tokenMemo = $tokenMuxedAccountId ?? $tokenSubMemo;

        // SEP-12 says: If the JWT's `sub` field does not contain a muxed account or memo then the memo
        // request parameters may contain any value.

        if ($tokenMemo === null) {
            return;
        }

        // SEP-12 says: If a memo is present in the decoded SEP-10 JWT's `sub` value, it must match this
        // parameter value. If a muxed account is used as the JWT's `sub` value, memos sent in requests
        // must match the 64-bit integer subaccount ID of the muxed account. See the Shared Account's
        // section for more information.

        $requestMemo = $request->memo;
        if ($tokenMemo === $requestMemo) {
            return;
        }

        throw new SepNotAuthorized('The memo specified does not match the memo ID authorized via SEP-10');
    }

    /**
     * @throws InvalidSepRequest
     */
    private function updateRequestMemoAndMemoType(Sep12CustomerRequestBase $request, Sep10Jwt $token): void
    {
        $memo = $request->memo;
        if ($memo === null) {
            $request->memoType = null;

            return;
        }

        $memoType = $request->memoType;
        if ($memoType === null) {
            $memoType = MemoHelper::memoTypeAsString(XdrMemoType::MEMO_ID);
        }
        // SEP-12 says: If a memo is present in the decoded SEP-10 JWT's `sub` value, this parameter
        // (memoType) can be ignored:
        if ($token->accountMemo !== null || $token->muxedAccountId !== null) {
            $memoType = MemoHelper::memoTypeAsString(XdrMemoType::MEMO_ID);
        }
        MemoHelper::makeMemoFromSepRequestData($memo, $memoType);
        $request->memo = $memo;
        $request->memoType = $memoType;
    }
}
