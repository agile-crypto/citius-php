# citius-php

PHP gRPC client for the Citius crypto API (`caas.crypto.v1`):

- client stubs generated from the protobuf definitions of [citius/api](https://github.ibm.com/citius/api), included as a git submodule in `proto/api`;
- connections built from the YAML configuration of the [Citius Go SDK](https://github.com/agile-crypto/citius-go-sdk), with TLS, mutual TLS and bearer token authentication.

Only the services needed by the Nextcloud WebAuthn adapter are generated:

| Service | Client class | Used for |
|---|---|---|
| `CryptoService` | `Citius\Grpc\Crypto\V1\CryptoServiceClient` | Signature verification |
| `KeyManagementService` | `Citius\Grpc\Crypto\V1\KeyManagementServiceClient` | Public key import and deletion |
| `CryptoPolicyService` | `Citius\Grpc\Crypto\V1\CryptoPolicyServiceClient` | Allowed algorithms |

Generation works per service (proto file), not per RPC, so each client exposes all RPCs of its service.

## Layout

| Path | Content |
|---|---|
| `proto/api/` | Git submodule `citius/api`, tracking `main` (the `.proto` sources) |
| `buf.gen.php.yaml` | buf generation template for PHP |
| `gen/Citius/Grpc/` | Generated code (do not edit), autoloaded as `Citius\Grpc\` |
| `src/` | Configuration, connection and authentication, autoloaded as `Citius\Client\` |
| `tests/` | Unit and integration tests |

## Clone

The submodule URL uses SSH, so you need read access to `citius/api` and an SSH key on github.ibm.com.

```bash
git clone --recurse-submodules  git@github.ibm.com:citius/citius-php.git
```

In an existing clone without the submodule checked out:

```bash
git submodule update --init
```

## Requirements

- Generation: [buf](https://buf.build/docs/installation) (`brew install bufbuild/buf/buf`) and network access to `buf.build`, which hosts the remote plugins. Unauthenticated requests are rate limited (`resource_exhausted: too many requests`). Log in with `buf registry login`, or set `BUF_TOKEN` where no login is stored, for example in a container.
- Runtime: PHP >= 8.3 with the `grpc` and `openssl` extensions. The `protobuf` extension is optional but recommended.

## Generate the PHP code

From the repository root:

```bash
buf generate proto/api/proto --template buf.gen.php.yaml \
  --path proto/api/proto/services/crypto_service.proto \
  --path proto/api/proto/services/key_management_service.proto \
  --path proto/api/proto/services/crypto_policy_service.proto
```

The same command is available as a Composer script, when Composer and buf are installed in the same environment:

```bash
composer run generate
```

`clean: true` in the template deletes `gen/` before each run, so removed messages do not linger. Commit `gen/` so consumers do not need buf.

To generate another service, add its file with an extra `--path` in both the command above and the `generate` script in `composer.json`. Its message and type dependencies are included automatically.

## Update the API version

Move the submodule to the latest commit of `main`, regenerate, and commit the submodule pointer together with `gen/`, so each version of `gen/` matches the API commit it was generated from:

```bash
git submodule update --remote proto/api
composer run generate
git add proto/api gen
git commit -m "update citius/api to $(git -C proto/api describe --tags --always)"
```

To use a specific tag instead, check it out in the submodule before generating:

```bash
git -C proto/api fetch --tags
git -C proto/api checkout v0.2.0
```

## What the template does

| Setting | Reason |
|---|---|
| `exclude_types: buf.validate.*` | The protos import protovalidate (`buf/validate/validate.proto`), which uses proto2 closed enums that the PHP plugin cannot generate (`Can't generate PHP code for closed enum buf.validate.Ignore`). Excluding the option types strips the validation annotations and the import. They are server-side rules the client does not need. |
| `exclude_types: google.api.http` | Strips the REST gateway annotations, so no `Google\Api\*` classes are generated. They are unused by a gRPC client and would collide with `google/common-protos`. |
| `managed.override` | Puts messages and clients in `Citius\Grpc\Crypto\V1` and metadata in `Citius\Grpc\GPBMetadata\...`, instead of generic global names such as `GPBMetadata\Types\Common`. |
| Pinned plugin versions | `protocolbuffers/php:v33.2` matches the `google/protobuf` 4.33 runtime. Generated code must not be newer than the runtime, so bump the plugin and the `google/protobuf` constraint in `composer.json` together. `grpc/php:v1.83.1` matches the `grpc` extension. |

## Configuration

The YAML schema is the one of the Citius Go SDK, so the same file configures Go and PHP clients (see [Differences with the Go SDK](#differences-with-the-go-sdk)):

```yaml
default:                                 # shared by all services
  endpoint: "citius.example.com:443"
  tls:
    ca_cert: "/etc/citius/ca.pem"        # empty: system root CAs
    client_cert: "/etc/citius/client.pem" # client_cert + client_key: mutual TLS
    client_key: "/etc/citius/client.key"
    server_name: "citius.example.com"    # required unless insecure
    min_version: "1.2"                   # "" or "1.2"
    insecure: false                      # true: plaintext, no TLS (development only)
  auth:
    enabled: true
    static_token: ""                     # exactly one of static_token and token_source
    token_source: "/run/secrets/citius-token"
  timeout: "30s"                         # Go duration, default deadline of each call

crypto: {}                               # a service is usable only if its section is present
key_management:
  endpoint: "km.example.com:443"         # fields override the default; tls/auth blocks replace it whole
```

Services: `crypto`, `key_management`, `crypto_policy`, `discovery`, `key_establishment`, `provider`, `streaming_crypto`. Relative file paths resolve against the current working directory, as in Go.

## Usage

`Connector::connect()` (Go: `dial.Connect`) builds the `(hostname, opts, channel)` triplet taken by the generated clients:

```php
use Citius\Client\Config\Config;
use Citius\Client\Config\Service;
use Citius\Client\Dial\Connector;
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Citius\Grpc\Crypto\V1\VerifyRequest;

$connection = Connector::connect(Config::load('/etc/citius/client.yaml')->resolve(Service::Crypto));
// Shortcut: Connector::connectFromFile('/etc/citius/client.yaml', Service::Crypto)

$client = new CryptoServiceClient($connection->hostname, $connection->opts, $connection->channel);
// or: $client = $connection->createClient(CryptoServiceClient::class);

$request = (new VerifyRequest())
	->setKeyName('nextcloud/webauthn/...')
	->setInput($data)
	->setSignature($signature);

[$response, $status] = $client->Verify($request)->wait();
if ($status->code !== \Grpc\STATUS_OK) {
	throw new \RuntimeException($status->details);
}
$valid = $response->getValid();
```

- The configuration can also come from a string (`Config::fromYaml()`) or a decoded array (`Config::fromArray()`), e.g. stored in an application's settings.
- Authentication adds `authorization: Bearer <token>` to every call through a gRPC interceptor. For other token sources (OAuth2, workload identity), implement `Citius\Client\Auth\TokenProvider` and wrap the channel with `Grpc\Interceptor::intercept($channel, new AuthInterceptor($provider))`.
- The configured timeout applies to unary calls that do not pass their own `['timeout' => <microseconds>]` call option.
- `connect()` validates the configuration and the certificate files, and throws `Citius\Client\Exception\ConfigException` on errors. It does no network I/O: the channel connects on the first call, and connection failures come back as call status `UNAVAILABLE`.

| Go SDK | PHP |
|---|---|
| `config.Load`, `Config.Resolve`, `ResolveConfigFromFile` | `Config::load()`, `Config::resolve()`, `Config::resolveFromFile()` |
| `ServiceConfig.ResolveTokenProvider` | `ServiceConfig::resolveTokenProvider()` |
| `dial.Connect` | `Connector::connect()` |
| `auth.TokenProvider`, `StaticToken`, `TokenSource` | `Citius\Client\Auth\TokenProvider`, `StaticToken`, `TokenSource` |
| `auth.UnaryInterceptor` | `Citius\Client\Auth\AuthInterceptor` (unary and streaming calls) |

### Differences with the Go SDK

| Case | Go | PHP |
|---|---|---|
| `min_version: "1.3"` | Enforced | `ConfigException`: the PHP gRPC extension cannot set the TLS version. gRPC enforces TLS >= 1.2 and negotiates 1.3 when the server supports it. |
| Only one of `client_cert` / `client_key` | Silently no client certificate | `ConfigException` |
| Token file with a trailing newline | Newline sent in the header | Surrounding whitespace trimmed |
| Negative `timeout` | Accepted: every call fails immediately | `ConfigException` |
| Wrong value types, e.g. `insecure: "yes"` | YAML decoding error | `ConfigException` naming the key |

## Tests

```bash
composer install
composer test                # unit tests
composer test:integration    # integration tests
```

| Suite | Test | Needs |
|---|---|---|
| unit | `ConfigTest` | Port of the Go SDK `config_test.go` on the same fixtures (`tests/fixtures/config`) |
| unit | `DurationTest`, `AuthTest`, `ConnectorTest` | Nothing. `ConnectorTest` generates a throwaway PKI |
| integration | `LocalServerTest` | Nothing. Starts local PHP gRPC servers (plaintext, TLS) and checks calls over TLS and mTLS with static and file tokens, server name verification, untrusted servers, missing and wrong tokens, and timeouts |
| integration | `GoMtlsExampleTest` | The Go SDK `examples/mtls` server. Checks interoperability with a Go server that requires client certificates: the PHP gRPC server cannot require them |
| integration | `ReferenceImplementationTest` | A Citius server reference implementation. Equivalent of the Go `examples/reference_implementation/client`: create policy, create key, sign, verify |

Integration tests without their server are skipped.

**Go mTLS example:** start the server from `citius-go-sdk/examples/mtls` (`go run server/main.go`, listens on `localhost:50051`), then:

```bash
CITIUS_MTLS_EXAMPLE_RESOURCES=/path/to/citius-go-sdk/examples/mtls/resources \
CITIUS_MTLS_EXAMPLE_ADDR=localhost:50051 \
vendor/bin/phpunit tests/Integration/GoMtlsExampleTest.php
```

From a container, use `host.containers.internal:50051` (Podman) or `host.docker.internal:50051` (Docker) as the address.

**Reference implementation:** same environment variables as the Go example, whose flags become variables (`-tls` → `CITIUS_TLS=1`, `-auth` → `CITIUS_AUTH=1`, `-role` → `CITIUS_ROLE`, `-auth-token-file` → `CITIUS_AUTH_TOKEN_FILE`):

```bash
set -a; source path/to/server/bootstrap/zitadel/citius-zitadel.env; set +a
CITIUS_TLS=1 CITIUS_AUTH=1 CITIUS_ROLE=ADMIN vendor/bin/phpunit tests/Integration/ReferenceImplementationTest.php
```
