<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\exception\InvalidSep10JwtData;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\MuxedAccount;
use Throwable;
use UnexpectedValueException;

use function array_key_exists;
use function count;
use function explode;
use function is_string;
use function json_encode;
use function str_starts_with;
use function strval;

class Sep10Jwt
{
    public string $jti; // JWT ID
    public string $iss; // Issuer
    public string $sub; // Subject
    public string $iat; // Issued At
    public string $exp; // Expiration Time


    public ?string $accountId = null;

    public ?string $homeDomain = null;
    public ?string $clientDomain = null;

    public ?string $accountMemo = null;

    public ?string $muxedAccountId = null;

    public ?int $muxedId = null;

    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Constructor.
     *
     * @param string $iss Issuer
     * @param string $sub should contain "accountId:memo" or "muxedAccountId" (starting with M)
     * @param string $iat Issued At
     * @param string $exp Expires At
     * @param string $jti Transaction id/Hash
     * @param string|null $homeDomain home domain if different to issuer.
     * @param string|null $clientDomain client domain if available.
     */
    public function __construct(
        string $iss,
        string $sub,
        string $iat,
        string $exp,
        string $jti,
        ?string $homeDomain = null,
        ?string $clientDomain = null,
    ) {
        $this->jti = $jti;
        $this->iss = $iss;
        $this->sub = $sub;
        $this->iat = $iat;
        $this->exp = $exp;
        $this->homeDomain = $homeDomain;
        $this->clientDomain = $clientDomain;
        $subs = explode(':', $sub);
        if (count($subs) === 2) {
            $this->accountId = $subs[0];
            $this->accountMemo = $subs[1];
        } else {
            try {
                $muxedAccount = MuxedAccount::fromAccountId($sub);

                if (str_starts_with($sub, 'M')) {
                    $this->muxedAccountId = $sub;
                } else {
                    $this->accountId = $muxedAccount->getEd25519AccountId();
                }

                $this->muxedId = $muxedAccount->getId();
            } catch (Throwable $th) {
                self::getLogger()->error(
                    'Failed to parse muxed account.',
                    ['context' => 'jwt', 'account_id' => $sub, 'error' => $th->getMessage(), 'exception' => $th],
                );
            }
        }
    }

    /**
     * Returns the payload for the jwt token
     *
     * @return array<string,string>
     */
    public function getPayload(): array
    {
        $payload = [
            'jti' => $this->jti,
            'iss' => $this->iss,
            'sub' => $this->sub,
            'iat' => $this->iat,
            'exp' => $this->exp,
        ];
        if ($this->clientDomain !== null) {
            $payload['client_domain'] = $this->clientDomain;
        }
        if ($this->homeDomain !== null) {
            $payload['home_domain'] = $this->homeDomain;
        }

        self::getLogger()->debug(
            'The jwt payload.',
            ['context' => 'jwt', 'content' => json_encode($payload)],
        );

        return $payload;
    }

    /**
     * Returns the values stored within the jwt as an array<string,string>.
     * Mandatory keys are: jti, iss, sub, iat, exp
     * Optional keys are: account_id, account_memo, muxed_account_id, muxed_id, client_domain, home_domain
     * Either account_id or muxed_account_id are set. If muxed_account_id is set, muxed_id is also set.
     * muxed_account_id and account_memo cannot be set at the same time.
     *
     * @return array<string,string>
     */
    public function toArray(): array
    {
        $result = [
            'jti' => $this->jti,
            'iss' => $this->iss,
            'sub' => $this->sub,
            'iat' => $this->iat,
            'exp' => $this->exp,
        ];
        if ($this->accountId !== null) {
            $result['account_id'] = $this->accountId;
        }
        if ($this->accountMemo !== null) {
            $result['account_memo'] = $this->accountMemo;
        }
        if ($this->muxedAccountId !== null) {
            $result['muxed_account_id'] = $this->muxedAccountId;
        }
        if ($this->muxedId !== null) {
            $result['muxed_id'] = strval($this->muxedId);
        }
        if ($this->clientDomain !== null) {
            $result['client_domain'] = $this->clientDomain;
        }
        if ($this->homeDomain !== null) {
            $result['home_domain'] = $this->homeDomain;
        }

        return $result;
    }

    /**
     * Parses and creates a Sep10Jwt object from the given data array.
     * This is typically used to create the Sep10Jwt object from an authenticated request.
     * The array must contain the key - value pairs: jti, iss, sub, iat, exp.
     * Optional key - value pairs are home_domain and client_domain.
     * Keys and values must be strings.
     *
     * @param array<string,string> $data data to parse from
     *
     * @throws InvalidSep10JwtData
     */
    public static function fromArray(array $data): Sep10Jwt
    {
        self::getLogger()->debug(
            'Parsing jwt from array.',
            ['context' => 'jwt', 'content' => json_encode($data)],
        );

        if (!isset($data['jti'])) {
            throw new InvalidSep10JwtData('jti can not be null');
        }
        $jti = $data['jti'];

        if (!isset($data['iss'])) {
            throw new InvalidSep10JwtData('iss can not be null');
        }
        $iss = $data['iss'];

        if (!isset($data['sub'])) {
            throw new InvalidSep10JwtData('sub can not be null');
        }
        $sub = $data['sub'];

        if (!isset($data['iat'])) {
            throw new InvalidSep10JwtData('iat can not be null');
        }
        $iat = $data['iat'];

        if (!isset($data['exp'])) {
            throw new InvalidSep10JwtData('exp can not be null');
        }
        $exp = $data['exp'];

        $homeDomain = null;
        if (isset($data['home_domain'])) {
            $homeDomain = $data['home_domain'];
        }

        $clientDomain = null;
        if (isset($data['client_domain'])) {
            $clientDomain = $data['client_domain'];
        }

        return new Sep10Jwt($iss, $sub, $iat, $exp, $jti, $homeDomain, $clientDomain);
    }

    /**
     * Signs and composes the jwt string.
     *
     * @param string $key The $key to sign with
     *
     * @return string The signed jwt token as string.
     */
    public function sign(string $key): string
    {
        return JWT::encode($this->getPayload(), $key, 'HS256');
    }

    /**
     * Validates the given sep 10 jwt string. Returns an Sep10Jwt object if valid.
     * Throws an exception if invalid. For example if expired. (see exceptions).
     *
     * @param string $jwt the jwt string to validate
     * @param string $signerKey the secret key that has been used to sign the jwt
     * @param string|null $issuerUrl the url the jwt was requested from (issued by). If null, it will not be validated.
     *
     * @return Sep10Jwt object created from the given sep10 jwt string.
     *
     * @throws InvalidArgumentException Provided signerKey was empty or malformed
     * @throws DomainException Provided JWT is malformed
     * @throws UnexpectedValueException Provided JWT was invalid
     * @throws SignatureInvalidException Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws BeforeValidException Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws ExpiredException Provided JWT has since expired, as defined by the 'exp' claim
     */
    public static function validateSep10Jwt(string $jwt, string $signerKey, ?string $issuerUrl = null): Sep10Jwt
    {
        $decodedJwt = JWT::decode($jwt, new Key($signerKey, 'HS256'));

        self::getLogger()->debug(
            'Validating jwt.',
            ['context' => 'jwt', 'content' => json_encode($decodedJwt)],
        );

        //print_r($decodedJwt);
        $decodedJwtArray = (array) $decodedJwt;
        if (!array_key_exists('jti', $decodedJwtArray)) {
            throw new UnexpectedValueException('jti not found');
        }
        $jti = $decodedJwtArray['jti'];
        if (!is_string($jti)) {
            throw new UnexpectedValueException('jti is not a string');
        }

        if (!array_key_exists('iss', $decodedJwtArray)) {
            throw new UnexpectedValueException('iss not found');
        }
        $iss = $decodedJwtArray['iss'];
        if (!is_string($iss)) {
            throw new UnexpectedValueException('iss is not a string');
        }

        if ($issuerUrl !== null && $iss !== $issuerUrl) {
            throw new UnexpectedValueException('jwt was not issued by ' . $issuerUrl);
        }

        if (!array_key_exists('sub', $decodedJwtArray)) {
            throw new UnexpectedValueException('sub not found');
        }
        $sub = $decodedJwtArray['sub'];
        if (!is_string($sub)) {
            throw new UnexpectedValueException('sub is not a string');
        }

        if (!array_key_exists('iat', $decodedJwtArray)) {
            throw new UnexpectedValueException('iat not found');
        }
        $iat = $decodedJwtArray['iat'];
        if (!is_string($iat)) {
            throw new UnexpectedValueException('iat is not a string');
        }

        if (!array_key_exists('exp', $decodedJwtArray)) {
            throw new UnexpectedValueException('exp not found');
        }
        $exp = $decodedJwtArray['exp'];
        if (!is_string($exp)) {
            throw new UnexpectedValueException('exp is not a string');
        }

        $homeDomain = null;
        if (array_key_exists('home_domain', $decodedJwtArray)) {
            $hd = $decodedJwtArray['home_domain'];
            if (!is_string($hd)) {
                throw new UnexpectedValueException('home_domain is not a string');
            }
            $homeDomain = $hd;
        }

        $clientDomain = null;
        if (array_key_exists('client_domain', $decodedJwtArray)) {
            $cd = $decodedJwtArray['client_domain'];
            if (!is_string($cd)) {
                throw new UnexpectedValueException('client_domain is not a string');
            }
            $clientDomain = $cd;
        }

        return new Sep10Jwt(
            $iss,
            $sub,
            $iat,
            $exp,
            $jti,
            homeDomain: $homeDomain,
            clientDomain: $clientDomain,
        );
    }

    /**
     * Extracts the account data (account id and optional memo) from the given jwt token.
     * Checks if the account id exists and if the account id and optional memo are valid.
     * If not, it throws InvalidSep10JwtData.
     *
     * @return array<string, string> account id and optional memo. Keys: 'account_id' and 'account_memo'
     *
     * @throws InvalidSep10JwtData if the account was not found or invalid or if there is a memo, but it is invalid.
     */
    public function getValidatedAccountData(): array
    {
        /**
         * @var array<string, string> $accountData
         */
        $accountData = [];
        if ($this->muxedAccountId !== null) {
            try {
                KeyPair::fromAccountId($this->muxedAccountId);
            } catch (Throwable $th) {
                self::getLogger()->debug(
                    'Failed to parse muxed account',
                    ['context' => 'jwt', 'error' => $th->getMessage(), 'exception' => $th],
                );

                throw new InvalidSep10JwtData('invalid account id in jwt token');
            }
            $accountData['account_id'] = $this->muxedAccountId;
        } elseif ($this->accountId !== null) {
            try {
                KeyPair::fromAccountId($this->accountId);
            } catch (Throwable $th) {
                self::getLogger()->debug(
                    'Failed to parse account',
                    ['context' => 'jwt', 'error' => $th->getMessage(), 'exception' => $th],
                );

                throw new InvalidSep10JwtData('invalid account id in jwt token');
            }
            $accountData['account_id'] = $this->accountId;
            if ($this->accountMemo !== null) {
                try {
                    MemoHelper::makeMemoFromSepRequestData($this->accountMemo, 'id');
                } catch (Throwable $t) {
                    self::getLogger()->debug(
                        'Failed to build the memo.',
                        ['context' => 'jwt', 'error' => $t->getMessage(), 'exception' => $t],
                    );

                    throw new InvalidSep10JwtData($t->getMessage());
                }

                $accountData['account_memo'] = $this->accountMemo;
            }
        } else {
            throw new InvalidSep10JwtData('account id not found in jwt token');
        }

        return $accountData;
    }

    /**
     * Returns the logger (initializes if null).
     */
    private static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }

    /**
     * Sets the logger in static context.
     */
    public static function setLogger(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger ?? new NullLogger();
    }
}
