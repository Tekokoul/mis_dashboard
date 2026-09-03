<?php
/**
 * Sign in with Microsoft Entra ID (OpenID Connect, authorization code + PKCE).
 *
 * Nothing here runs unless the container was given SSO_TENANT_ID,
 * SSO_CLIENT_ID and SSO_CLIENT_SECRET (see .env.example); the entrypoint turns
 * them into the _SSO_* constants in settings.local.php. With them absent the
 * login page shows no Microsoft button and both SSO routes answer 404.
 *
 * Flow: users/sso_login builds state + nonce + PKCE verifier into the session
 * and redirects to the authorization endpoint; Entra sends the browser back to
 * users/sso_callback with a code; the code is exchanged server-side for an ID
 * token, whose RS256 signature is checked against the tenant's published keys
 * and whose issuer / audience / tenant / nonce / times are all verified before
 * a single claim is trusted. No PHP libraries: curl + openssl only.
 *
 * Endpoints come from OpenID discovery ({authority}/.well-known/openid-configuration),
 * cached for a day, so the authority can be pointed at a local mock for tests.
 */
class entraSso
{
    const DISCOVERY_TTL = 86400;   // seconds
    const CLOCK_SKEW    = 300;     // seconds of tolerance on exp / nbf / iat

    /** @return array|null  null when SSO is not configured */
    public static function config()
    {
        $tenant = defined('_SSO_TENANT_ID') ? trim((string)_SSO_TENANT_ID) : '';
        $client = defined('_SSO_CLIENT_ID') ? trim((string)_SSO_CLIENT_ID) : '';
        $secret = defined('_SSO_CLIENT_SECRET') ? (string)_SSO_CLIENT_SECRET : '';
        if ($tenant === '' || $client === '' || $secret === '') {
            return null;
        }
        $authority = defined('_SSO_AUTHORITY') && trim((string)_SSO_AUTHORITY) !== ''
            ? rtrim(trim((string)_SSO_AUTHORITY), '/')
            : 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/v2.0';
        $domains = defined('_SSO_ALLOWED_DOMAINS') ? (string)_SSO_ALLOWED_DOMAINS : '';
        $domains = array_values(array_filter(array_map(function ($d) {
            return strtolower(trim($d));
        }, explode(',', $domains))));
        $group = defined('_SSO_DEFAULT_GROUP') ? (int)_SSO_DEFAULT_GROUP : 4;
        return [
            'tenant'    => $tenant,
            'client_id' => $client,
            'secret'    => $secret,
            'authority' => $authority,
            'domains'   => $domains,
            'group'     => ($group > 0) ? $group : 4,
        ];
    }

    public static function enabled()
    {
        return self::config() !== null;
    }

    // ------------------------------------------------------------------ helpers

    public static function b64url_encode($bin)
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64url_decode($str)
    {
        $str = strtr($str, '-_', '+/');
        $pad = strlen($str) % 4;
        if ($pad) { $str .= str_repeat('=', 4 - $pad); }
        return base64_decode($str, true);
    }

    private static function cacheFile($name)
    {
        return rtrim(sys_get_temp_dir(), '/') . '/afcdc-sso-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) . '.json';
    }

    /** GET a JSON document; false on any failure. */
    private static function httpGetJson($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) { return false; }
        $json = json_decode($body, true);
        return is_array($json) ? $json : false;
    }

    /** Discovery document, cached. */
    public static function discovery($cfg)
    {
        $file = self::cacheFile('discovery-' . md5($cfg['authority']));
        if (is_file($file) && (time() - filemtime($file)) < self::DISCOVERY_TTL) {
            $doc = json_decode((string)file_get_contents($file), true);
            if (is_array($doc)) { return $doc; }
        }
        $doc = self::httpGetJson($cfg['authority'] . '/.well-known/openid-configuration');
        if ($doc === false) { return false; }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'] as $k) {
            if (empty($doc[$k]) || !is_string($doc[$k])) { return false; }
        }
        @file_put_contents($file, json_encode($doc), LOCK_EX);
        return $doc;
    }

    /** JWKS keys, cached; $force refetches (a key id we have never seen). */
    public static function jwks($cfg, $jwksUri, $force = false)
    {
        $file = self::cacheFile('jwks-' . md5($jwksUri));
        if (!$force && is_file($file) && (time() - filemtime($file)) < self::DISCOVERY_TTL) {
            $doc = json_decode((string)file_get_contents($file), true);
            if (is_array($doc) && isset($doc['keys'])) { return $doc; }
        }
        $doc = self::httpGetJson($jwksUri);
        if ($doc === false || !isset($doc['keys']) || !is_array($doc['keys'])) { return false; }
        @file_put_contents($file, json_encode($doc), LOCK_EX);
        return $doc;
    }

    /** RSA public key (n, e in base64url) -> PEM, via a hand-built SubjectPublicKeyInfo. */
    public static function jwkToPem(array $jwk)
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) { return false; }
        $n = self::b64url_decode($jwk['n']);
        $e = self::b64url_decode($jwk['e']);
        if ($n === false || $e === false) { return false; }
        $der = function ($tag, $content) {
            $len = strlen($content);
            if ($len < 0x80)       { $l = chr($len); }
            elseif ($len < 0x100)  { $l = "\x81" . chr($len); }
            elseif ($len < 0x10000){ $l = "\x82" . chr($len >> 8) . chr($len & 0xff); }
            else                   { $l = "\x83" . chr($len >> 16) . chr(($len >> 8) & 0xff) . chr($len & 0xff); }
            return chr($tag) . $l . $content;
        };
        $int = function ($bin) use ($der) {
            // INTEGERs are signed: a leading 1-bit needs a 0x00 prefix.
            if (ord($bin[0]) & 0x80) { $bin = "\x00" . $bin; }
            return $der(0x02, $bin);
        };
        $rsaKey  = $der(0x30, $int($n) . $int($e));
        $algId   = $der(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00"); // rsaEncryption, NULL
        $bitStr  = $der(0x03, "\x00" . $rsaKey);
        $spki    = $der(0x30, $algId . $bitStr);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    // ------------------------------------------------------------------ the flow

    /** Build the authorization URL and store state / nonce / verifier in the session. */
    public static function beginLogin($cfg, $redirectUri)
    {
        $disc = self::discovery($cfg);
        if ($disc === false) { return false; }
        $state    = bin2hex(random_bytes(16));
        $nonce    = bin2hex(random_bytes(16));
        $verifier = self::b64url_encode(random_bytes(48));
        $_SESSION['sso'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'started' => time()];
        $params = [
            'client_id'             => $cfg['client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => $redirectUri,
            'response_mode'         => 'query',
            'scope'                 => 'openid profile email',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => self::b64url_encode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ];
        return $disc['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the code and verify the ID token. Returns the verified claims,
     * or a string with a short reason for the log. Never returns a partially
     * verified token.
     */
    public static function completeLogin($cfg, $redirectUri, $code, $state)
    {
        $pending = $_SESSION['sso'] ?? null;
        unset($_SESSION['sso']);                                   // one attempt per state
        if (!is_array($pending) || empty($pending['state']) || !hash_equals($pending['state'], (string)$state)) {
            return 'state mismatch';
        }
        if ((time() - (int)($pending['started'] ?? 0)) > 600) {
            return 'login took too long';
        }
        $disc = self::discovery($cfg);
        if ($disc === false) { return 'discovery unavailable'; }

        // Code -> tokens, server to server, with the client secret.
        $ch = curl_init($disc['token_endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['secret'],
                'grant_type'    => 'authorization_code',
                'code'          => (string)$code,
                'redirect_uri'  => $redirectUri,
                'code_verifier' => $pending['verifier'],
                'scope'         => 'openid profile email',
            ], '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $tokens = is_string($body) ? json_decode($body, true) : null;
        if ($http !== 200 || !is_array($tokens) || empty($tokens['id_token'])) {
            $err = is_array($tokens) ? (string)($tokens['error'] ?? 'no id_token') : 'http ' . $http;
            return 'token exchange failed (' . $err . ')';
        }

        return self::verifyIdToken($cfg, $disc, (string)$tokens['id_token'], $pending['nonce']);
    }

    /** Full ID-token verification. Returns claims (array) or a reason (string). */
    public static function verifyIdToken($cfg, $disc, $jwt, $expectedNonce)
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) { return 'malformed token'; }
        list($h64, $p64, $s64) = $parts;
        $header  = json_decode((string)self::b64url_decode($h64), true);
        $claims  = json_decode((string)self::b64url_decode($p64), true);
        $sig     = self::b64url_decode($s64);
        if (!is_array($header) || !is_array($claims) || $sig === false) { return 'malformed token'; }
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) { return 'unexpected algorithm'; }

        // Signature, against the tenant's published keys (refetch once on an unknown kid).
        $pem = false;
        foreach ([false, true] as $force) {
            $jwks = self::jwks($cfg, $disc['jwks_uri'], $force);
            if ($jwks === false) { return 'keys unavailable'; }
            foreach ($jwks['keys'] as $k) {
                if (($k['kid'] ?? null) === $header['kid'] && (($k['use'] ?? 'sig') === 'sig')) {
                    $pem = self::jwkToPem($k);
                    break 2;
                }
            }
        }
        if ($pem === false) { return 'unknown signing key'; }
        $pub = openssl_pkey_get_public($pem);
        if ($pub === false) { return 'bad signing key'; }
        if (openssl_verify($h64 . '.' . $p64, $sig, $pub, OPENSSL_ALGO_SHA256) !== 1) { return 'bad signature'; }

        // Claims. Every one of these is a hard failure.
        $now = time();
        if (($claims['iss'] ?? '') !== $disc['issuer'])                       { return 'wrong issuer'; }
        $aud = $claims['aud'] ?? '';
        if (is_array($aud) ? !in_array($cfg['client_id'], $aud, true) : $aud !== $cfg['client_id']) { return 'wrong audience'; }
        if (isset($claims['tid']) && (string)$claims['tid'] !== $cfg['tenant']) { return 'wrong tenant'; }
        if (!isset($claims['exp']) || (int)$claims['exp'] < $now - self::CLOCK_SKEW)  { return 'token expired'; }
        if (isset($claims['nbf']) && (int)$claims['nbf'] > $now + self::CLOCK_SKEW)   { return 'token not yet valid'; }
        if (isset($claims['iat']) && (int)$claims['iat'] > $now + self::CLOCK_SKEW)   { return 'token from the future'; }
        if (empty($claims['nonce']) || !hash_equals((string)$expectedNonce, (string)$claims['nonce'])) { return 'nonce mismatch'; }
        if (empty($claims['oid']) && empty($claims['sub']))                   { return 'no subject'; }
        return $claims;
    }

    /** The account identity from verified claims: [subject, email/upn, given, family]. */
    public static function identity(array $claims)
    {
        $subject = (string)($claims['oid'] ?? $claims['sub']);
        $email   = strtolower(trim((string)($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? '')));
        $given   = trim((string)($claims['given_name'] ?? ''));
        $family  = trim((string)($claims['family_name'] ?? ''));
        if ($given === '' && $family === '' && !empty($claims['name'])) {
            $name = trim((string)$claims['name']);
            $pos  = strrpos($name, ' ');
            if ($pos === false) { $given = $name; } else { $given = substr($name, 0, $pos); $family = substr($name, $pos + 1); }
        }
        return [$subject, $email, mb_substr($given, 0, 45), mb_substr($family, 0, 45)];
    }
}
