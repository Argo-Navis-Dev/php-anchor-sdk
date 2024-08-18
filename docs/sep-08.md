# Regulated Assets API

This guide will walk you through integrating with the PHP Anchor SDK for the purpose of handling Regulated Assets defined in [SEP-08](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md).

Before continuing with this section, make sure that you have already configured [SEP-1 (Stellar Info File)](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md). It is required by SEP-08.

## Configuration & callback

The SDK provides the SEP-08 implementation via the class [`Sep08Service`](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Sep08/Sep08Service.php). Before usage, it must be initialized by passing the needed business logic callback object via its constructor:

```php
public function __construct(
    IRegulatedAssetsIntegration $sep08Integration,
)
```

The SDK abstracts stellar specific functionality described in [SEP-08](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md), so that the anchor developer can focus on implementing the business logic.
To be able to access the business logic, the SDK defines the callback interface [IRegulatedAssetsIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IRegulatedAssetsIntegration.php).

## Using the SDK Service

In this example we create a controller named `StellarRegulatedAssetsController` that handles incoming requests:

```php
class StellarRegulatedAssetsController extends Controller
{
    /**
     * This is the core SEP-8 endpoint used to validate and process regulated assets transactions.
     * @param ServerRequestInterface $request request from the client.
     * @return ResponseInterface response to the client
     */
    public function approve(ServerRequestInterface $request): ResponseInterface {
        $sep08Integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($sep08Integration);
        return $sep08Service->handleRequest($request);
    }
    //..
}
```

Next, we have to link our route:

```php
Route::post(
    'sep08/tx-approve', 
    [StellarRegulatedAssetsController::class, 'approve'],
);
```

The business logic is provided by the `RegulatedAssetsIntegration` class that implements the callback interface [IRegulatedAssetsIntegration](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/callback/IRegulatedAssetsIntegration.php) defined by the SDK.

An example implementation of the controller and business logic can be found in the anchor reference server: [StellarRegulatedAssetsController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarRegulatedAssetsController.php) 
and [RegulatedAssetsIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep08RegulatedAssets/RegulatedAssetsIntegration.php).

Now that we have our business logic callback prepared, we use it to initialize our `Sep08Service`:

```php
$sep08Service = new Sep08Service($sep08Integration);
```

Next we can pass the incoming request to the `Sep08Service`:

```php
return $sep08Service->handleRequest($request);
```
The `Sep08Service` will parse the request and validate it, so that it can reject invalid requests. After executing the business logic, the Service composes the response, so that it can be sent back to sending Anchor.

## Modifying the Stellar Info File

The client needs to know that `SEP-08` functionality is supported by the Anchor. Therefore,
we must add the currency data for each supported regulated asset to the `stellar.toml` file created [earlier](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md). Here is an example:

```toml
# dev.stellar.toml
ACCOUNTS = ["add your public keys for your distribution accounts here"]
SIGNING_KEY = "add your signing key here"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"


# ...

[[CURRENCIES]]
code = "STAR"
issuer = "GB6CPVUXGWPE33XY7CHUKKL5VOGIR6ADBVQQYZSWJ3Y2CQDUJKICSTAR"
name = "Stars for best friends"
desc = "Send stars to php anchor sdk friends!"
regulated = true
approval_server = "https://localhost:5173/sep08/tx-approve"
approval_criteria = "Only one payment operation per transaction allowed. Payment greater then 5 Stars requires your email address."
```

## Reference implementation

Our [Anchor Reference Server](https://github.com/Argo-Navis-Dev/anchor-reference-server) offers a
`SEP-08 Approval Server` reference implementation using the `php anchor sdk` and the core `php stellar sdk`. It intended for demo and testing. 

It is being conceived to:

1. Be used as an example of how to use the `php anchor sdk` and the core `php stellar sdk` to implement `SEP-08` support as an anchor.
2. Be used as an example of how regulated assets transactions can be validated and revised by an anchor.
3. Be used as an example of how an anchor's `SEP-08` implementation can be tested.

### Account Setup

In order to properly use the server, the issuer account of the offered demo regulated asset (see `SEP08_ISSUER_ID` in [.env.example](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/.env.example)), needs to be configured according with the `SEP-08` [authorization flags](https://github.com/stellar/stellar-protocol/blob/7c795bb9abc606cd1e34764c4ba07900d58fe26e/ecosystem/sep-0008.md#authorization-flags0) by setting both 
`Authorization Required` and `Authorization Revocable` flags. This allows the issuer to grant and revoke authorization to transact the asset at will.
One can use the [Stellar Lab](https://laboratory.stellar.org/#?network=test) to fund the issuer account on testnet and set the needed flags.

#### GET /friendbot?addr={stellar_address}

This endpoint sends a payment of 100 regulated assets to the provided `addr`. Please be aware the address must first establish a trustline to the regulated asset in order to receive that payment. You can do that in the [Stellar Lab](https://laboratory.stellar.org/#?network=test) by using the `change trust` operation.

### API Spec

#### POST /tx-approve
This is the core SEP-8 endpoint used to validate and process regulated assets transactions. 
Its response will contain one of the following statuses: 


- [Success](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md#success): means the transaction has been approved and signed by the issuer without being revised.
- [Revised](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md#revised): this response means the transaction was revised to be made compliant, and signed by the issuer.
- [Rejected](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md#revised): this response means the transaction is not and couldn't be made compliant.
- [Action Required](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md#revised): this response means the user must complete an action before this transaction can be approved. The anchor will provide a URL that facilitates the action. Upon completion, the user can resubmit the transaction.
- [Pending](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md#pending):  this response means the user KYC could not be verified as approved nor rejected and was marked as "pending". As an arbitrary rule, this server is marking as "pending" all accounts whose email starts with "y".

The implementation can be found in:
[StellarRegulatedAssetsController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarRegulatedAssetsController.php)
and [RegulatedAssetsIntegration.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Stellar/Sep08RegulatedAssets/RegulatedAssetsIntegration.php).

**Example request:**
```json
{
  "tx": "AAAAAgAAAAA0Nk3...OPu4YEqqBsK"
}
```

**Example response:**
```json
{
  "status": "revised",
  "message": "Authorization and deauthorization operations were added.",
  "tx": "AAAAAgAAAAA0Nk3...4/V22h2FyHNt2ALwncmlEq+3hpojZDDQ=="
}
```

#### POST /kyc-status/{STELLAR_ADDRESS}

This endpoint is used for the extra action after `/tx-approve`, as described in the [Action Required](https://github.com/stellar/stellar-protocol/blob/7c795bb9abc606cd1e34764c4ba07900d58fe26e/ecosystem/sep-0008.md#action-required) `SEP-08` doc section.

Currently, an arbitrary criteria is implemented:

- email addresses starting with "x" will have the KYC automatically denied.
- email addresses starting with "y" will have their KYC marked as pending.
- all other emails will be accepted.

*Note: you'll need to resubmit your transaction to `/tx_approve` in order to verify if your KYC was approved.*

The implementation can be found in:
[StellarRegulatedAssetsController.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Http/Controllers/StellarRegulatedAssetsController.php).

**Example request:**
```json
{
  "email_address": "foo@bar.com"
}
```

**Example response:**
```json
{
  "result": "no_further_action_required"
}
```
After the user has been approved or rejected, they can POST their transaction to `/tx-approve` for revision.

If their KYC was rejected, they should see a rejection response. 

**Response example** (rejected for emails starting with "x"):
```json
{
  "status": "rejected",
  "error": "Your KYC was rejected and you're not authorized for operations above ..."
}
```

If their KYC was marked as pending, they should see a pending response. 

**Response example** (pending for emails starting with "y"):

```json
{
  "status": "pending",
  "message": "Your approval request is pending. Please try again later.",
  "timeout" : 1000
}
```

#### GET /kyc-status/{STELLAR_ADDRESS}

Returns the detail of an account that requested KYC, as well some metadata about its status.

*Note: This functionality is for test/debugging purposes and it's not part of the SEP-08 spec.*

**Response example** 
```json
{
    "address": "GDEWF77LQ54ILG72I2GTKABLMXUR6XFV3P4AMAVU4P7YKVVAKNUMADEI",
    "status": "approved"
}
```

#### DELETE /kyc-status/{STELLAR_ADDRESS}

Deletes a stellar account from the list of KYCs. If the stellar address is not in the database to be deleted the server will return with a 404 - Not Found.

*Note: This functionality is for test/debugging purposes and it's not part of the SEP-08 spec.*

**Response example** 
```json
{
   "message": "ok"
}
```

### Source code

The source code of this example can be found in the [anchor reference server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation. It contains the described demo business logic that can easily be extended for your own needs.