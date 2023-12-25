# Stellar Web Authentication - SEP-10

A wallet may want to authenticate with any web service which requires a Stellar account ownership verification, for example, to upload KYC information to an anchor in an authenticated way as described in [SEP-12](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md).

Stellar-based wallet applications create authenticated sessions with Stellar anchors by proving they, or their users, have sufficient control over a Stellar account. Once authenticated, the wallet application uses a session token provided by the anchor in subsequent requests to the anchor's standardized services.

The PHP Anchor SDK supports this form of authentication with minimal configuration from the business.

## Configuration

The SDK provides the Stellar Web Authentication via the class [`Sep10Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Service.php). Before usage, it must be initialized 
by providing the needed configuration data via its constructor:

```php
public function __construct(IAppConfig $appConfig, ISep10Config $sep10Config)
```

To pass the data we can create two new classes, one implementing the [`IAppConfig`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/IAppConfig.php) 
and the other one implementing [`ISep10Config`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/ISep10Config.php).

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

`Sep10Config` provides the data needed to construct and validate the authentication challenge. This data is also used by the Service to compose and sign the jwt token.

```php
class Sep10Config implements ISep10Config
{
    public function getWebAuthDomain(): ?string {
        return 'localhost:8000';
    }

    public function getHomeDomains(): array {
        return ['localhost:8000'];
    }

    public function getAuthTimeout(): int {
        return 300;
    }

    public function getJwtTimeout(): int {
        return 300;
    }

    public function getSep10SigningSeed(): string {
        return 'SDAZWKWRYCNGU6JIZGBY7G45QN4OJSH7VAJPNIJPO4NOR56IJF35WKFL';
    }

    public function getSep10JWTSigningKey(): string {
        return 'SAYBCVQHMBRQIOCKMCCGBSJZ5X6OCAX7576MVGPHEA6W76ZV2QDUXRJ2';
    }
    
    public function isClientAttributionRequired(): bool {
        return false;
    }
    
    public function getAllowedClientDomains(): ?array {
        return null;
    }

    public function getKnownCustodialAccountList(): ?array {
        return null;
    }

}
```
More info about the values can be found the [`IAppConfig`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/IAppConfig.php)
and the [`ISep10Config`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/config/ISep10Config.php) interface documentation.

The values can be loaded from a database, config files or other sources. See also the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementations
[`StellarAppConfig`](https://github.com/Argo-Navis-Dev/anchor-reference-server/tree/main/app/Stellar/StellarAppConfig.php) 
and [`StellarSep10Config`](https://github.com/Argo-Navis-Dev/anchor-reference-server/tree/main/app/Stellar/StellarSep10Config.php) as an example.

## Using the SDK Service

In this example we create controller named `StellarAuthController` that handles incoming Stellar web authentication requests:

```php
class StellarAuthController extends Controller
{
    public function auth(ServerRequestInterface $request): ResponseInterface {
        try {
            $appConfig = new StellarAppConfig();
            $sep10Config = new StellarSep10Config();
            $sep10Service = new Sep10Service($appConfig, $sep10Config);

            return $sep10Service->handleRequest($request, httpClient: new Client());
        } catch (InvalidConfig $invalid) {
            return new JsonResponse(['error' => 'Internal server error: Invalid config. ' .
                $invalid->getMessage()], 500);
        }
    }
}
```

Next, we have to link it to the `get` and `post` routes:

```php
Route::get('auth', [StellarAuthController::class, 'auth']);
Route::post('auth', [StellarAuthController::class, 'auth']);
```

The `get` request will be handled as a challenge request and the service will return a response containing the transaction to be signed by the client. 

Request:
```sh
get http://localhost:8000/auth?account=GAPRQWSYQAH6PGFAWWPTSWUEXHJOYFFPAOIUX2ISEJGGUGJDQR24IFGR
```

Response (body): 
```json
{
  "transaction": "AAAAAgAAAACBPZt6XCuq7seLFXWUEGuFC86PpC7rymuS5CWisH6bGAAAAMgAAAAAAAAAAAAAAAEAAAAAZYoGNAAAAABligdgAAAAAAAAAAIAAAABAAAAAB8YWliAD+eYoLWfOVqEudLsFK8DkUvpEiJMahkjhHXEAAAACgAAABNsb2NhbGhvc3Q6ODAwMCBhdXRoAAAAAAEAAABAT2YwRjZUR1JiK0ordEtubHZCbWZIL3F5L0NBNjFCamswZE1jbUowd3A0enVwZGsvQUdGQUt6eVZPSDNDUzN1cwAAAAEAAAAAgT2belwrqu7HixV1lBBrhQvOj6Qu68prkuQlorB+mxgAAAAKAAAAD3dlYl9hdXRoX2RvbWFpbgAAAAABAAAACWxvY2FsaG9zdAAAAAAAAAAAAAABsH6bGAAAAEBOzY+b+1Osa4hVkKm/QvINuSKnr9CM6bQsd32Q1ccN4xeAfgVrSVbxXoyCQoadx35KaiRauvIyImXYW96UH0QP",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

Next, the client will sign the transaction and send it back to the server by using the `post` method of our endpoint.
Hint: for testing purposes one can sign the transaction using [Stellar Laboratory](https://laboratory.stellar.org/).

Request:
```shell
post http://localhost:8000/auth
```
Content (signed transaction):

```json
{
  "transaction": "AAAAAgAAAACBPZt6XCuq7seLFXWUEGuFC86PpC7rymuS5CWisH6bGAAAAMgAAAAAAAAAAAAAAAEAAAAAZYoGNAAAAABligdgAAAAAAAAAAIAAAABAAAAAB8YWliAD+eYoLWfOVqEudLsFK8DkUvpEiJMahkjhHXEAAAACgAAABNsb2NhbGhvc3Q6ODAwMCBhdXRoAAAAAAEAAABAT2YwRjZUR1JiK0ordEtubHZCbWZIL3F5L0NBNjFCamswZE1jbUowd3A0enVwZGsvQUdGQUt6eVZPSDNDUzN1cwAAAAEAAAAAgT2belwrqu7HixV1lBBrhQvOj6Qu68prkuQlorB+mxgAAAAKAAAAD3dlYl9hdXRoX2RvbWFpbgAAAAABAAAACWxvY2FsaG9zdAAAAAAAAAAAAAACsH6bGAAAAEBOzY+b+1Osa4hVkKm/QvINuSKnr9CM6bQsd32Q1ccN4xeAfgVrSVbxXoyCQoadx35KaiRauvIyImXYW96UH0QPI4R1xAAAAEDwoPGokIyHGsjt/1aFjKimn06FBgYtPbmn5moO8gfHKcWDAwS5vLs5RQQrnDpFBxN68/RCaeNLN7lMws81t0wA"
}
```

The Service will handle the `post` request as a validation request and will return the jwt token to the caller if the authentication was successful (transaction has been correctly signed by the client).

Response:

The response body should contain the jwt token generated by the service:
```json
{
  "jwt": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJqdGkiOiIzNDc4NzVlY2FlMDRkYTUyMDMxMGI5MTFkZThjMjExMjU0ZDM0OWQ4YmRlNGVmZTNhMjRkMTM3NjQ1ZGNjOTM3IiwiaXNzIjoiaHR0cDovL2xvY2FsaG9zdDo4MDAwL2F1dGgiLCJzdWIiOiJHQVBSUVdTWVFBSDZQR0ZBV1dQVFNXVUVYSEpPWUZGUEFPSVVYMklTRUpHR1VHSkRRUjI0SUZHUiIsImlhdCI6IjE3MDM1NDQzNzIiLCJleHAiOiIxNzAzNTQ3MzcyIiwiaG9tZV9kb21haW4iOiJsb2NhbGhvc3Q6ODAwMCJ9.lX77F6ADmVhsVPQ6QczTiRhlFHEBh6M7brre13uAzEI"
}
```
Next, let's see how to check if the client is authenticated if it tries to access another endpoint that requires SEP-10 authentication.

For this we first create a middleware class called `StellarAuthMiddleware`:

```php
class StellarAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = request()->bearerToken();
            if ($token === null) {
                throw new Exception("missing jwt token");
            }
            $sep10Config = new StellarSep10Config();
            $jwtSigningKey = $sep10Config->getSep10JWTSigningKey();
            $sep10Jwt = Sep10Jwt::validateSep10Jwt($token, $jwtSigningKey);
            $request->merge(['stellar_auth' => $sep10Jwt->toArray()]);

            return $next($request);
        } catch (Exception $e) {
            return new Response('Unauthorized: ' . $e->getMessage(), 403);
        }
    }
}
```
Here we extract the jwt token from that authentication header (bearer). Next we use the SDK's class [`Sep10Jwt`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) ::`validateSep10Jwt` method, that can validate the jwt token and returns a `Sep10Jwt` object if valid. 
If not valid, `validateSep10Jwt` will throw an exception. It can throw different kinds of exceptions such as `ExpiredException` if the jwt token is expired. For more info, take a look to the [`Sep10Jwt`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep10/Sep10Jwt.php) class documentation.

After successful validation of the jwt token, we add the data from the token (such as the account id of the client) to our request by using the key `stellar_auth`, so that it can be accessed later by other handlers within our chain. We also pass the request to the next handler.

If the validation was not successful, we simply return an `Unathorized` response.

Now let's add our middleware to a new test route:

```php
Route::get('/test_stellar_auth', function (Request $request) {
    return $request->input('stellar_auth');
})->middleware(StellarAuthMiddleware::class);
```

The body can only be accessed if the request successfully passed our middleware. If so, let's return the contents of the stored `stellar_auth` data. The output should look similar to this:
```json
{
  "jti": "9049044b84f7052c7d2e12c0bf7b6927a5c6f3efd098a4e3a537121b96415f63",
  "iss": "http://localhost:8000/auth",
  "sub": "GAPRQWSYQAH6PGFAWWPTSWUEXHJOYFFPAOIUX2ISEJGGUGJDQR24IFGR",
  "iat": "1703542645",
  "exp": "1703545645",
  "account_id": "GAPRQWSYQAH6PGFAWWPTSWUEXHJOYFFPAOIUX2ISEJGGUGJDQR24IFGR",
  "home_domain": "localhost:8000"
}
```

This data can be used for further processing such as for the SEP-12 implementation to accept and store uploaded KYC information.

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation.