# Stellar Toml - SEP-01

Every Anchor must define a `stellar.toml` file to describe the Anchors’s supported assets, any validators that are run, and other meta data. 

This procedure is described in the Stellar Ecosystem Proposal [SEP-01](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0001.md).

The PHP Stellar SDK can generate the TOML-formatted stellar.toml file for the Anchor server.

## Introduction

The `stellar.toml` file is used to provide a common place where the Internet can find information about your organization’s Stellar integration. By setting the `home_domain` of your Stellar account to the domain that hosts your `stellar.toml`, you can create a definitive link between this information and that account. Any website can publish Stellar network information, and the `stellar.toml` is designed to be readable by both humans and machines.

In the next steps we will learn how to expose the stellar.toml file for your Anchor, how to fill it with the needed data and how to connect the domain to your Stellar account so that the file can be found by other services and apps, such as wallets or exchanges.

## Path

Given the domain `DOMAIN`, the `stellar.toml` will be searched for at the following location:

```
https://DOMAIN/.well-known/stellar.toml
```

You must enable CORS on the `stellar.toml` so people can access this file from other sites. The following HTTP header must be set for an HTTP response for `/.well-known/stellar.toml` file request.

```
Access-Control-Allow-Origin: *
```

## Expose

### Static file

The easiest way to expose the `stellar.toml` is to serve the file itself by putting the filled and correctly formatted file into a folder and return it on every request.

Example:
```php
Route::get('.well-known/stellar.toml', function () {
    $filePath = storage_path('app/stellar.toml'); // Adjust the file path as needed
    if (file_exists($filePath)) {
        $contents = file_get_contents($filePath);
        return response($contents, 200)
            ->header('Content-Type', 'text/plain');
    } else {
        return response('File not found', 404);
    }
});
```

### Using the SDK

For the static file approach, however, you need the already formatted file, and you have to replace it every time something changes. You need to know how to format the contents and the procedure is error-prone.

In contrast, the PHP Anchor SDK can properly format the content for you and gives you much more flexibility.

Next, let's see how we can use the SDK for this. 

First, let's forward the request to our controller `StellarTomlController`:
```php
Route::prefix('.well-known')->group(function () {
    Route::get('/stellar.toml', [StellarTomlController::class, 'toml']);
});
```
This will forward the request for the `stellar.toml` file to our controller by calling its method `toml`:

```php
public function toml():ResponseInterface {
    $provider = new TomlProvider();
    return $provider->handleFromData(self::tomlData());
}
```

#### TomlProvider

`TomlProvider` is the PHP Anchor SDK class that handles `stellar.toml`requests. In the example above we used its method ```handleFromData(TomlData $data)```.
It returns an object that implements the [PSR-7](https://www.php-fig.org/psr/psr-7/) `ResponseInterface`. This object can be used by every framework that supports the PSR-7 standard.

As an input parameter the method accepts an object of type `TomlData`. Next let's have a look how we can create and fill such an object.

In this example we fill it manually, but you can also for example use data from you database to fill it:

```php
private function tomlData():TomlData {
        $tomlData = new TomlData();

        $generalInfo = new GeneralInformation();
        $generalInfo->version = "2.0.0";
        $generalInfo->networkPassphrase = Network::testnet()->getNetworkPassphrase();
        $generalInfo->webAuthEndpoint = "http://localhost:8000/webauth";
        $generalInfo->signingKey = "GCIFNTTPECZ4M2PXR76FTRDJM4AV7P2Q7275FL24BNFB56XCWMO53474";
        $tomlData->generalInformation = $generalInfo;

        $doc = new Documentation();
        $doc->orgName = "Argo Navis Dev";
        $doc->orgGithub = "https://github.com/Argo-Navis-Dev";
        $doc->orgUrl = "https://argo-navis.dev";
        $doc->orgDescription = 'Argo Navis Dev provides development services related to Stellar';
        $doc->orgOfficialEmail = 'info@argo-navis.dev';
        $tomlData->documentation = $doc;

        $principals = new Principals();
        $firstPoc = new PointOfContact();
        $firstPoc->name = 'Bence';
        $firstPoc->email = 'bence@argo-navis.dev';
        $principals->add($firstPoc);

        $secondPoc = new PointOfContact();
        $secondPoc->name = 'Christian';
        $secondPoc->email = 'christian@argo-navis.dev';
        $principals->add($secondPoc);
        $tomlData->principals = $principals;

        return $tomlData;
}
```

As you can see, the `TomlData` can be filled by using its member variables. By doing so, you do not have to care about 
formatting the data to correspond Stellar specific `stellar.toml` standard. The generation of the correctly formatted
response content is done by the SDK.

`TomlProvider` also offers following different handler methods that we could use:

```php
handleFromFile(string $pathToFile): ResponseInterface
```
Returns a response object that contains the contents of the given file. It does not validate the file content. A method to validate the file content is offered by the `TomlData` class that we will describe later.

```php
handleFromUrl(string $url, ClientInterface $httpClient): ResponseInterface
```
Returns a response object that contains the contents loaded from a given url. It does not validate the content. A method to validate the file content is offered by the `TomlData` class that we will describe later.

#### TomlData

The `TomlData` class can be used to pass the data to the `TomlProvider` but also to parse data form a `stellar.toml` file. This can be very useful if we want to communicate with other applications or anchors that also provide a stellar.toml file. We will need this for example when we implement [SEP-24](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md) and [SEP-31](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md). It is also useful to check if a static `stellar.toml` file is correctly formatted.

Let's have a look to the methods that it offers:

```php
public static function fromUrl(string $url, ClientInterface $httpClient): TomlData
```
It can be used to fetch the query data from a given url. It requests the data and parses it into the `TomlData` object that is returned, so we can access the property that we need from the result:

```php
$tomlData = TomlData::fromUrl("https://ultrastellar.com/.well-known/stellar.toml", new Client());
$signingKey = $tomlData->generalInformation->signingKey;
```

It also throws exceptions if the data could not be loaded (`TomlDataNotLoaded`) or could not be parsed (`ParseException`).

Next:
```php
public static function fromFile(string $pathToFile): TomlData
```
Loads and parses the data from a given file.

```php
public static function fromString(string $toml): TomlData
```
Loads and parses the data from a given string.

```php
public static function fromDomain(string $domain, ClientInterface $httpClient): TomlData
```
Similar to `fromUrl` but it composes the request url itself by using the given domain name.

```fromDomain("ultrastellar.com,...)``` 

dose the same as 

```fromUrl("https://ultrastellar.com/.well-known/stellar.toml", ...)```

## Conclusion

As we saw in the description above, the SDK abstracts the stellar specific logic to format or parse contents of the `stellar.toml` files. There are different ways to do this, so we can choose the way that best suits our needs.

## Further readings:
- [SEP-01: Stellar Info File docs](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0001.md)
- [PSR-7: HTTP message interfaces](https://www.php-fig.org/psr/psr-7/)
- [Reference Server Implementation](https://github.com/Argo-Navis-Dev/anchor-reference-server/blob/main/routes/web.php)
- [Stellar Toml SDK Test Cases](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/tests/Sep01Test.php)

