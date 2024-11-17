# Logging

The PHP Anchor SDK offers detailed logging to help you debug and monitor your application on different levels.
When you create a new instance of the SDK service, you can pass a logger to it. The passed logger must implement the PSR-3 Logger Interface.
You can read more about the PSR-3 Logger Interface [here](https://www.php-fig.org/psr/psr-3/).
If the passed logger is not set, the SDK will use the `NullLogger` from the `ArgoNavis\PhpAnchorSdk\logging` package.

The following example show how to pass a logger to the SDK service:

```php
$sep12Service = new Sep12Service(
    customerIntegration: $customerIntegration,
    appConfig: new StellarAppConfig(),
    config: null,
    logger: Log::getLogger(),
);
```
The above example uses the Laravel logger. You can use any logger that implements the PSR-3 Logger Interface.
If you don't want any logging, you can pass `null` value for the `logger` parameter.