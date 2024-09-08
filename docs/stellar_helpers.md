# Stellar helpers

An anchor must regularly check whether certain actions have taken place on the Stellar Network. 
For example, in the case of withdrawals, it must check whether the customer's Stellar payment has been received 
in the anchor's Stellar distribution account. In the case of deposits, it must check whether the recipient account
already trusts the asset that the anchor must send to the customer.

The PHP Stellar Anchor SDK offers helper functions to facilitate these checks. It abstracts Stellar specific logic,
so that you, the developer of an anchor, can focus more on implementing the business logic.


## Payments helper

The [PaymentsHelper](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Stellar/PaymentsHelper.php)
helps you find received Stellar payments for your anchor transactions. It takes into account both, 
normal Stellar transactions and fee bump transactions. It finds both, normal incoming payments and incoming path payments.

This functionality is particularly important to find out whether incoming payments for 
[SEP-6](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md) `withdrawal` and
`withdrawal-exchange`, [SEP-24](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md) 
`withdrawal` and `withdrawal-exchange` and 
[SEP-31](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md) `send` anchor transactions 
have been received.

### Using the payments helper

The following static function is offered to search for received payments:

```php
public static function queryReceivedPayments(
    string $horizonUrl,
    string $receiverAccountId,
    ?string $cursor = null,
): ReceivedPaymentsQueryResult 
```

Now let's explain the parameters and what the function returns.

`$horizonUrl`:

This is the base url to the Stellar Horizon instance that should be used to search for the payments received.
The url of the Testnet Horizon instance offered by SDF is for example `https://horizon-testnet.stellar.org`.

`$receiverAccountId`:

The search for received payments always refers to a receiver account that exists in the Stellar Network.
It is the account on which the anchor expects the payments. This can be a distribution account of the anchor, for example.

The parameter `$receiverAccountId` represents the Stellar Account Id (public key) of the receiver account. 
For example: `GAKRN7SCC7KVT52XLMOFFWOOM4LTI2TQALFKKJ6NKU3XWPNCLD5CFRY2`.

`$cursor`:

During the search, the function will load all Stellar transactions of the given receiver account that took place 
after `$cursor` and start the parsing for received payments within those transactions. This is an important parameter 
because it provides information on how far back the loading of the transactions should go. If `null` is passed, 
the loading starts with the first transaction of the receiver account. To reduce the loading and parsing effort, 
the `$lastTransactionPagingToken` from the search result of the last successful search should be transferred here. 
In some cases, this can also be used to prevent finding a payment that has already been found in the result of a 
previous search.

*Result*:

The result of the search is returned via the result object [ReceivedPaymentsQueryResult](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Stellar/ReceivedPaymentsQueryResult.php). 
It contains following data:

```php
/**
 * @var string $cursor The (start) cursor used to query the transactions for the receiver account.
 */
public string $cursor;

/**
 * @var string $receiverAccountId The id of the Stellar account that received the payments.
 */
public string $receiverAccountId;

/**
 * @var array<ReceivedPayment> $receivedPayments The found received payments of the search.
 */
public array $receivedPayments;

/**
 * @var string $lastTransactionPagingToken The last transaction paging token from the query results.
 * To be used as a cursor in successive queries.
 */
public string $lastTransactionPagingToken;
```

Each found payment is stored in the `$receivedPayments` array. A received payment is represented by a
[ReceivedPayment](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Stellar/ReceivedPayment.php)
object:

```php
/**
 * @var string $assetCode The asset code of the asset received. 'native' if Stellar Lumens - XLM have been received.
 */
public string $assetCode;

/**
 * @var string|null $assetIssuer The asset issuer Stellar account id of the asset received.
 * null if Stellar Lumens - XLM have been received.
 */
public ?string $assetIssuer = null;

/**
 * @var string|null $memoValue The memo value as string of the Memo used in the Stellar Transaction that included
 * this payment. If memo type is 'hash' or 'return' it is the base64 encoded string of the memo value.
 */
public ?string $memoValue = null;

/**
 * @var string|null $memoType The memo type of the Memo used in the Stellar Transaction that included this payment.
 * (possible types: 'text', 'id', 'hash', 'return')
 */
public ?string $memoType = null;

/**
 * @var BigInteger $amountIn The Stellar payment amount (Stellar uses 7 decimals places of precision).
 */
public BigInteger $amountIn;

/**
 * @var string $amountInAsDecimalString The received payment amount as decimal string with 7 places of precision.
 * (`$amountIn` payment amount divided by 10000000). E.g. 25.52 USDC would be `$amountIn`: 2552000000, and
 * `$amountInAsDecimalString`: 25.52000000
 */
public string $amountInAsDecimalString;

/**
 * @var string $senderAccountId The Stellar account id of the payment sender. It is the source account of the payment
 * operation if set, otherwise source account of the transaction that includes this payment.
 */
public string $senderAccountId;

/**
 * @var string $receiverAccountId The Stellar account id of the payment receiver.
 */
public string $receiverAccountId;

/**
 * @var string $stellarTransactionId Id/Hash of the Stellar transaction that includes this payment.
 */
public string $stellarTransactionId;

/**
 * @var string $transactionEnvelopeXdr The base64 encoded Stellar transaction envelope xdr that contains
 * this payment.
 */
public string $transactionEnvelopeXdr;

/**
 * @var string $transactionResultXdr The base 64 encoded transaction result xdr of the Stellar transaction that
 * contains this payment.
 */
public string $transactionResultXdr;
```

### Integration example

An integration example can be found in the anchor reference server: 
[Sep6WithdrawalsWatcher.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Jobs/Sep6WithdrawalsWatcher.php)

You can also find examples in this test case: [StellarPaymentsQueryTest.php](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/tests/StellarPaymentsQueryTest.php)

## Trustline helper

With the [TrustlineHelper](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/src/Stellar/TrustlineHelper.php) 
you can easily find out whether a customer account can receive a certain anchor asset.

This is important, for example, if the customer wants to initiate a [SEP-6](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md) `deposit` or `deposit-exchange`,
because they must be able to receive the purchased asset on their Stellar Account. However, they can only receive it 
if their account trusts the asset via a trustline.

To find out whether the account has such a trustline, the function `checkIfAccountTrustsAsset` of the
`TrustlineHelper` can be called:

```php
public static function checkIfAccountTrustsAsset(
    string $horizonUrl,
    string $accountId,
    string $assetCode,
    string $assetIssuer,
): bool
```

Now let's explain the parameters and what the function returns.

`$horizonUrl`:

This is the base url to the Stellar Horizon instance that should be used to check if the user's account trusts the 
given anchor asset. The url of the Testnet Horizon instance offered by SDF is for example 
`https://horizon-testnet.stellar.org`.

`$accountId`:

The Stellar account id (Public key) of the Stellar account to check if it trusts the asset.

`$assetCode` and `$assetIssuer`: 
An Asset on the Stellar Network is identified by it's asset code (e.g. `USDC`) and the account id of the
asset issuer Stellar account. E.g. `GDC4MJVYQBCQY6XYBZZBLGBNGFOGEFEZDRXTQ3LXFA3NEYYT6QQIJPA2`

*Result*:

The result is `true` if the given Stellar account trusts the anchor asset. Otherwise, `false`.

### Integration example

An integration example can be found in the anchor reference server:
[Sep6DepositPendingTrustWatcher.php](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/app/Jobs/Sep6DepositPendingTrustWatcher.php)




