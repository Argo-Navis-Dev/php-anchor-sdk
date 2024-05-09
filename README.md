# php-anchor-sdk

The PHP Stellar Anchor SDK makes it easier for PHP developers to implement Stellar Anchors.

Stellar clients make requests to the endpoints of Anchor Servers using sets of standards called [SEPs](https://developers.stellar.org/docs/fundamentals-and-concepts/stellar-ecosystem-proposals) (Stellar Ecosystem Proposals). The PHP Anchor SDK will help PHP developers to implement the Client - Anchor interaction by abstracting the Stellar-specific functionality defined in the SEP's so that developers can focus on business logic.

The SDK is composed of two components:
- A Service Layer Library implementing the Stellar specific functionality described in the corresponding SEPs
- An [Anchor Reference Server](https://github.com/Argo-Navis-Dev/anchor-reference-server) implementation that uses the library.

This is the repo of the Service Layer Library. Pls. see [architecture doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/architecture.md).

The Anchor Reference Server using the library can be found [here](https://github.com/Argo-Navis-Dev/anchor-reference-server). 

## Roadmap:

- Implementation of [SEP-01](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0001.md) Service (Stellar Info File) -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-01.md).
- Implementation of [SEP-10](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md) Service (Stellar Authentication) -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md).
- Implementation of [SEP-12](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md) KYC API Service &  [SEP-09](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md) Standard KYC Fields -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-12.md).
- Implementation of [SEP-24](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md) Hosted Deposit and Withdrawal - Interactive Flow Service  -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-24.md).
- Implementation of [SEP-38](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md) Anchor RFQ Service -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-38.md).
- Implementation of [SEP-06](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md) Deposit and Withdrawal Service -> **Done**, see [doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-06.md).
- Implementation of [SEP-31](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md) Cross-Border Payments Service -> in progress
- Implementation of [SEP-08](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0008.md) Regulated Assets

## Installing the SDK:

`composer require argonavis/php-anchor-sdk`

## Integration docs:

[Docs folder](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/)

## Example Integration

[Anchor Reference Server](https://github.com/Argo-Navis-Dev/anchor-reference-server)
