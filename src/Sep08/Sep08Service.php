<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep08;

use ArgoNavis\PhpAnchorSdk\callback\ApprovalActionRequired;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalPending;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalRejected;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalRevised;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalSuccess;
use ArgoNavis\PhpAnchorSdk\callback\IRegulatedAssetsIntegration;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function is_array;
use function json_decode;
use function parse_str;
use function strlen;

class Sep08Service
{
    public IRegulatedAssetsIntegration $sep08Integration;

    public function __construct(IRegulatedAssetsIntegration $sep08Integration)
    {
        $this->sep08Integration = $sep08Integration;
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        try {
            if ($request->getMethod() === 'POST') {
                $contentType = $request->getHeaderLine('Content-Type');
                if ($contentType === 'application/x-www-form-urlencoded') {
                    $parsedBody = [];
                    $content = $request->getBody()->__toString();
                    if (strlen($content) !== 0) {
                        parse_str($content, $parsedBody);
                    }
                    $approveRequest = ApprovalRequest::fromDataArray($parsedBody);
                } elseif ($contentType === 'application/json') {
                    $content = $request->getBody()->__toString();
                    $jsonData = @json_decode($content, true);
                    if (!is_array($jsonData)) {
                        throw new InvalidRequestData('Invalid body.');
                    }
                    $approveRequest = ApprovalRequest::fromDataArray($jsonData);
                } else {
                    throw new InvalidRequestData('Invalid request type ' . $contentType);
                }

                return $this->handleApprovalRequest($approveRequest);
            } else {
                $response = new ApprovalRejected('Invalid request. Method not supported.');

                return new JsonResponse($response->toJson(), 400);
            }
        } catch (InvalidRequestData $invalid) {
            $response = new ApprovalRejected('Invalid request. ' . $invalid->getMessage());

            return new JsonResponse($response->toJson(), 400);
        } catch (Throwable $e) {
            $error = 'Failed to validate the SEP-08 request ' . $e->getMessage();
            $response = new ApprovalRejected($error);

            return new JsonResponse($response->toJson(), 400);
        }
    }

    private function handleApprovalRequest(ApprovalRequest $request): ResponseInterface
    {
        try {
            $response = $this->sep08Integration->approve($request->tx);
            if (
                $response instanceof ApprovalSuccess ||
                $response instanceof ApprovalRevised ||
                $response instanceof ApprovalPending ||
                $response instanceof ApprovalActionRequired
            ) {
                return new JsonResponse($response->toJson(), 200);
            } else {
                return new JsonResponse($response->toJson(), 400);
            }
        } catch (AnchorFailure $e) {
            $response = new ApprovalRejected($e->getMessage());

            return new JsonResponse($response->toJson(), 400);
        }
    }
}
