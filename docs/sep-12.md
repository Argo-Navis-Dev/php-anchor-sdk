# KYC API - SEP-12

A wallet or other client can use the KYC API ([SEP-12](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md)) to upload KYC (or other) information to anchors and other services. SEP-24, SEP-6 and SEP-31 use this protocol, but it can serve as a stand-alone service as well.

To use this service, a wallet must first create an authenticated session with the Stellar anchor by proving they, or their users, have sufficient control over a Stellar account. Once authenticated, the wallet application uses a session token provided by the anchor to make requests to the KYC API endpoints. The authentication process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md)


The PHP Anchor SDK supports the Stellar KYC API standard with minimal configuration from the business.

## Configuration & Callback

The SDK provides the KYC API implementation via the class [`Sep12Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep12/Sep12Service.php). Before usage, it must be initialized by providing the needed business logic callback class and optional configuration data via its constructor:

```php
public function __construct(ICustomerIntegration $customerIntegration, ?ISep12Config $config = null)
```

The configuration data is optional and specifies the maximum size of a file that can be uploaded by the client (such as photo of the id document) and the maximal number of files that can be uploaded in one request. If the configuration is not provided, the SDK uses default values (max 2MB file size and max 6 files per request).

To pass our data we can create a new class implementing the [`ISep12Config`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/ISep12Config.php).

The SDK abstracts the stellar specific functionality described in [SEP-12](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md) so that the anchor developer can focus on implementing the business logic. To be able to access the business logic, the SDK defines the callback interface [ICustomerIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ICustomerIntegration.php).

## Using the SDK Service

In this example we create a controller named `StellarCustomerController` that handles incoming Stellar KYC requests:

```php
class StellarCustomerController extends Controller
{
    public function customer(ServerRequestInterface $request): ResponseInterface {

        $auth = $this->getStellarAuthData($request);
        if ($auth === null) {
            return new JsonResponse(['error' => 'Unauthorized! Use SEP-10 to authenticate.'], 401);
        }
        try {
            $sep10Jwt = Sep10Jwt::fromArray($auth);
            $customerIntegration = new CustomerIntegration();
            $sep12Service = new Sep12Service($customerIntegration);
            return $sep12Service->handleRequest($request, $sep10Jwt);
        } catch (InvalidSep10JwtData $e) {
            return new JsonResponse(['error' => 'Unauthorized! Invalid token data: ' . $e->getMessage()], 401);
        }
    }

    // ...
}
```

Next, we have to link our routes:

```php
Route::get('customer', [StellarCustomerController::class, 'customer'])->middleware(StellarAuthMiddleware::class);
Route::put('customer', [StellarCustomerController::class, 'customer'])->middleware(StellarAuthMiddleware::class);
Route::put('customer/verification', [StellarCustomerController::class, 'customer'])->middleware(StellarAuthMiddleware::class);
Route::put('customer/callback', [StellarCustomerController::class, 'customer'])->middleware(StellarAuthMiddleware::class);
Route::delete('customer/{account_id}', [StellarCustomerController::class, 'customer'])->middleware(StellarAuthMiddleware::class);
```

Because all endpoints need SEP-10 authentication, we need to pass the verified jwt token. We can do this by setting our `StellarAuthMiddleware` class as a middleware to the routes. This process is described [here](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).

By linking the routes, the incoming request is passed to our controller. Here, we need to extract the validated jwt token auth data from the request to be able to pass it to the `Sep12Service` provided by the SDK.

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

This example implementation can be found in the anchor reference server: [StellarCustomerController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarCustomerController.php)

Now, that we have the token data we want to build an `Sep10Jwt` object from it to be able to pass it to the SDK service:

```php
$sep10Jwt = Sep10Jwt::fromArray($auth);
```

The [Sep10Jwt](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) class parses, validates and prepares the SEP-10 auth data from the jwt token, including user account id, memo, and other needed information. If the data is invalid for some reason, it will throw an exception.

Next, we need to prepare our business logic and provide a `CustomerIntegration` class that implements the callback interface [ICustomerIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/ICustomerIntegration.php) defined by the SDK.

An example implementation can be found in the anchor reference server: [CustomerIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep12Customer/CustomerIntegration.php).

Now that we have the callback prepared, we use it to initialize our `Sep12Service`:

```php
$sep12Service = new Sep12Service($customerIntegration);
```
The callback will be used by the Service to access the business logic.

Next we can pass the incoming request together with the jwt to the `Sep12Service`:

```php
return $sep12Service->handleRequest($request, $sep10Jwt);
```

The `Sep12Service` will parse the request and validate it, so that it can reject invalid requests. For example, it handles all defined content types, validates if the request data matches the jwt data, extracts uploaded files and then executes the corresponding business logic. After calling the business logic via the provided callback class, the Service composes the response, so that it can be sent back to the client.

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation.