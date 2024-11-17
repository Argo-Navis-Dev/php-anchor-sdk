# Callbacks
The different SEPs provide callback mechanisms to notify the receiver about the status of a transaction or customer.
The `CallbackHelper` class provides a generic method to send the callback POST request to the receiver.
The following static function is offered to send a callback:
```php
public static function sendCallbackRequest(
        object $requestBodyData,
        string $serverAccountSigningKey,
        ?string $callbackUrl = null,
): void
```

Now let's explain the parameters of the function:

`$requestBodyData`: The data that should be sent in the body of the callback POST request. This data should be an object that can be converted to JSON.<br>
`$serverAccountSigningKey`: The Anchor server signing seed.<br>
`$callbackUrl`: The URL to which the callback POST request should be sent. If `null` no request will be sent.<br>

## Callback verification
Under the `tools/callback-handler` you can find a simple Node.js server that listens for incoming requests.
It validates the signature of the request (callback) and logs the payload to the console.

### Run the callback handler server
To run the callback handler server, follow these steps:
```bash
cd tools/callback-handler
npp install # Install the required packages
node callback-handler-server.js # Start the server
```
