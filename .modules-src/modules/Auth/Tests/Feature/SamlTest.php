<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Models\UserSsoIdentity;
use Modules\Auth\Saml\SamlSettings;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * These tests mint REAL signed assertions.
 *
 * A mocked SAML toolkit would be worth almost nothing: the entire security of
 * this flow is XML signature validation plus a handful of conditions, and a
 * stub that returns "valid" tests none of it. So the suite stands up a
 * throwaway IdP — self-signed certificate, real RSA signature over the
 * assertion — and every negative case is a genuinely malformed or hostile
 * document rather than a flag flipped on a mock.
 */
beforeEach(function () {
    [$key, $cert] = testIdpKeypair();

    config([
        'auth.saml.enabled' => true,
        'auth.saml.label'   => 'Acme Directory',
        'auth.saml.idp'     => [
            'entity_id' => 'https://idp.test/metadata',
            'sso_url'   => 'https://idp.test/sso',
            'slo_url'   => null,
            'x509'      => $cert,
        ],
        'auth.saml.sp'                => ['entity_id' => null, 'x509' => null, 'private_key' => null, 'name_id_format' => null],
        'auth.saml.security'          => ['allow_idp_initiated' => false],
        'auth.saml.attributes'        => ['email' => '', 'first_name' => '', 'last_name' => '', 'subject' => ''],
        'auth.saml.return_url'        => 'https://app.test/auth/sso/complete',
        'auth.sso.allow_registration' => false,
        'auth.sso.allowed_domains'    => '',
    ]);
});

/**
 * One self-signed keypair for the whole file. Generating RSA keys is the
 * slowest thing here by an order of magnitude, and every test wants the same
 * IdP identity.
 *
 * @return array{0: string, 1: string} private key PEM, certificate body
 */
function testIdpKeypair(): array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr  = openssl_csr_new(['commonName' => 'idp.test'], $pkey, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);

    openssl_x509_export($x509, $certPem);
    openssl_pkey_export($pkey, $keyPem);

    return $cached = [$keyPem, SamlSettings::normalizeCert($certPem)];
}

/** A SECOND identity, to prove a valid signature from the wrong key is refused. */
function otherKeypair(): array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr  = openssl_csr_new(['commonName' => 'evil.test'], $pkey, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);

    openssl_x509_export($x509, $certPem);
    openssl_pkey_export($pkey, $keyPem);

    return $cached = [$keyPem, SamlSettings::normalizeCert($certPem)];
}

/**
 * Build a SAML Response carrying one assertion.
 *
 * @param  array<string, mixed>  $o
 */
function samlResponse(array $o = []): string
{
    $now          = time();
    $acs          = url(SamlSettings::ACS_PATH);
    $audience     = $o['audience'] ?? url(SamlSettings::METADATA_PATH);
    $issuer       = $o['issuer'] ?? 'https://idp.test/metadata';
    $inResp       = $o['inResponseTo'] ?? null;
    $notAfter     = gmdate('Y-m-d\TH:i:s\Z', $o['notOnOrAfter'] ?? $now + 300);
    $notBefore    = gmdate('Y-m-d\TH:i:s\Z', $o['notBefore'] ?? $now - 60);
    $issueInstant = gmdate('Y-m-d\TH:i:s\Z', $o['issueInstant'] ?? $now);
    $assertionId  = $o['assertionId'] ?? '_a'.bin2hex(random_bytes(16));
    $nameId       = $o['nameId'] ?? 'ada@example.com';
    $attributes   = $o['attributes'] ?? ['email' => ['ada@example.com']];

    $inRespAttr = $inResp !== null ? ' InResponseTo="'.htmlspecialchars($inResp, ENT_QUOTES).'"' : '';

    $attributeXml = '';

    foreach ($attributes as $name => $values) {
        $valueXml = '';

        foreach ((array) $values as $v) {
            $valueXml .= '<saml:AttributeValue xsi:type="xs:string">'.htmlspecialchars((string) $v, ENT_QUOTES).'</saml:AttributeValue>';
        }
        $attributeXml .= '<saml:Attribute Name="'.htmlspecialchars((string) $name, ENT_QUOTES).'" '
            .'NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:basic">'.$valueXml.'</saml:Attribute>';
    }
    $attributeStatement = $attributeXml !== '' ? '<saml:AttributeStatement>'.$attributeXml.'</saml:AttributeStatement>' : '';

    $assertion = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
        .'xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        .'ID="'.$assertionId.'" Version="2.0" IssueInstant="'.$issueInstant.'">'
        .'<saml:Issuer>'.htmlspecialchars($issuer, ENT_QUOTES).'</saml:Issuer>'
        .'<saml:Subject>'
        .'<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.htmlspecialchars($nameId, ENT_QUOTES).'</saml:NameID>'
        .'<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">'
        .'<saml:SubjectConfirmationData'.$inRespAttr.' NotOnOrAfter="'.$notAfter.'" Recipient="'.htmlspecialchars($acs, ENT_QUOTES).'"/>'
        .'</saml:SubjectConfirmation>'
        .'</saml:Subject>'
        .'<saml:Conditions NotBefore="'.$notBefore.'" NotOnOrAfter="'.$notAfter.'">'
        .'<saml:AudienceRestriction><saml:Audience>'.htmlspecialchars($audience, ENT_QUOTES).'</saml:Audience></saml:AudienceRestriction>'
        .'</saml:Conditions>'
        .'<saml:AuthnStatement AuthnInstant="'.$issueInstant.'" SessionIndex="_s'.bin2hex(random_bytes(8)).'">'
        .'<saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext>'
        .'</saml:AuthnStatement>'
        .$attributeStatement
        .'</saml:Assertion>';

    if (($o['sign'] ?? true) === true) {
        [$key, $cert] = $o['signWith'] ?? testIdpKeypair();
        $assertion    = signAssertion($assertion, $key, $cert);
    }

    // Encryption happens AFTER signing, which is the real order: the IdP signs
    // the assertion, then encrypts the signed document for the SP. Signing an
    // already-encrypted blob would sign ciphertext and prove nothing about the
    // statement inside it.
    if (isset($o['encryptFor'])) {
        $assertion = '<saml:EncryptedAssertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">'
            .encryptAssertionXml($assertion, $o['encryptFor'])
            .'</saml:EncryptedAssertion>';
    }

    $response = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
        .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
        .'ID="_r'.bin2hex(random_bytes(16)).'" Version="2.0" IssueInstant="'.$issueInstant.'" '
        .'Destination="'.htmlspecialchars($acs, ENT_QUOTES).'"'.$inRespAttr.'>'
        .'<saml:Issuer>'.htmlspecialchars($issuer, ENT_QUOTES).'</saml:Issuer>'
        .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
        .$assertion
        .'</samlp:Response>';

    return base64_encode($response);
}

/**
 * Sign an assertion and put the Signature where the schema requires it.
 *
 * Utils::addSign inserts the Signature as the ROOT's first child, which for an
 * Assertion means before <Issuer>. The SAML schema fixes the order as Issuer,
 * Signature, Subject — and php-saml validates responses against the XSD — so an
 * assertion signed the naive way is rejected as malformed rather than as
 * unsigned, which would make every negative test below pass for the wrong
 * reason. Real IdPs emit it in the right place; so does this one.
 */
function signAssertion(string $assertionXml, string $key, string $cert): string
{
    // formatCert re-adds the PEM headers. addSign passes the certificate on to
    // add509Cert with isPEMFormat = true, so a bare base64 body parses to
    // nothing and the signature ships an EMPTY <X509Data> — which fails schema
    // validation before anyone looks at the cryptography. Config wants the
    // stripped form, xmlseclibs wants the wrapped one.
    $signed = Utils::addSign($assertionXml, $key, Utils::formatCert($cert, true));

    $dom = new DOMDocument;
    $dom->loadXML($signed);

    $root      = $dom->documentElement;
    $signature = $root->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
    $issuer    = $root->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0);

    if ($signature && $issuer && $signature->nextSibling !== $issuer->nextSibling) {
        $root->insertBefore($signature, $issuer->nextSibling);
    }

    return $dom->saveXML($dom->documentElement);
}

/** Mint a RelayState the way `start` does, bound to a request id. */
function samlRelay(string $requestId = '_req123'): string
{
    $relay = 'relay-'.bin2hex(random_bytes(8));

    Cache::put('saml:request:'.hash('sha256', $relay), ['request_id' => $requestId, 'ip' => '127.0.0.1'], 600);

    return $relay;
}

function acsQuery(string $location, string $key): ?string
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

    return $q[$key] ?? null;
}

/** POST an assertion to the ACS and hand back the redirect Location. */
function postAssertion(string $samlResponse, ?string $relay): string
{
    $payload = ['SAMLResponse' => $samlResponse];

    if ($relay !== null) {
        $payload['RelayState'] = $relay;
    }

    return test()->post('/api/v1/auth/saml/acs', $payload)->headers->get('Location') ?? '';
}

// ---------------------------------------------------------------------------
// Discovery / metadata
// ---------------------------------------------------------------------------

test('metadata describes this SP and is reachable before an IdP is configured', function () {
    // Chicken and egg: the IdP administrator needs our metadata in order to
    // give us the entity id and certificate that "configured" checks for.
    config(['auth.saml.idp' => ['entity_id' => null, 'sso_url' => null, 'slo_url' => null, 'x509' => null]]);

    $xml = $this->get('/api/v1/auth/saml/metadata')->assertOk()->getContent();

    expect($xml)->toContain('EntityDescriptor')
        ->and($xml)->toContain(url(SamlSettings::ACS_PATH))
        ->and($xml)->toContain('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST');
});

test('the whole SAML surface disappears when it is switched off', function () {
    config(['auth.saml.enabled' => false]);

    $this->getJson('/api/v1/auth/saml/start')->assertNotFound();
});

test('an enabled but half-configured IdP says so rather than throwing', function () {
    config(['auth.saml.idp.x509' => null]);

    $this->getJson('/api/v1/auth/saml/start')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Sign-in with Acme Directory is not finished being set up.');
});

test('SAML is offered alongside OIDC in one discovery list', function () {
    $data = $this->getJson('/api/v1/auth/sso/providers')->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['kind'])->toBe('saml')
        ->and($data[0]['label'])->toBe('Acme Directory');
});

// ---------------------------------------------------------------------------
// start
// ---------------------------------------------------------------------------

test('start returns an AuthnRequest URL and remembers the request id', function () {
    $url = $this->getJson('/api/v1/auth/saml/start')->assertOk()->json('url');

    expect($url)->toStartWith('https://idp.test/sso');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['SAMLRequest'] ?? null)->not->toBeEmpty()
        ->and($query['RelayState'] ?? null)->not->toBeEmpty();

    // The binding that makes a later assertion answerable to THIS request.
    $stored = Cache::get('saml:request:'.hash('sha256', $query['RelayState']));

    expect($stored['request_id'] ?? null)->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// The signature is the whole ballgame
// ---------------------------------------------------------------------------

test('a validly signed assertion signs the user in', function () {
    $user  = User::factory()->create(['email' => 'ada@example.com']);
    $relay = samlRelay('_req-1');

    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1']), $relay);

    expect($location)->toStartWith('https://app.test/auth/sso/complete')
        ->and(acsQuery($location, 'code'))->not->toBeEmpty()
        ->and(UserSsoIdentity::where('user_id', $user->id)->where('provider', 'saml')->exists())->toBeTrue();
});

test('an unsigned assertion is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    $relay = samlRelay('_req-1');

    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1', 'sign' => false]), $relay);

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and(acsQuery($location, 'error'))->not->toBeNull();
});

test('an assertion signed by the wrong key is refused', function () {
    // A perfectly well-formed, correctly signed document — signed by someone
    // who is not the configured IdP. This is the case a fingerprint check or a
    // "does it have a Signature element" check would wave through.
    User::factory()->create(['email' => 'ada@example.com']);
    $relay = samlRelay('_req-1');

    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-1', 'signWith' => otherKeypair()]),
        $relay
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('a tampered assertion is refused even though it is signed', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    User::factory()->create(['email' => 'victim@example.com']);
    $relay = samlRelay('_req-1');

    $signed = base64_decode(samlResponse(['inResponseTo' => '_req-1']));
    // Swap the address AFTER signing — the digest no longer matches.
    $tampered = str_replace('ada@example.com', 'victim@example.com', $signed);

    $location = postAssertion(base64_encode($tampered), $relay);

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and(UserSsoIdentity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Replay and request binding
// ---------------------------------------------------------------------------

test('an assertion cannot be replayed', function () {
    // The toolkit does not track this for you. Without it a signed assertion is
    // a bearer credential for anyone holding a copy until NotOnOrAfter passes.
    User::factory()->create(['email' => 'ada@example.com']);

    $assertionId = '_a'.bin2hex(random_bytes(16));
    $response    = samlResponse(['inResponseTo' => '_req-1', 'assertionId' => $assertionId]);

    $first = postAssertion($response, samlRelay('_req-1'));
    expect(acsQuery($first, 'code'))->not->toBeEmpty();

    // A fresh RelayState, so it is the ASSERTION id doing the refusing rather
    // than the single-use request binding.
    $second = postAssertion($response, samlRelay('_req-1'));
    expect(acsQuery($second, 'code'))->toBeNull();
});

test('an assertion answering a different request is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    // RelayState says this answers _req-A; the assertion says _req-B.
    $location = postAssertion(samlResponse(['inResponseTo' => '_req-B']), samlRelay('_req-A'));

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('an unsolicited assertion is refused by default', function () {
    // No RelayState of ours means we never asked for this — the login-CSRF
    // shape, where an attacker posts an assertion for their own account into
    // the victim's browser.
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(samlResponse(), null);

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('IdP-initiated sign-in works once a project opts in', function () {
    config(['auth.saml.security.allow_idp_initiated' => true]);
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(samlResponse(), null);

    expect(acsQuery($location, 'code'))->not->toBeEmpty();
});

test('a RelayState that was never issued is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1']), 'never-issued-relay');

    expect(acsQuery($location, 'code'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Conditions
// ---------------------------------------------------------------------------

test('an expired assertion is refused', function () {
    // An hour stale, not a few seconds: php-saml allows three minutes of clock
    // drift either side (Constants::ALLOWED_CLOCK_DRIFT), deliberately, because
    // an IdP and an SP with slightly different clocks is the normal case rather
    // than an attack. A test expiring the assertion by 30 seconds asserts the
    // opposite of what it looks like — that the drift allowance works.
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-1', 'notOnOrAfter' => time() - 3600, 'notBefore' => time() - 7200]),
        samlRelay('_req-1')
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('an assertion addressed to a different audience is refused', function () {
    // Otherwise an assertion minted for a DIFFERENT service protected by the
    // same IdP would sign someone in here.
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-1', 'audience' => 'https://someone-else.test/metadata']),
        samlRelay('_req-1')
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('an assertion from a different issuer is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-1', 'issuer' => 'https://evil.test/metadata']),
        samlRelay('_req-1')
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Attribute handling
// ---------------------------------------------------------------------------

test('the email is read from whichever claim the IdP happens to send', function () {
    // AD FS and Azure emit WS-Federation claim URIs; anything LDAP-backed emits
    // urn:oid values. Both are tried without configuration.
    $user  = User::factory()->create(['email' => 'ada@example.com']);
    $relay = samlRelay('_req-1');

    $location = postAssertion(samlResponse([
        'inResponseTo' => '_req-1',
        'nameId'       => 'opaque-guid-not-an-email',
        'attributes'   => [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ada@example.com'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'    => ['Ada'],
        ],
    ]), $relay);

    expect(acsQuery($location, 'code'))->not->toBeEmpty()
        ->and(UserSsoIdentity::where('user_id', $user->id)->exists())->toBeTrue();
});

test('a configured attribute name wins over the built-in candidates', function () {
    config(['auth.saml.attributes.email' => 'urn:acme:workEmail']);
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $location = postAssertion(samlResponse([
        'inResponseTo' => '_req-1',
        'nameId'       => 'opaque',
        'attributes'   => ['urn:acme:workEmail' => ['ada@example.com']],
    ]), samlRelay('_req-1'));

    expect(acsQuery($location, 'code'))->not->toBeEmpty();
});

test('an opaque NameID with no email attribute is refused, not turned into an account', function () {
    // Treating an opaque identifier as an address would create a user named
    // after a GUID and then link real people onto it.
    config(['auth.sso.allow_registration' => true]);

    $location = postAssertion(samlResponse([
        'inResponseTo' => '_req-1',
        'nameId'       => 'a8f3c1de-0000-4444-8888-1234567890ab',
        'attributes'   => [],
    ]), samlRelay('_req-1'));

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and(User::count())->toBe(0);
});

test('the subject can be pinned to an attribute when the NameID is transient', function () {
    // A transient NameID changes every session and would fork a new identity
    // row on every sign-in.
    config(['auth.saml.attributes.subject' => 'urn:acme:employeeId']);
    $user = User::factory()->create(['email' => 'ada@example.com']);

    foreach (['_transient-1', '_transient-2'] as $i => $nameId) {
        postAssertion(samlResponse([
            'inResponseTo' => '_req-1',
            'nameId'       => $nameId,
            'attributes'   => ['email' => ['ada@example.com'], 'urn:acme:employeeId' => ['E-4417']],
        ]), samlRelay('_req-1'));
    }

    expect(UserSsoIdentity::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserSsoIdentity::first()->provider_id)->toBe('E-4417');
});

// ---------------------------------------------------------------------------
// Shared account rules and the handoff
// ---------------------------------------------------------------------------

test('the handoff code redeems at the same endpoint OIDC uses', function () {
    $user     = User::factory()->create(['email' => 'ada@example.com']);
    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1']), samlRelay('_req-1'));
    $code     = acsQuery($location, 'code');

    $token = $this->postJson('/api/v1/auth/sso/exchange', ['code' => $code])
        ->assertOk()
        ->json('access_token');

    expect($token)->not->toBeEmpty();

    // Single-use, exactly as for OIDC.
    $this->postJson('/api/v1/auth/sso/exchange', ['code' => $code])->assertStatus(422);
});

test('an unknown email is refused while registration is off', function () {
    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-1', 'nameId' => 'stranger@example.com', 'attributes' => ['email' => ['stranger@example.com']]]),
        samlRelay('_req-1')
    );

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('the domain allow-list applies to SAML linking too', function () {
    // The IdP is trusted for its own users, not for every address it cares to
    // name. Federating with a partner's IdP means this is the only control.
    config(['auth.sso.allowed_domains' => 'company.com']);
    User::factory()->create(['email' => 'victim@example.com']);

    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1']), samlRelay('_req-1'));

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and(UserSsoIdentity::count())->toBe(0);
});

test('a signed assertion counts as a verified email, so registration can proceed', function () {
    // The one place the two protocols legitimately differ: a social provider
    // needs an email_verified claim, whereas the project pasted THIS IdP's
    // certificate into its own config and the IdP is the system of record.
    config(['auth.sso.allow_registration' => true]);

    $location = postAssertion(samlResponse([
        'inResponseTo' => '_req-1',
        'nameId'       => 'newbie@example.com',
        'attributes'   => ['email' => ['newbie@example.com'], 'givenName' => ['New'], 'sn' => ['Bie']],
    ]), samlRelay('_req-1'));

    expect(acsQuery($location, 'code'))->not->toBeEmpty();

    $user = User::where('email', 'newbie@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('New')
        ->and($user->last_name)->toBe('Bie')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('RelayState is never used as a redirect target', function () {
    // The classic SAML open redirect. RelayState is attacker-controlled and is
    // used here ONLY as a cache key; the landing page comes from config.
    User::factory()->create(['email' => 'ada@example.com']);

    $relay = 'https://evil.test/steal';
    Cache::put('saml:request:'.hash('sha256', $relay), ['request_id' => '_req-1', 'ip' => '127.0.0.1'], 600);

    $location = postAssertion(samlResponse(['inResponseTo' => '_req-1']), $relay);

    expect($location)->toStartWith('https://app.test/auth/sso/complete')
        ->and($location)->not->toContain('evil.test');
});

// ---------------------------------------------------------------------------
// Single Logout
//
// SLO is HTTP-Redirect binding: the message is deflated, base64'd and carried
// in the query string, and the signature covers the URL-ENCODED query string
// rather than the XML. So these fixtures sign exactly what a real IdP signs.
// ---------------------------------------------------------------------------

/** Deflate + base64, the redirect binding's wire format. */
function deflateMessage(string $xml): string
{
    return base64_encode(gzdeflate($xml));
}

/**
 * Sign a redirect-binding query string the way an IdP does.
 *
 * The signature covers `SAMLRequest=..&RelayState=..&SigAlg=..` in that exact
 * order, each value URL-encoded, and NOT the XML — which is why the endpoint
 * has to hand php-saml the raw QUERY_STRING rather than a re-encoding of the
 * parsed parameters.
 */
function signedSloQuery(string $param, string $message, ?string $relay, ?array $keypair = null): string
{
    [$key] = $keypair ?? testIdpKeypair();

    $sigAlg = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    $parts = [$param.'='.urlencode($message)];

    if ($relay !== null) {
        $parts[] = 'RelayState='.urlencode($relay);
    }
    $parts[] = 'SigAlg='.urlencode($sigAlg);

    $signed = implode('&', $parts);

    openssl_sign($signed, $signature, $key, OPENSSL_ALGO_SHA256);

    return $signed.'&Signature='.urlencode(base64_encode($signature));
}

function logoutRequestXml(string $nameId = 'ada@example.com', ?string $issuer = null, ?string $destination = null): string
{
    $issuer      = $issuer ?? 'https://idp.test/metadata';
    $destination = $destination ?? url(SamlSettings::SLS_PATH);

    return '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
        .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
        .'ID="_lr'.bin2hex(random_bytes(16)).'" Version="2.0" '
        .'IssueInstant="'.gmdate('Y-m-d\TH:i:s\Z').'" '
        .'Destination="'.htmlspecialchars($destination, ENT_QUOTES).'">'
        .'<saml:Issuer>'.htmlspecialchars($issuer, ENT_QUOTES).'</saml:Issuer>'
        .'<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'
        .htmlspecialchars($nameId, ENT_QUOTES).'</saml:NameID>'
        .'</samlp:LogoutRequest>';
}

/** A signed-in SAML user with a live token and a recorded IdP session. */
function samlSignedInUser(string $email = 'ada@example.com', string $nameId = 'ada@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);

    UserSsoIdentity::create([
        'user_id'        => $user->id,
        'provider'       => SamlSettings::PROVIDER,
        'provider_id'    => $nameId,
        'email'          => $email,
        'name_id'        => $nameId,
        'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        'session_index'  => '_session-1',
    ]);

    $user->createToken('test')->plainTextToken;

    return $user;
}

test('an IdP-initiated LogoutRequest revokes every token that user holds', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();
    $user->createToken('second-device');

    expect($user->tokens()->count())->toBe(2);

    $query = signedSloQuery('SAMLRequest', deflateMessage(logoutRequestXml()), null);

    $this->get('/api/v1/auth/saml/sls?'.$query)->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

test('the LogoutResponse goes back to the IdP, not to the app', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    samlSignedInUser();

    $query    = signedSloQuery('SAMLRequest', deflateMessage(logoutRequestXml()), null);
    $location = $this->get('/api/v1/auth/saml/sls?'.$query)->headers->get('Location');

    expect($location)->toStartWith('https://idp.test/slo')
        ->and($location)->toContain('SAMLResponse=');
});

test('an UNSIGNED LogoutRequest is refused — otherwise anyone can log anyone out', function () {
    // The endpoint is unauthenticated by necessity: the IdP sends a top-level
    // browser navigation with no cookie of ours on it. That makes the message
    // signature the only authentication in play, and php-saml only demands one
    // when wantMessagesSigned is set — which is why SamlSettings::forSlo()
    // forces it rather than leaving it to config.
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();

    $unsigned = 'SAMLRequest='.urlencode(deflateMessage(logoutRequestXml()));

    $this->get('/api/v1/auth/saml/sls?'.$unsigned);

    expect($user->tokens()->count())->toBe(1);
});

test('a LogoutRequest signed by the WRONG key is refused', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();

    $query = signedSloQuery('SAMLRequest', deflateMessage(logoutRequestXml()), null, otherKeypair());

    $this->get('/api/v1/auth/saml/sls?'.$query);

    expect($user->tokens()->count())->toBe(1);
});

test('a LogoutRequest from the wrong issuer is refused', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();

    $xml   = logoutRequestXml(issuer: 'https://evil.test/metadata');
    $query = signedSloQuery('SAMLRequest', deflateMessage($xml), null);

    $this->get('/api/v1/auth/saml/sls?'.$query);

    expect($user->tokens()->count())->toBe(1);
});

test('a logout for an unknown subject still answers Success', function () {
    // Not an error: the IdP is entitled to end a session we never had. Refusing
    // would also turn the endpoint into an account-existence oracle for anyone
    // who can get a signed request minted.
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();

    $query    = signedSloQuery('SAMLRequest', deflateMessage(logoutRequestXml(nameId: 'nobody@example.com')), null);
    $location = $this->get('/api/v1/auth/saml/sls?'.$query)->headers->get('Location');

    expect($location)->toStartWith('https://idp.test/slo')
        ->and($user->tokens()->count())->toBe(1);
});

test('a subject is matched on provider_id when SAML_ATTR_SUBJECT moved name_id', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = User::factory()->create(['email' => 'ada@example.com']);
    UserSsoIdentity::create([
        'user_id'     => $user->id,
        'provider'    => SamlSettings::PROVIDER,
        'provider_id' => 'ada@example.com',
        'email'       => 'ada@example.com',
    ]);
    $user->createToken('test');

    $query = signedSloQuery('SAMLRequest', deflateMessage(logoutRequestXml()), null);

    $this->get('/api/v1/auth/saml/sls?'.$query);

    expect($user->tokens()->count())->toBe(0);
});

test('SP-initiated logout revokes locally BEFORE handing back the IdP URL', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = samlSignedInUser();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/saml/logout');

    $response->assertOk();

    expect($user->tokens()->count())->toBe(0)
        ->and($response->json('url'))->toStartWith('https://idp.test/slo');
});

test('SP-initiated logout still logs out locally when the IdP has no SLO endpoint', function () {
    // url: null is a complete answer, not a failure — the client logs out
    // locally either way. Ending the IdP session is the bonus.
    config(['auth.saml.idp.slo_url' => null]);
    $user = samlSignedInUser();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/saml/logout');

    $response->assertOk()->assertJson(['url' => null]);

    expect($user->tokens()->count())->toBe(0);
});

test('SP-initiated logout for a password user is a local logout, not an error', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);
    $user = User::factory()->create();
    $user->createToken('test');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/saml/logout');

    $response->assertOk()->assertJson(['url' => null]);

    expect($user->tokens()->count())->toBe(0);
});

test('the SP-initiated logout endpoint requires a session', function () {
    $this->postJson('/api/v1/auth/saml/logout')->assertUnauthorized();
});

test('a signed LogoutResponse ends the round trip at the app', function () {
    config(['auth.saml.idp.slo_url' => 'https://idp.test/slo']);

    $xml = '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
        .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
        .'ID="_lrs'.bin2hex(random_bytes(16)).'" Version="2.0" '
        .'IssueInstant="'.gmdate('Y-m-d\TH:i:s\Z').'" '
        .'Destination="'.htmlspecialchars(url(SamlSettings::SLS_PATH), ENT_QUOTES).'">'
        .'<saml:Issuer>https://idp.test/metadata</saml:Issuer>'
        .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
        .'</samlp:LogoutResponse>';

    $query    = signedSloQuery('SAMLResponse', deflateMessage($xml), null);
    $location = $this->get('/api/v1/auth/saml/sls?'.$query)->headers->get('Location');

    expect($location)->toStartWith('https://app.test/auth/sso/complete');
});

test('a sign-in records the NameID and SessionIndex that logout needs', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $relay = samlRelay('_req-slo');
    postAssertion(samlResponse(['inResponseTo' => '_req-slo']), $relay);

    $identity = UserSsoIdentity::where('provider', SamlSettings::PROVIDER)->first();

    expect($identity)->not->toBeNull()
        ->and($identity->name_id)->toBe('ada@example.com')
        ->and($identity->session_index)->toStartWith('_s');
});

// ---------------------------------------------------------------------------
// Encrypted assertions
// ---------------------------------------------------------------------------

/**
 * Encrypt an assertion for the SP, the way an IdP does.
 *
 * AES-128-CBC for the content with a per-message session key, and that session
 * key wrapped with the SP's PUBLIC key using RSA-OAEP. Which is worth saying out
 * loud, because it is the reason the tests below exist: the SP's certificate is
 * published in its own metadata, so being able to encrypt for it proves nothing
 * whatsoever about who you are.
 */
function encryptAssertionXml(string $assertionXml, string $spCertBody): string
{
    $dom = new DOMDocument;
    $dom->loadXML($assertionXml);

    $sessionKey = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
    $sessionKey->generateSessionKey();

    $publicKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, ['type' => 'public']);
    $publicKey->loadKey(Utils::formatCert($spCertBody, true), false, true);

    $enc = new XMLSecEnc;
    $enc->setNode($dom->documentElement);
    $enc->type = XMLSecEnc::Element;
    $enc->encryptKey($publicKey, $sessionKey);

    $encrypted = $enc->encryptNode($sessionKey);

    return $encrypted->ownerDocument->saveXML($encrypted);
}

/** Give the SP a keypair of its own, which only encryption needs. */
function configureSpKeypair(): array
{
    [$key, $cert] = otherKeypair();

    config([
        'auth.saml.sp.x509'        => $cert,
        'auth.saml.sp.private_key' => $key,
    ]);

    return [$key, $cert];
}

test('an encrypted assertion is decrypted and signs the user in', function () {
    [, $spCert] = configureSpKeypair();
    config(['auth.saml.security.want_assertions_encrypted' => true]);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-enc');
    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-enc', 'encryptFor' => $spCert]),
        $relay
    );

    expect(acsQuery($location, 'code'))->not->toBeNull();
});

test('the assertion really is encrypted on the wire — no plaintext email crosses the browser', function () {
    // Proves the fixture rather than trusting it, and states the actual benefit:
    // the assertion travels through the USER'S BROWSER as a form POST. Without
    // encryption every attribute in it — email, names, group memberships — is
    // readable by anything with access to that page. This is the one property
    // encryption buys, so it is asserted directly rather than inferred from a
    // sign-in succeeding.
    [, $spCert] = configureSpKeypair();

    $payload = base64_decode(samlResponse(['encryptFor' => $spCert]));

    expect($payload)->toContain('<saml:EncryptedAssertion')
        ->and($payload)->toContain('EncryptedData')
        ->and($payload)->not->toContain('ada@example.com')
        ->and($payload)->not->toContain('<saml:Attribute');
});

test('encryption is not authentication — a wrongly-signed assertion is still refused', function () {
    // The property that makes the rest of this safe. The SP certificate is
    // PUBLISHED in the SP metadata, so anyone at all can encrypt an assertion
    // for us. If decryption implied trust, publishing that certificate would be
    // handing out the ability to mint identities. The signature inside the
    // envelope is what authenticates; encryption only keeps the browser from
    // reading it in transit.
    [, $spCert] = configureSpKeypair();
    config(['auth.saml.security.want_assertions_encrypted' => true]);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-enc');
    $location = postAssertion(
        samlResponse([
            'inResponseTo' => '_req-enc',
            'encryptFor'   => $spCert,
            'signWith'     => otherKeypair(),
        ]),
        $relay
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('an unsigned assertion is refused even when correctly encrypted', function () {
    [, $spCert] = configureSpKeypair();
    config(['auth.saml.security.want_assertions_encrypted' => true]);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-enc');
    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-enc', 'encryptFor' => $spCert, 'sign' => false]),
        $relay
    );

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('with encryption required, a plaintext assertion is refused', function () {
    configureSpKeypair();
    config(['auth.saml.security.want_assertions_encrypted' => true]);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-plain');
    $location = postAssertion(samlResponse(['inResponseTo' => '_req-plain']), $relay);

    expect(acsQuery($location, 'code'))->toBeNull();
});

test('an encrypted assertion still works when encryption is not REQUIRED', function () {
    // want_assertions_encrypted is a floor, not a switch: an IdP that encrypts
    // when we did not demand it is fine, and the SP keypair alone is what makes
    // decryption possible.
    [, $spCert] = configureSpKeypair();
    config(['auth.saml.security.want_assertions_encrypted' => false]);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-enc');
    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-enc', 'encryptFor' => $spCert]),
        $relay
    );

    expect(acsQuery($location, 'code'))->not->toBeNull();
});

test('an encrypted assertion with no SP private key configured is refused, not fatal', function () {
    // The misconfiguration that would otherwise 500: encryption turned on, or an
    // IdP that encrypts by default, with no keypair to decrypt with.
    [, $spCert] = otherKeypair();
    config(['auth.saml.sp.x509' => $spCert, 'auth.saml.sp.private_key' => '']);
    User::factory()->create(['email' => 'ada@example.com']);

    $relay    = samlRelay('_req-enc');
    $location = postAssertion(
        samlResponse(['inResponseTo' => '_req-enc', 'encryptFor' => $spCert]),
        $relay
    );

    expect(acsQuery($location, 'code'))->toBeNull()
        ->and($location)->toStartWith('https://app.test/auth/sso/complete');
});
