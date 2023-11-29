# php-anchor-sdk

The PHP Stellar Anchor SDK will make it easier for PHP developers to implement Stellar Anchors.

Stellar clients make requests to the endpoints of Anchor Servers using sets of standards called [SEPs](https://developers.stellar.org/docs/fundamentals-and-concepts/stellar-ecosystem-proposals) (Stellar Ecosystem Proposals). The PHP Anchor SDK will help PHP developers to implement the Client - Anchor interaction by abstracting the Stellar-specific functionality defined in the SEP's so that developers can focus on business logic.

The SDK will be composed of two components:
- A Service Layer Library implementing the Stellar specific functionality described in the corresponding SEPs
- A Reference Server implementation that uses the library.


This is the repo of the Service Layer Library. Pls. see [architecture doc](https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/architecture.md).

Roadmap:

- Implementation of [SEP-01](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0001.md) Service (Stellar Info File) until Dec.10.2023
- Implementation of [SEP-10](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0010.md) Service (Stellar Authentication) until Dec.27.2023
- Implementation of [SEP-12](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0012.md) KYC API Service &  [SEP-09](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0009.md) Standard KYC Fields until Jan.10.2024
- Implementation of [SEP-24](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0024.md) Hosted Deposit and Withdrawal - Interactive Flow Service until Jan.29.2024
- Implementation of [SEP-31](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0031.md) Cross-Border Payments Service until Feb.15.2024
- Implementation of [SEP-38](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0038.md) Anchor RFQ Service until Mar.04.2024
- Implementation of [SEP-06](https://dashboard.communityfund.stellar.org/redirect?url=https%3A%2F%2Fgithub.com%2Fstellar%2Fstellar-protocol%2Fblob%2Fmaster%2Fecosystem%2Fsep-0006.md) Deposit and Withdrawal Service until Mar.25.2024








