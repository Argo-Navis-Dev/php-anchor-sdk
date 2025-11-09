# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

The PHP Stellar Anchor SDK is a service layer library that helps PHP developers implement Stellar Anchors by abstracting Stellar-specific functionality defined in SEPs (Stellar Ecosystem Proposals). This allows developers to focus on business logic rather than protocol implementation details.

**Key Architecture Concept**: The SDK acts as a middleware between:
- **Client Layer**: Wallets or applications making SEP-compliant requests
- **Service Layer** (this SDK): Handles Stellar-specific protocol logic
- **Business Logic Layer**: Must be implemented by anchor developers through callback interfaces

## Development Commands

### Testing
- Run all tests: `composer argo:test:all`
- Run unit tests only: `composer argo:test:unit` or `vendor/bin/phpunit`
- Run single test file: `vendor/bin/phpunit tests/Sep10Test.php`
- Generate HTML coverage report: `composer argo:test:coverage:html`
- Coverage for CI: `composer argo:test:coverage:ci`

### Code Quality
- Run all linting: `composer argo:lint:all`
- Fix coding standards: `composer argo:lint:fix`
- Check coding standards: `composer argo:lint:style`
- Check syntax errors: `composer argo:lint:syntax`
- Static analysis (all): `composer argo:analyze:all`
- PHPStan only: `composer argo:analyze:phpstan`
- Psalm only: `composer argo:analyze:psalm`

### Other
- Clean build directory: `composer argo:build:clean`
- Check dependency licenses: `composer argo:license`

## Architecture & Code Organization

### SEP Service Pattern

Each SEP (Stellar Ecosystem Proposal) implementation follows a consistent pattern:

1. **Service Class** (`src/SepXX/SepXXService.php`): Main entry point that handles requests
   - Takes PSR-7 `ServerRequestInterface`
   - Returns PSR-7 `ResponseInterface`
   - Coordinates between request parsing, validation, business logic callbacks, and response formatting

2. **Request Parser** (`src/SepXX/SepXXRequestParser.php`): Parses and validates incoming requests

3. **Business Logic Interface** (`src/callback/IXxxIntegration.php`): Defines callbacks that anchor developers must implement
   - `ICustomerIntegration`: SEP-12 KYC operations
   - `ITransferIntegration`: SEP-06 deposit/withdrawal operations
   - `IInteractiveFlowIntegration`: SEP-24 interactive flow operations
   - `ICrossBorderIntegration`: SEP-31 cross-border payments
   - `IQuotesIntegration`: SEP-38 quote/price operations
   - `IRegulatedAssetsIntegration`: SEP-08 regulated assets approval

### Core Components

- **`src/Sep10/`**: Authentication (SEP-10) - JWT-based authentication with Stellar accounts
- **`src/Sep01/`**: TOML provider (SEP-01) - Stellar info file serving
- **`src/Sep12/`**: KYC API (SEP-12) - Customer information upload/verification
- **`src/Sep06/`**: Deposit/Withdrawal (SEP-06) - Programmatic asset transfer
- **`src/Sep24/`**: Interactive flow (SEP-24) - Web-based deposit/withdrawal
- **`src/Sep31/`**: Cross-border payments (SEP-31) - Payment receiving
- **`src/Sep38/`**: RFQ/Quotes (SEP-38) - Price quotes and anchored asset exchange
- **`src/Sep08/`**: Regulated assets (SEP-08) - Transaction approval flow

### Shared Infrastructure

- **`src/config/`**: Configuration interfaces (`IAppConfig`, `ISepXXConfig`)
  - `IAppConfig`: Required for Stellar network, Horizon URL, and localization
  - SEP-specific config interfaces: Optional configuration per SEP service

- **`src/callback/`**: Business logic integration interfaces and request/response DTOs

- **`src/shared/`**: Common data structures used across SEPs
  - Asset info classes (Sep06AssetInfo, Sep24AssetInfo, etc.)
  - Transaction status enums
  - Customer field definitions
  - Quote and price structures

- **`src/exception/`**: Custom exceptions with localization support via `LocalizedExceptionHelper`

- **`src/Stellar/`**: Stellar blockchain interaction helpers
  - `PaymentsHelper`: Query and parse received payments
  - `TrustlinesHelper`: Check account trustlines
  - `CallbackHelper`: Send signed callback notifications to clients

- **`src/logging/`**: PSR-3 logger support with `NullLogger` default

### Service Initialization Pattern

All SEP services follow this initialization pattern:

```php
$service = new SepXXService(
    businessLogicIntegration: $integration,  // Implements IXxxIntegration
    appConfig: $appConfig,                   // Implements IAppConfig
    config: $sepConfig,                      // Optional: Implements ISepXXConfig
    logger: $logger                          // Optional: PSR-3 LoggerInterface
);

$response = $service->handleRequest($request, $sep10Jwt);
```

## Key Integration Points

### Authentication Flow (SEP-10)
1. Client requests challenge: `GET /auth?account=G...`
2. Server returns signed challenge transaction
3. Client signs and submits: `POST /auth` with signed transaction
4. Server validates and returns JWT token
5. JWT used for authenticating subsequent SEP requests via `Sep10Jwt::fromArray($authData)`

### Localization
Implement `IAppConfig::getLocalizedText()` to provide translations. SDK passes keys with default English text. Return translations based on locale or fall back to default.

### Logging
Pass any PSR-3 compatible logger to service constructors. Use `null` or omit for no logging. SDK logs at various levels (info, warning, error) for debugging and monitoring.

### Callback Notifications
Use `CallbackHelper::sendCallbackRequest()` to send signed webhook notifications to clients about transaction/customer status changes. Used by SEP-06, SEP-24, SEP-31 for async updates.

## Important Development Notes

- **PHP Version**: Requires PHP 8.0+
- **PSR Standards**: Uses PSR-7 (HTTP messages), PSR-3 (logging)
- **Stellar SDK**: Built on `soneso/stellar-php-sdk` for blockchain operations
- **JWT Handling**: Uses `firebase/php-jwt` for SEP-10 authentication tokens
- **Namespace**: All classes under `ArgoNavis\PhpAnchorSdk\`

## Testing Approach

Tests are located in `tests/` directory with pattern `SepXXTest.php`. Each test typically:
1. Sets up mock business logic integration
2. Creates service instance
3. Constructs PSR-7 request objects
4. Invokes `handleRequest()`
5. Validates response structure and content

## Reference Implementation

The [Anchor Reference Server](https://github.com/Argo-Navis-Dev/anchor-reference-server) demonstrates:
- How to integrate each SEP service
- Example business logic implementations
- Middleware for authentication
- Route configuration patterns
- Framework integration (Laravel)
