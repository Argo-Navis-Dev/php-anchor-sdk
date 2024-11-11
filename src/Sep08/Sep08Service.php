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
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_array;
use function json_decode;
use function json_encode;
use function parse_str;
use function strlen;

class Sep08Service
{
    public IRegulatedAssetsIntegration $sep08Integration;

    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    /**
     * @param IRegulatedAssetsIntegration $sep08Integration The SEP-08 integration to be used.
     * @param LoggerInterface|null $logger The PSR-3 specific logger to be used for logging.
     */
    public function __construct(
        IRegulatedAssetsIntegration $sep08Integration,
        ?LoggerInterface $logger = null,
    ) {
        $this->sep08Integration = $sep08Integration;
        $this->logger = $logger ?? new NullLogger();
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->logger->info(
                'Handling incoming request.',
                ['context' => 'sep08', 'method' => $request->getMethod()],
            );

            if ($request->getMethod() === 'POST') {
                $contentType = $request->getHeaderLine('Content-Type');
                $this->logger->debug(
                    'The submitted request content type.',
                    ['context' => 'sep08', 'operation' => 'approval', 'content_type' => $contentType],
                );

                if ($contentType === 'application/x-www-form-urlencoded') {
                    $parsedBody = [];
                    $content = $request->getBody()->__toString();
                    if (strlen($content) !== 0) {
                        parse_str($content, $parsedBody);
                    }
                    $this->logger->debug(
                        'The submitted request content (before processing).',
                        ['context' => 'sep08', 'operation' => 'approval', 'content' => $content],
                    );

                    $approveRequest = ApprovalRequest::fromDataArray($parsedBody);
                } elseif ($contentType === 'application/json') {
                    $content = $request->getBody()->__toString();
                    $jsonData = @json_decode($content, true);
                    $this->logger->debug(
                        'The submitted request content (before processing).',
                        ['context' => 'sep08', 'operation' => 'approval', 'content' => $content],
                    );
                    if (!is_array($jsonData)) {
                        $this->logger->debug(
                            'The request body must be an array',
                            ['context' => 'sep08', 'operation' => 'approval'],
                        );

                        throw new InvalidRequestData('Invalid body.');
                    }
                    $approveRequest = ApprovalRequest::fromDataArray($jsonData);
                } else {
                    throw new InvalidRequestData('Invalid request type' . ' ' . $contentType);
                }
                $this->logger->info(
                    'Handling SEP-08 transaction approval request.',
                    ['context' => 'sep08', 'operation' => 'approval'],
                );

                return $this->handleApprovalRequest($approveRequest);
            } else {
                $error = 'Invalid request. Method not supported.';
                $response = new ApprovalRejected($error);
                $this->logger->error(
                    $error,
                    ['context' => 'sep08', 'operation' => 'approval', 'http_status_code' => 400],
                );

                return new JsonResponse($response->toJson(), 400);
            }
        } catch (InvalidRequestData $invalid) {
            $error = 'Invalid request.';
            $response = new ApprovalRejected($error . ' ' . $invalid->getMessage());
            $this->logger->error(
                $error,
                ['context' => 'sep08', 'operation' => 'approval',
                    'http_status_code' => 400, 'error' => $invalid->getMessage(), 'exception' => $invalid,
                ],
            );

            return new JsonResponse($response->toJson(), 400);
        } catch (Throwable $e) {
            $error = 'Failed to validate the SEP-08 request ' . $e->getMessage();
            $response = new ApprovalRejected($error);
            $this->logger->error(
                $error,
                ['context' => 'sep08', 'operation' => 'approval',
                    'http_status_code' => 400, 'error' => $e->getMessage(), 'exception' => $e,
                ],
            );

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
                $this->logger->error(
                    'Approval succeeded.',
                    ['context' => 'sep08', 'operation' => 'approval',
                        'response' => json_encode($response->toJson()),
                    ],
                );

                return new JsonResponse($response->toJson(), 200);
            } else {
                $this->logger->error(
                    'Approval failed.',
                    ['context' => 'sep08', 'operation' => 'approval',
                        'response' => json_encode($response->toJson()),
                    ],
                );

                return new JsonResponse($response->toJson(), 400);
            }
        } catch (AnchorFailure $e) {
            $response = new ApprovalRejected($e->getMessage());
            $this->logger->error(
                'Approval failed.',
                ['context' => 'sep08', 'operation' => 'approval',
                    'error' => $e->getMessage(), 'exception' => $e,
                ],
            );

            return new JsonResponse($response->toJson(), 400);
        }
    }
}
