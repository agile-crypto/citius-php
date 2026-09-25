# citius-php

PHP gRPC client stubs for the Citius crypto API (`caas.crypto.v1`), generated from the protobuf definitions of [citius/api](https://github.ibm.com/citius/api), included as a git submodule in `proto/api`.

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
- Runtime: PHP >= 8.3 with the `grpc` extension. The `protobuf` extension is optional but recommended.

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

## Usage

```php
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Citius\Grpc\Crypto\V1\VerifyRequest;
use Grpc\ChannelCredentials;

$client = new CryptoServiceClient('citius:50051', [
	'credentials' => ChannelCredentials::createInsecure(),
]);

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
