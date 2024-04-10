# Anchor RFQ API - Quotes - SEP-38

This guide will walk you through integrating with the PHP Anchor SDK for the purpose of providing quotes compatible with [SEP-38](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md).

By leveraging the SDK's support for SEP-38, anchors can provide quotes that can be referenced within the context of other Stellar Ecosystem Proposals, such as SEP-24, SEP-06, SEP-31.

Before continuing with this section, make sure that you have already configured the necessary features, required by SEP-38: [SEP-1 (Stellar Info File)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md) and [SEP-10 (Stellar Authentication)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

## Configuration & callback

The SDK provides the SEP-38 implementation via the class [`Sep38Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep38/Sep38Service.php). Before usage, it must be initialized by providing the needed business logic callback class via its constructor:

```php
public function __construct(IQuotesIntegration $sep38Integration)
```

The SDK abstracts stellar specific functionality described in [SEP-38](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md), so that the anchor developer can focus on implementing the business logic. 
To be able to access the business logic, the SDK defines the callback interface [IQuotesIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IQuotesIntegration.php).
An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep38Quote/QuotesIntegration.php).

## Using the SDK Service

In this example we create a controller named `StellarQuotesController` that handles incoming requests:

```php
class StellarQuotesController extends Controller
{
    public function quotes(ServerRequestInterface $request): ResponseInterface {

        $auth = $this->getStellarAuthData($request);
        try {
            $sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
            $sep38Integration = new QuotesIntegration();
            $sep38Service = new Sep38Service($sep38Integration);
            return $sep38Service->handleRequest($request, $sep10Jwt);
        } catch (InvalidSep10JwtData $e) {
            return new JsonResponse(['error' => 'Unauthorized! Invalid token data: ' . $e->getMessage()], 401);
        }
    }
    // ...
}
```

Next, we have to link our routes:

```php
Route::get(
    'sep38/info', 
    [StellarQuotesController::class, 'quotes']
);

Route::get(
    'sep38/prices', 
    [StellarQuotesController::class, 'quotes']
);

Route::get(
    'sep38/price',
     [StellarQuotesController::class, 'quotes']
);

Route::post(
    'sep38/quote', 
    [StellarQuotesController::class, 'quotes']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep38/quote/{quote_id}',
    [StellarQuotesController::class, 'quotes']
)->middleware(StellarAuthMiddleware::class);
```

To all endpoints that require SEP-10 authentication, we need to pass the verified jwt token. We can do this by setting our `StellarAuthMiddleware` class as a middleware to the routes. This process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

By linking the routes, the incoming request is passed to our controller. Here, we need to extract the validated jwt token auth data from the request to be able to pass it to the `Sep38Service` provided by the SDK.

```php
$auth = $this->getStellarAuthData($request);
```

How to get that data from the request can be different depending on the framework that we use. For example, with `Laravel` it looks like this:

```php
private function getStellarAuthData(ServerRequestInterface $request) : ?array {
    $authDataKey = 'stellar_auth';
    $params = $request->getQueryParams();
    if (isset($params[$authDataKey])) {
        return $params[$authDataKey];
    }
    $params = $request->getParsedBody();
    if (isset($params[$authDataKey])) {
        return $params[$authDataKey];
    }
    return null;
}
```

This example implementation can be found in the anchor reference server: [StellarQuotesController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarQuotesController.php)

Now, if we have the token data, we want to build an `Sep10Jwt` object from it to be able to pass it to the SDK service:

```php
$sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
```

The [Sep10Jwt](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) class parses, validates and prepares the SEP-10 auth data from the jwt token, including user account id, memo, and other needed information. If the data is invalid for some reason, it will throw an exception.

Next, we need to prepare our business logic and provide an `QuotesIntegration` class that implements the callback interface [IQuotesIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IQuotesIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [QuotesIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep38Quote/QuotesIntegration.php).

Now that we have the callback prepared, we use it to initialize our `Sep38Service`:

```php
$sep38Service = new Sep38Service($sep38Integration);
```
The callback will be used by the Service to access the business logic.

Next we can pass the incoming request together with the jwt to the `Sep38Service`:

```php
return $sep38Service->handleRequest($request, $sep10Jwt);
```
The `Sep38Service` will parse the request and validate it, so that it can reject invalid requests. After calling the business logic via the provided callback class, the Service composes the response, so that it can be sent back to the client.

## Modifying the Stellar Info File

Wallets and other Anchors need to know that SEP-38 functionality is supported by your business. Therefore,
we must add the `ANCHOR_QUOTE_SERVER` address to the `stellar.toml` file created [earlier](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md).

```toml
# dev.stellar.toml
ACCOUNTS = ["add your public keys for your distribution accounts here"]
SIGNING_KEY = "add your signing key here"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"

TRANSFER_SERVER_SEP0024 = "https://localhost:5173/auth"
WEB_AUTH_ENDPOINT = "https://localhost:5173/sep24"
ANCHOR_QUOTE_SERVER = "https://localhost:5173/sep38"

# ...
```

An example implementation from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarTomlController.php).


## Example source code

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation.
It already contains demo business logic that can easily be extended for your own needs.
