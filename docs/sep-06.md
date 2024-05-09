# Deposit and Withdrawal API - SEP-06

This guide will walk you through integrating with the PHP Anchor SDK for the purpose of of build an on & off-ramp service compatible with [SEP-06](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md), the ecosystem's standardized protocol for programmatic deposit and withdrawals.

[SEP-06](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md) allows for a means by which wallets and/or exchanges interact with an anchor on behalf of users, never requiring the user to directly interact with the on & off-ramp.

By leveraging support for SEP-6, businesses make their own on & off-ramp service available as an in-app experience through Stellar-based applications such as wallets and exchanges, extending their reach and connecting with users through the applications they already use.

Before continuing with this section, make sure that you have already configured the necessary features, required by SEP-06: [SEP-1 (Stellar Info File)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md), [SEP-10 (Stellar Authentication)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md) and optionally [SEP-38 (Quotes)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-38.md).

## The Basic User Experience

The complete customer experience for a deposit or withdrawal using SEP-6 is as follows:

1. The customer opens the SEP-6 wallet application of their choice
2. The customer selects an asset to deposit and the wallet finds an anchor (clients could also choose the specific anchor)
3. Once the wallet authenticates with the anchor, the customer begins entering their KYC and transaction information requested by the anchor
4. The wallet provides instructions, and the customer deposits real fiat currency with the anchor (such as bank transfer)
5. Once the wallet receives the deposit, the customer receives the tokenized asset on the Stellar network from the anchor's distribution account

The customer can then use the digital asset on the Stellar network for remittance, payments, trading, store of value, or another use case not listed here. At some later date, the customer could decide to withdraw their assets from the Stellar network, which would look something like this:

1. The customer opens their wallet application
2. The customer selects the asset for withdrawal and wallet finds the anchor
3. After authenticating with the anchor, the customer can enter their transaction information and any additional KYC information that wasn't already collected
4. After asking for customer approval, the wallet sends the specified amount of the customer's asset balance to the anchor's distribution account on Stellar
5. Once the anchor receives the payment, the customer receives the withdrawn funds via any method supported by the anchor (such as bank transfer)

## Configuration & callback

The SDK provides the SEP-06 implementation via the class [`Sep06Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep06/Sep06Service.php). Before usage, it must be initialized by providing the needed configuration data and business logic callback class via its constructor:

```php
public function __construct(
        IAppConfig $appConfig,
        ISep06Config $sep06Config,
        ITransferIntegration $sep06Integration,
        ?IQuotesIntegration $quotesIntegration = null,
)
```

The configuration data needed is defined by the [IAppConfig](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/IAppConfig.php) and [ISep06Config](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/ISep06Config.php) interfaces.

To pass our data we can create two new classes, one implementing the `IAppConfig` interface, the other one implementing `ISep06Config`. 

For example:

`AppConfig`, which provides the data needed to access the Stellar Network. The Service needs it for example to check it the client account exists.

```php
class AppConfig implements IAppConfig
{
    public function getStellarNetwork(): Network {
        return Network::testnet();
    }

    public function getHorizonUrl(): string {
        return 'https://horizon-testnet.stellar.org';
    }
}
```

`Sep06Config` provides the data needed to define the supported features.


```php
class Sep06Config implements ISep06Config
{
    public function isAccountCreationSupported(): bool {
        return true;
    }

    public function areClaimableBalancesSupported(): bool {
        return false;
    }
}
```

Examples from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/).

The SDK abstracts stellar specific functionality described in [SEP-06](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md), so that the anchor developer can focus on implementing business logic. To be able to access the business logic, the SDK defines the callback interface [ITransferIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ITransferIntegration.php).
An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep06Transfer/TransferIntegration.php).

If the `deposit-exchange` and `withdraw-exchange` endpoints should be supported, the Anchor must also provide an implementation of the callback interface [IQuotesIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IQuotesIntegration.php) so that the SDK can access the quotes used to handle the deposit and withdraw exchanges.
An example from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep38Quote/QuotesIntegration.php). See also [SEP-38 - Quotes docs](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-38.md).

## Using the SDK Service

In this example we create a controller named `StellarTransferController` that handles incoming requests:

```php
class StellarTransferController extends Controller
{
    public function transfer(ServerRequestInterface $request): ResponseInterface {

        $auth = $this->getStellarAuthData($request);
        try {
            $sep10Jwt = $auth === null ? null : Sep10Jwt::fromArray($auth);
            $sep06Service = new Sep06Service(
                appConfig: new StellarAppConfig(),
                sep06Config: new StellarSep06Config(),
                sep06Integration: new TransferIntegration(),
                quotesIntegration: new QuotesIntegration(),
            );
            return $sep06Service->handleRequest($request, $sep10Jwt);
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
    'sep06/info', 
    [StellarTransferController::class, 'transfer']
);

Route::get(
    'sep06/deposit', 
    [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep06/withdraw',
    [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);


Route::get(
    'sep06/deposit-exchange',
    [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep06/withdraw-exchange',
     [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep06/transaction',
    [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);

Route::get(
    'sep06/transactions',
    [StellarTransferController::class, 'transfer']
)->middleware(StellarAuthMiddleware::class);
```

To all endpoints that require SEP-10 authentication, we need to pass the verified jwt token. We can do this by setting our `StellarAuthMiddleware` class as a middleware to the routes. This process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

By linking the routes, the incoming request is passed to our controller. Here, we need to extract the validated jwt token auth data from the request to be able to pass it to the `Sep06Service` provided by the SDK.

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

This example implementation can be found in the anchor reference server: [StellarTransferController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarTransferController.php)


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

Next, we need to prepare our business logic and provide an `TransferIntegration` class that implements the callback interface [ITransferIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ITransferIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [TransferIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep06Transfer/TransferIntegration.php).

Now that we have the callback prepared, we use it to initialize our `Sep06Service`:

```php
$sep06Service = new Sep06Service(
    appConfig: new StellarAppConfig(),
    sep06Config: new StellarSep06Config(),
    sep06Integration: new TransferIntegration(),
    quotesIntegration: new QuotesIntegration(),
);
```
The callback will be used by the Service to access the business logic.

Next we can pass the incoming request together with the jwt to the `Sep06Service`:

```php
return $sep06Service->handleRequest($request, $sep10Jwt);
```
The `Sep06Service` will parse the request and validate it, so that it can reject invalid requests. For example, it validates the request fields, given accounts, assets, amounts, quotes, etc. and then executes the corresponding business logic. After calling the business logic via the provided callback class, the Service composes the response, so that it can be sent back to the client.

## Modifying the Stellar Info File

Wallets need to know that SEP-06 functionality is supported by your business, and they also need to know all currencies you support. Therefore,
we must modify the `stellar.toml` file created [earlier](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md). The entry `TRANSFER_SERVER` has to be added.

```toml
# dev.stellar.toml
ACCOUNTS = ["add your public keys for your distribution accounts here"]
SIGNING_KEY = "add your signing key here"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"

TRANSFER_SERVER = "https://localhost:5173/sep06"

WEB_AUTH_ENDPOINT = "https://localhost:5173/auth"
ANCHOR_QUOTE_SERVER = "https://localhost:5173/sep38"
KYC_SERVER = "https://localhost:5173/"
TRANSFER_SERVER_SEP0024 = "https://localhost:5173/sep24"

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

//...
```

An example implementation from the reference server can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarTomlController.php).

Note that you'll need to create another file for your production deployment that uses the public network's passphrase, your production service URLs, your Mainnet distribution accounts and signing key, as well as the Mainnet issuing accounts of the assets your service utilizes.

## Example source code

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation.
It already contains example business logic that can easily be extended for your own needs.