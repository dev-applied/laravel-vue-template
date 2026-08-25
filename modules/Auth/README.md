# Auth

Credential flows for the template: email + password login issuing Sanctum tokens,
`me` / logout, impersonation, forgot + reset password, and the MCP server
endpoint. Extracted from the kernel — a template checkout has no auth routes
until this module is added.

## Install

```sh
php artisan module:add Auth
php artisan migrate
php artisan route:clear && composer typescript
```

Two independent options, `auth` and `sso`. Both default to the smaller choice.

## Endpoints

| Method | Path | Notes |
|---|---|---|
| POST | `/api/v1/auth` | Login. Returns `{access_token, token_type}`. `throttle:6,1`. |
| GET | `/api/v1/auth` | The signed-in user, or `{user: null}`. |
| DELETE | `/api/v1/auth` | Logout — revokes the current token. |
| POST | `/api/v1/auth/impersonate` | |
| DELETE | `/api/v1/auth/stop-impersonating` | |
| POST | `/api/v1/forgot-password` | `throttle:6,1`. |
| POST | `/api/v1/forgot-password/reset` | |

Paths are unchanged from the pre-module kernel, so the frontend user store and
Capacitor builds need no edits.

## The `auth` option — OAuth for MCP clients

| Choice | What it adds |
|---|---|
| `sanctum` (default) | Bearer personal access tokens. The `/mcp` endpoint is `auth:sanctum`. |
| `sanctum+oauth` | Adds Passport OAuth 2.1 for MCP clients that speak OAuth, and widens the endpoint guard to `auth:sanctum,api`. |

Passport's `/oauth/*` routes are suppressed unless the layer is on — a
Sanctum-only project should not advertise endpoints it does not serve. That
suppression happens in `register()`, not `boot()`: Passport reads the flag during
its own boot, after every provider's `register()` has run.

The password-reset mail is this module's. The kernel User model keeps the
framework's `CanResetPassword` trait untouched; the provider swaps the
notification's mailable.

## Single sign-on (the `sso` option)

A **separate axis** from `auth`, and the opposite direction: `auth=sanctum+oauth`
makes this app an OAuth **server** for MCP clients, while `sso` makes it a
**client** of somebody else's identity provider. A project can want either, both
or neither, so they are not choices on one setting.

Four values, because a project may need social sign-in, an enterprise IdP, or both:

| `sso=` | What you get | Requires |
|---|---|---|
| `none` | Email + password only | — |
| `oidc` | Google / Microsoft / Okta via Socialite | `laravel/socialite` |
| `saml` | SAML 2.0 against one enterprise IdP | `onelogin/php-saml` |
| `oidc+saml` | Both | both |

```sh
php artisan module:add Auth --option sso=oidc
php artisan migrate     # user_sso_identities
```

Both protocols end the same way, so the app has ONE completion path regardless of
which is installed: a single-use handoff code, redeemed at
`POST /api/v1/auth/sso/exchange`, landing on the `SsoCompletePage` route.

### How a sign-in actually finishes

This is the part worth reading before changing anything.

The callback / ACS **never returns a token**. It redirects the browser to an
app-controlled URL carrying a single-use code that expires in two minutes, and
the app exchanges that code for a Sanctum token over a back channel.

It did return a token once, in a JSON body, and that was wrong twice over. A
provider redirect is a top-level navigation, so the browser simply displayed the
JSON — there was no frontend handler and sign-in stopped there. And in a
Capacitor build the round trip completes in a SYSTEM browser that shares no
storage with the app, so the app could not read the token even in principle,
while the token sat in a page in the user's real Chrome profile.

### OIDC endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/auth/sso/providers` | Everything to offer, BOTH protocols. Unauthenticated. `throttle:60,1`. |
| GET | `/api/v1/auth/sso/{provider}/start` | Returns `{url}`; the client navigates to it. |
| GET | `/api/v1/auth/sso/{provider}/callback` | Redirects to the app with a handoff code. |
| POST | `/api/v1/auth/sso/exchange` | Redeems the code for a token. Shared with SAML. |

```
SSO_ENABLED=true
SSO_PROVIDERS=google,microsoft
SSO_ALLOW_REGISTRATION=false
SSO_ALLOWED_DOMAINS=
SSO_PKCE=true
```

A provider is only offered once it has a `client_id` and `client_secret`. Listing
one without them renders no button and returns 503 naming the problem, rather
than a `DriverMissingConfigurationException` stack trace.

### SAML endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/auth/saml/metadata` | SP metadata for the IdP administrator. Public. |
| GET | `/api/v1/auth/saml/start` | Returns `{url}` — an AuthnRequest redirect. |
| POST | `/api/v1/auth/saml/acs` | Where the IdP POSTs the assertion. Redirects with a handoff code. |

```
SAML_ENABLED=true
SAML_LABEL="Acme Directory"
SAML_IDP_ENTITY_ID=https://sts.acme.com/adfs/services/trust
SAML_IDP_SSO_URL=https://sts.acme.com/adfs/ls/
SAML_IDP_X509=MIIC...              # signing cert, PEM headers optional
SAML_ALLOW_IDP_INITIATED=false
```

`metadata` works **before** the IdP is configured, on purpose: the administrator
at the other end needs it in order to give you the values above.

Attribute names are the part of SAML that varies most, so a candidate list is
tried without configuration — WS-Federation claim URIs (AD FS, Azure),
`urn:oid:` values (anything LDAP-backed), and bare names. Set `SAML_ATTR_EMAIL`
when yours sends something else, and `SAML_ATTR_SUBJECT` when the IdP issues a
**transient** NameID, which changes per session and would otherwise fork a new
identity row on every sign-in.

#### Single Logout

Two endpoints, and they are not symmetrical.

`GET|POST /api/v1/auth/saml/sls` — unauthenticated, and takes both message kinds:

| Arrives with | Means | We do |
| --- | --- | --- |
| `SAMLRequest` | The IdP is ending a session | Revoke **every** token that subject holds, answer with a LogoutResponse at the IdP's SLO URL |
| `SAMLResponse` | The IdP is answering a logout we started | Nothing further to revoke; return the browser to the app |

`POST /api/v1/auth/saml/logout` — `auth:sanctum`, the SP-initiated half. Revokes
local tokens **first**, then returns `{url}` for the browser to follow. `{url: null}`
is a complete answer, not an error: the user signed in with a password, or the IdP
advertises no SLO endpoint. Either way they are logged out here.

The module's `resources/ts/plugin.ts` registers that call as a kernel
`onBeforeLogout` handler, so the app's ordinary sign-out drives it — a handler
runs while the token is still valid, and a returned URL is followed after local
state is cleared. Nothing to wire per project. The plugin is dropped from the
`none` and `oidc` variants, where the route does not exist.

Three things worth knowing before changing any of it:

- **`wantMessagesSigned` is forced on for SLO** (`SamlSettings::forSlo()`) and is
  not configurable. It is optional for sign-in and rightly so — the assertion
  carries its own signature and most IdPs do not sign the Response wrapper. A
  LogoutRequest has no assertion. php-saml only demands a signature when this
  flag is set (`LogoutRequest.php:428` — an absent Signature is simply not
  checked otherwise), so at the default this endpoint would accept an unsigned
  LogoutRequest naming any NameID, from anyone: an unauthenticated
  log-anybody-out.
- **ALL of that user's tokens go, not one.** Sanctum issues a token per device
  and none of them carries an IdP SessionIndex, so there is nothing to be
  selective with — and "log this person out" leaving them signed in on a second
  device is the wrong way to be wrong.
- **The NameID is read from the toolkit's validated XML**, never from the raw
  query parameter, and only inside the post-validation callback. Reading it
  earlier would be the same DoS by a different route.

A note for anyone porting this: SLO is HTTP-Redirect binding, and the signature
covers the URL-encoded **query string** in a fixed parameter order — not the XML.
`$request->getQueryString()` is the wrong source for it: Symfony normalises,
which ksorts the parameters and re-encodes them RFC3986 (`Request.php:679`), so
neither the order nor the bytes survive. The raw `QUERY_STRING` off the server
bag is what the IdP signed. The endpoint also tries both of php-saml's
reconstruction modes, because IdPs genuinely differ on the encoding.

#### Encrypted assertions

Off by default. Turn on with `SAML_WANT_ASSERTIONS_ENCRYPTED=true`, which makes
an unencrypted assertion a refusal rather than a preference.

Encryption needs a keypair the SP owns — the IdP encrypts a per-message session
key with the SP's public key:

```sh
openssl req -x509 -newkey rsa:2048 -keyout sp.key -out sp.crt -days 3650 -nodes -subj "/CN=your-app"
```

`SAML_SP_X509` takes the certificate body, `SAML_SP_PRIVATE_KEY` the key. Both
also feed request signing, so a project doing either needs them. The certificate
is published in the SP metadata; the key never leaves the server.

**What it buys, precisely:** the assertion crosses the user's own browser as a
form POST. Unencrypted, every attribute in it — email, names, group memberships
— is readable by anything with access to that page. Encryption closes that, and
only that.

**What it does NOT buy: any authentication whatsoever.** The SP certificate is
published in the SP metadata, so anyone at all can encrypt an assertion for this
app. If decrypting implied trust, publishing that certificate would be handing
out the ability to mint identities. The signature on the assertion INSIDE the
envelope is what authenticates, and it is still checked — pinned by tests that
encrypt correctly and sign with the wrong key, and that encrypt correctly and do
not sign at all. Both are refused.

The ordering matters and the tests mirror it: the IdP signs the assertion, then
encrypts the signed document. Signing an already-encrypted blob would sign
ciphertext and prove nothing about the statement inside it.

A missing `SAML_SP_PRIVATE_KEY` with an encrypted assertion arriving is a
refusal, not a 500.

### The security properties, and why each is there

Not defaults to relax casually — each one is a specific attack.

**Both protocols**

- **An existing account is only linked on a VERIFIED email.** Otherwise anyone
  who can set an address at the provider inherits the matching account. A
  **missing** claim counts as unverified.
- **Registration is off by default**, and also demands a verified email. With it
  on and that check absent, an identity asserting an unverified `cfo@client.com`
  got a local account bearing that address, stamped as verified.
- **`SSO_ALLOWED_DOMAINS` gates LINKING as well as registration.** It restricts
  which addresses may arrive at all. Set it whenever the IdP is not one you
  operate. Note what it does *not* do — see the issuer pin below.
- **One refusal message, plus a logged reference.** Three specific refusals let
  an unauthenticated caller sort any address into exists / does not exist /
  deactivated, one callback at a time.
- **A deactivated user is refused** before any identity row is written.
- **The return URL is config-derived, never taken from the request.**

**OIDC**

- **The provider is an allow-list.** It arrives as a URL segment, so without the
  list it reaches Socialite's driver factory directly.
- **State is issued by this module**, single-use and bound to its provider. API
  routes have no session and neither does a Capacitor system browser, so
  `->stateless()` is required and that removes Socialite's own CSRF handling.
- **PKCE**, because state alone binds a code to *nothing*: `start` is
  unauthenticated and hands a valid state to anyone who asks. The verifier is
  bound by the provider to the authorization request that produced the code.
- **The issuer can be pinned per provider**, and on a multi-tenant endpoint it
  must be. See below.

#### Pinning the issuer (multi-tenant OIDC)

`SSO_ALLOWED_DOMAINS` cannot catch the attack it looks like it catches. This is
the published nOAuth shape:

1. The attacker creates their own Entra tenant — free, takes minutes.
2. In it, they create a user whose mail is `cfo@yourclient.com` and mark it
   verified. It is their tenant; they control the claim mapping.
3. They sign in to your app through the **same provider name** you trust.

`provider` matches. The email is asserted verified. The domain is in your
allow-list — it is *your* domain, which is exactly why it is listed. Every check
passes and the identity links onto the real CFO's account.

The only thing separating the two identities is which tenant asserted it, and
that lives in a claim. Pin it in `config/auth.php`:

```php
'sso' => [
    'required_claims' => [
        'azure'  => ['tid' => env('SSO_AZURE_TENANT_ID')],
        'google' => ['hd'  => env('SSO_GOOGLE_HOSTED_DOMAIN')],
        'okta'   => ['iss' => env('SSO_OKTA_ISSUER')],
    ],
],
```

Which claim carries it — and whether your Socialite driver surfaces it at all —
is driver-specific. Log `$identity->raw` once against the real provider and pin
what is actually there.

Rules, all fail-closed:

| Situation | Result |
| --- | --- |
| No entry for the provider | No pin. Fine for a single-tenant IdP or one you operate. |
| Claim present and matching | Signs in. Comparison is case-insensitive; a list of values is allowed; dot notation reaches a nested claim. |
| Claim **absent** from the identity | **Refused.** An attacker who can choose the issuer can usually choose which optional claims ride along, so missing must not mean fine. |
| Expected value resolves **empty** | **Refused, for that whole provider.** It means an env var did not resolve, and a pin that silently enforces nothing while reading as configured is worse than none. |
| Claim is an array or object | **Refused.** Pinning a group list is not a tenant pin; say what you mean. |

The pin is checked on **every** path, including an already-linked identity —
adding one revokes what got in before it, which is the usual reason to add one.

**SAML**

- **Assertions must be signed.** `wantAssertionsSigned` is forced on in
  `SamlSettings` and is not configurable. Everything else is worthless without it.
- **The issuer is pinned by construction** — no `required_claims` entry needed.
  php-saml compares the assertion's `<Issuer>` against `SAML_IDP_ENTITY_ID` and
  rejects a mismatch before the assertion is read, so the multi-tenant problem
  above does not arise: a project federates with one IdP whose certificate it
  pasted in itself. (`required_claims` still works here if you want to require a
  specific *attribute* on top — `raw` is the attribute set.)
- **InResponseTo is checked.** An opaque token of ours rides in `RelayState` so
  the ACS can find which AuthnRequest an assertion answers. RelayState is
  attacker-modifiable, so it is used ONLY as a cache key — substituting one
  selects a different expected request id and the cryptographic match then fails.
- **RelayState is never a redirect target.** That is the classic SAML open
  redirect, and it arrives carrying a credential.
- **Assertions cannot be replayed.** The toolkit does not track this for you; a
  signed assertion is otherwise a bearer credential for anyone holding a copy
  until its `NotOnOrAfter` passes.
- **Unsolicited (IdP-initiated) responses are refused by default.** An assertion
  we never asked for is the login-CSRF shape. `SAML_ALLOW_IDP_INITIATED=true`
  enables the Okta/Azure dashboard tile and accepts that trade.
- **A signed assertion counts as a verified email.** The one place the protocols
  legitimately differ: the project pasted *this* IdP's certificate into its own
  config, and the IdP is the system of record for its users. That trust does not
  extend to domains the IdP has no authority over — hence the allow-list above.

### The seam

`Sso\SsoIdentityResolver` maps an identity to a local user, and BOTH protocols go
through it — the account rules are written once. Bind your own in
`AppServiceProvider` for per-tenant config, role mapping from claims, or group
membership. It takes a protocol-neutral `Sso\SsoIdentity`, so overriding it does
not mean importing Socialite.

### Not included

Nothing outstanding for SAML — SLO and encrypted assertions both shipped. What a
project still owns: pinning the issuer for a multi-tenant OIDC provider (see
above; the module supplies the mechanism, only the project knows the value), and
binding its own `SsoIdentityResolver` if account creation needs rules beyond a
verified address in an allowed domain.

### Testing

The SAML suite mints **real signed assertions** — a throwaway self-signed IdP,
genuine RSA signatures — because a mocked toolkit would test none of what
matters. Every negative case is a genuinely hostile document: unsigned, signed by
the wrong key, tampered after signing, replayed, expired, wrong audience, wrong
issuer, answering a different request.

Worth knowing if you extend it: php-saml validates against the XSD, so an
assertion whose `<Signature>` sits in the wrong position is rejected as
*malformed* rather than as unsigned — which would make every negative test pass
for the wrong reason. `Utils::addSign` inserts it as the root's first child (i.e.
before `<Issuer>`), so the helper repositions it, as a real IdP would. It also
wants a PEM-wrapped certificate where the config wants a bare body; hand it the
stripped form and you get an empty `<X509Data>` and the same misleading pass.
