# Cross-Border Payments API

This guide will walk you through integrating with the PHP Anchor SDK for the purpose of handling payments between two financial accounts (Cross-Border Payments API) defined in  [SEP-31](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md).

By leveraging the SDK's support for SEP-31, anchors can enable payments between two financial accounts that exist outside the Stellar network.

Before continuing with this section, make sure that you have already configured the necessary features, required by SEP-31: [SEP-1 (Stellar Info File)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md) and [SEP-10 (Stellar Authentication)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

## Configuration & callback

The SDK provides the SEP-31 implementation via the class [`Sep31Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep31/Sep31Service.php). Before usage, it must be initialized by providing the needed business logic callback parameter classes via its constructor:

```php
public function __construct(ICrossBorderIntegration $sep31Integration, ?IQuotesIntegration $quotesIntegration = null) {
```

The SDK abstracts stellar specific functionality described in [SEP-31](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md), so that the anchor developer can focus on implementing the business logic.
To be able to access the business logic, the SDK defines the callback interface [ICrossBorderIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ICrossBorderIntegration.php).
An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep31CrossBorder/CrossBorderIntegration.php).

## Using the SDK Service

In this example we create a controller named `StellarCrossBorderController` that handles incoming requests:

```php
class StellarCrossBorderController extends Controller
{

    public function cross(ServerRequestInterface $request): ResponseInterface {
        $auth = $this->getStellarAuthData($request);
        if ($auth === null) {
            return new JsonResponse(['error' => 'Unauthorized! Use SEP-10 to authenticate.'], 401);
        }
        try {
            $sep10Jwt = Sep10Jwt::fromArray($auth);
            $crossBorderIntegration = new CrossBorderIntegration();
            $quotesIntegration = new QuotesIntegration();
            $sep31Service = new Sep31Service(
                sep31Integration: $crossBorderIntegration,
                quotesIntegration: $quotesIntegration,
            );

            return $sep31Service->handleRequest($request, $sep10Jwt);
        } catch (InvalidSep10JwtData $e) {
            return new JsonResponse(['error' => 'Unauthorized! Invalid token data: ' . $e->getMessage()], 401);
        }
    }
    //..
}
```

Next, we have to link our routes:

```php
Route::get(
    'sep31/info',
    [StellarCrossBorderController::class, 'cross']
)->middleware(StellarAuthMiddleware::class);

Route::post(
    'sep31/transactions',
    [StellarCrossBorderController::class, 'cross']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep31/transactions/{tx_id}',
    [StellarCrossBorderController::class, 'cross']
)->middleware(StellarAuthMiddleware::class);

Route::put(
    'sep31/transactions/{tx_id}/callback',
    [StellarCrossBorderController::class, 'cross']
)->middleware(StellarAuthMiddleware::class);

```

All endpoints requires SEP-10 authentication, therefore we need to pass the verified jwt token. We can do this by setting our `StellarAuthMiddleware` class as a middleware to the routes. This process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

By linking the routes, the incoming request is passed to our controller. Here, we need to extract the validated jwt token auth data from the request to be able to pass it to the `Sep31Service` provided by the SDK.

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

This example implementation can be found in the anchor reference server: [StellarCrossBorderController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarCrossBorderController.php)

Now, if we have the token data, we want to build an `Sep10Jwt` object from it to be able to pass it to the SDK service:

```php
$sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
```

The [Sep10Jwt](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) class parses, validates and prepares the SEP-10 auth data from the jwt token, including user account id, memo, and other needed information. If the data is invalid for some reason, it will throw an exception.

Next, we need to prepare our business logic and provide a `CrossBorderIntegration` class that implements the callback interface [ICrossBorderIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ICrossBorderIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [CrossBorderIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep31CrossBorder/CrossBorderIntegration.php).

Optionally, if the Anchor supports quotes ([SEP-38](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md)) we need to prepare our business logic and provide a `QuotesIntegration` class that implements the callback interface [IQuotesIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IQuotesIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [QuotesIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep38Quote/QuotesIntegration.php).

Now that we have both callbacks prepared, we use it to initialize our `Sep31Service`:

```php
$sep31Service = new Sep31Service($crossBorderIntegration, $quotesIntegration);
```
The callbacks will be used by the Service to access the business logics. Note that passing the `$quotesIntegration` parameter is optional.

Next we can pass the incoming request together with the jwt to the `Sep31Service`:

```php
return $sep31Service->handleRequest($request, $sep10Jwt);
```
The `Sep31Service` will parse the request and validate it, so that it can reject invalid requests. After calling the business logic via the provided callback class, the Service composes the response, so that it can be sent back to the client.

## Modifying the Stellar Info File

The sending Anchor needs to know that SEP-31 functionality is supported by the receiving Anchor. Therefore,
we must add the `DIRECT_PAYMENT_SERVER` address to the `stellar.toml` file created [earlier](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md).

```toml
# dev.stellar.toml
ACCOUNTS = ["add your public keys for your distribution accounts here"]
SIGNING_KEY = "add your signing key here"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"

DIRECT_PAYMENT_SERVER = "https://localhost:5173/sep31"

# ...
```

An example implementation from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarTomlController.php).


## Example source code

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation. It already contains demo business logic that can easily be extended for your own needs.
