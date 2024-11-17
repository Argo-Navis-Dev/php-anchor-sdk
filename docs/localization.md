# Localization

The SDK offers localization support for the messages that are sent to the client.
Some SEPs define a lang parameter in the request that can be used to specify the language of the response.
For example in SEP-12 `GET /customer` request the lang parameter can be used to specify the language of the different part (description, choices fields) of the response.
You can check a concrete example in the SEP-12 documentation by clicking [here](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#request).
The SDK itself does not provide translations for the messages, but it offers a way to incorporate the translation of the server which integrates the SDK.

## How to use the localization

Any server which integrates the SDK and wants to provide translations for the messages can do so by implementing the `AppConfig` interface `getLocalizedText` method.

```php
public function getLocalizedText(
        string $key,
        ?string $locale = 'en',
        ?string $default = null,
        ?array $params = null,
    ): string
```

When the server passes the `AppConfig` interface implementation to the SDK, the SDK will use the `getLocalizedText` method to get the localized text for the messages.
If the server does not provide a translation for a message, returns the default text passed by the SDK.
Depending on the server framework can return the localized text from a file, a database, or any other source.
In the following example we show how to implement the `getLocalizedText` method to provide translations for the messages in Laravel.

```php
public function getLocalizedText(
        string $key,
        ?string $locale = 'en',
        ?string $default = null,
        ?array $params = [],
    ): string {
        if ($params === null) {
            $params = [];
        }
        $localizedText = __($key, $params, $locale);
        if ($localizedText === $key) {
            $localizedText = $default ?? $key;
        }
        Log::info(
            'Retrieving the localized text by the SDK.',
            ['localized_text' => $localizedText, 'key' => $key, 'locale' => $locale, 'default' => $default,
                'params' => json_encode($params),
            ],
        );

        return $localizedText;
    }
```