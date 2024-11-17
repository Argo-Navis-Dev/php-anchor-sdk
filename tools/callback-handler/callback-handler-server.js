// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

//This is a simple Node.js server that listens for incoming POST requests on the '/anchor-tester' endpoint.
//Helps to test and validate the different callback functionality of the Anchor server.
//It verifies the signature of the incoming request and logs the result to the console.
//The server is listening on port 3000. You can change the port number to any other port you prefer.
const express = require('express');
const bodyParser = require('body-parser');
const app = express();
const port = 3000; // or any other port you prefer
var StellarSdk = require('stellar-sdk');

app.use(bodyParser.text({type:"*/*"}));
var serverUrl = `http://localhost:${port}/anchor-tester`;
app.post('/anchor-tester', (req, res) => {
    try {
        const ANCHOR_SERVER_PUBLIC_KEY = ''; // Replace with the public key of the Anchor server
        let keypair = StellarSdk.Keypair.fromPublicKey(ANCHOR_SERVER_PUBLIC_KEY);
        // Use the header value
        let headers = rawHeadersByKey(req.rawHeaders);
        let signature = headers['Signature'];
        console.log('Handling incoming POST request with signature: ' + '<' + signature + '>');

        let ts = signature.split(',')[0];
        ts = ts.split('=')[1];
        var parsedTs = new Date(ts * 1000);
        console.log('Request timstamp: <' + parsedTs + '>');
        console.log('Now: <' + new Date() + '>');

        let signedPayload = signature.substring(signature.indexOf('s=') + 2, signature.length);
        console.log('Signature: <' + signedPayload + '>');

        let requestBody = req.body;

        let decodedSignedPayload = Buffer.from(signedPayload, 'base64');
        let constructedSignature = `${ts}.${serverUrl}.${requestBody}`;
        console.log('Constructed plain signature: <' + constructedSignature + '>');
        const isValid = keypair.verify(constructedSignature, decodedSignedPayload);
        console.log(JSON.parse(requestBody));
        console.log('The signature is valid:', isValid);
    } catch (error) {
        console.error('Error verifying the signature:', error);
    }

    res.send('POST request received');
});

// Start the server
app.listen(port, () => {
    console.log(`Server is listening on: ${serverUrl}`);
});

function rawHeadersByKey(rawHeaders)
{
    var headersObject = {}
    var iterator = createRawHeadersIterator(rawHeaders);
    var headerIteration = iterator.next();
    while (!headerIteration.done) {
        let header = headerIteration.value;
        headersObject[header.name] = header.value;
        headerIteration = iterator.next();
    }

    return headersObject;
}

function * createRawHeadersIterator(arr)
{
    var curr = 0;
    while (curr < arr.length) {
        if (yield { name: arr[curr++], value: arr[curr++] }) {
            curr = 0;
        }
    }
}