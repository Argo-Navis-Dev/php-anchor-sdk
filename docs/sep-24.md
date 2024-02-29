# Hosted Deposits and Withdrawals - Interactive Flow - SEP-24

This guide will walk you through configuring and integrating with the PHP Anchor SDK for the purpose of building an on & off-ramp service compatible with [SEP-24](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md), the ecosystem's standardized protocol for hosted deposits and withdrawals.

By leveraging the SDK's support for SEP-24, businesses make their on & off-ramp service available as an in-app experience through Stellar-based applications such as wallets and exchanges, extending their reach and connecting with users through the applications they already use.

Before continuing with this section, make sure that you have already configured the necessary features, required by SEP-24: [SEP-1 (Stellar Info File)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md) and [SEP-10 (Stellar Authentication)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

## The Basic User Experience

The complete customer experience a deposit and withdrawal goes something like this:

1. The customer opens the SEP-24 wallet application of their choice
2. The customer selects an asset to deposit and the wallet finds an anchor (clients could also chose the specific anchor)
3. Once the wallet authenticates with the anchor, the customer begins entering their KYC and transaction information requested by the anchor
4. The wallet provides instructions, and the customer deposits real fiat currency with the anchor (e.g. makes a bank transfer)
5. Once the wallet receives the deposit, the customer receives the tokenized asset on the Stellar network from the anchor's distribution account

The customer can then use the digital asset on the Stellar network for remittance, payments, trading, store of value, or another use case not listed here. At some later date, the customer could decide to withdraw their assets from the Stellar network, which would look something like this:

1. The customer opens their wallet application
2. The customer selects the asset for withdrawal and wallet finds the anchor
3. After authenticating with the anchor, the wallet opens the given interactive URL and allows the customer to enter their transaction information (KYC has already been collected)
4. After asking for customer approval, the wallet sends the specified amount of the customer's asset balance to the anchor's distribution account on Stellar
5. Once the anchor receives the payment, the customer receives the withdrawn funds via bank transfer.


## Configuration & callback

The SDK provides the SEP-24 implementation via the class [`Sep24Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep24/Sep24Service.php). Before usage, it must be initialized by providing the needed configuration data and business logic callback class via its constructor:

```php
public function __construct(
    ISep24Config $sep24Config,
    IInteractiveFlowIntegration $sep24Integration,
)
```

The configuration data needed is defined by the [ISep24Config](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/ISep24Config.php) interface. To pass our data we can create a new class implementing the `ISep24Config` interface. An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/StellarSep24Config.php).
 

The SDK abstracts stellar specific functionality described in [SEP-24](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md), so that the anchor developer can focus on implementing the business logic. To be able to access the business logic, the SDK defines the callback interface [IInteractiveFlowIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IInteractiveFlowIntegration.php).
An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep24Interactive/InteractiveFlowIntegration.php).

## Using the SDK Service

In this example we create a controller named `StellarInteractiveFlowController` that handles incoming Stellar KYC requests:

```php
class StellarInteractiveFlowController extends Controller
{
    public function interactive(ServerRequestInterface $request): ResponseInterface {

        $auth = $this->getStellarAuthData($request);
        try {
            $sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
            $sep24Config = new StellarSep24Config();
            $sep24Integration = new InteractiveFlowIntegration();
            $sep24Service = new Sep24Service($sep24Config, $sep24Integration);
            return $sep24Service->handleRequest($request, $sep10Jwt);
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
    'sep24/info', 
    [StellarInteractiveFlowController::class, 'interactive']
);

Route::get(
    'sep24/fee', 
    [StellarInteractiveFlowController::class, 'interactive']
);

Route::post(
    'sep24/transactions/deposit/interactive', 
    [StellarInteractiveFlowController::class, 'interactive']
)->middleware(StellarAuthMiddleware::class);

Route::post(
    'sep24/transactions/withdraw/interactive', 
    [StellarInteractiveFlowController::class, 'interactive']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep24/transaction', 
    [StellarInteractiveFlowController::class, 'interactive']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep24/transactions', 
    [StellarInteractiveFlowController::class, 'interactive']
)->middleware(StellarAuthMiddleware::class);
```

To all endpoints that require SEP-10 authentication, we need to pass the verified jwt token. We can do this by setting our `StellarAuthMiddleware` class as a middleware to the routes. This process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

By linking the routes, the incoming request is passed to our controller. Here, we need to extract the validated jwt token auth data from the request to be able to pass it to the `Sep24Service` provided by the SDK.

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

This example implementation can be found in the anchor reference server: [StellarInteractiveFlowController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarInteractiveFlowController.php)

Now, if we have the token data, we want to build an `Sep10Jwt` object from it to be able to pass it to the SDK service:

```php
$sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
```

The [Sep10Jwt](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) class parses, validates and prepares the SEP-10 auth data from the jwt token, including user account id, memo, and other needed information. If the data is invalid for some reason, it will throw an exception.

Next, we need to prepare our business logic and provide an `InteractiveFlowIntegration` class that implements the callback interface [IInteractiveFlowIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IInteractiveFlowIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [InteractiveFlowIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep24Interactive/InteractiveFlowIntegration.php).

Now that we have the callback prepared, we use it to initialize our `Sep24Service`:

```php
$sep24Service = new Sep24Service($sep24Config, $sep24Integration);
```
The callback will be used by the Service to access the business logic.

Next we can pass the incoming request together with the jwt to the `Sep12Service`:

```php
return $sep24Service->handleRequest($request, $sep10Jwt);
```
The `Sep24Service` will parse the request and validate it, so that it can reject invalid requests. For example, it handles all defined content types, validates if the request data matches the jwt data, extracts uploaded files and then executes the corresponding business logic. After calling the business logic via the provided callback class, the Service composes the response, so that it can be sent back to the client.

## Modifing the Stellar Info File

Wallets need to know that SEP-24 functionality is supported by your business, and they also need to know all currencies you support. Therefore 
we must modify the `stellar.toml` file created [earlier](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md).

```toml
# dev.stellar.toml
ACCOUNTS = ["add your public keys for your distribution accounts here"]
SIGNING_KEY = "add your signing key here"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"

TRANSFER_SERVER_SEP0024 = "https://localhost:5173/auth"
WEB_AUTH_ENDPOINT = "https://localhost:5173/sep24"

# Add support for USDC
[[CURRENCIES]]
code = "USDC"
issuer = "GBBD47IF6LWK7P7MDEVSCWR7DPUWV3NY3DTQEVFL4NAT4AQH3ZLLFLA5"
status = "test"
is_asset_anchored = false
desc = "USD Coin issued by Circle"

# Optionally, add support for XLM
[[CURRENCIES]]
code = "native"
status = "test"
is_asset_anchored = false
anchor_asset_type = "crypto"
desc = "XLM, the native token of the Stellar network."

[DOCUMENTATION]
ORG_NAME = "Your organization"
ORG_URL = "Your website"
```

An example implementation from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarTomlController.php).

Note that you'll need to create another file for your production deployment that uses the public network's passphrase, your production service URLs, your Mainnet distribution accounts and signing key, as well as the Mainnet issuing accounts of the assets your service utilizes.

## Example source code

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation.
It already contains demo business logic that can easily be extended for your own needs.
