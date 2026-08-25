# iot.EraX production delivery

The iot.EraX production deployment is intentionally independent from the
repository's Docker Compose files. Compose is used for local validation and as
a public self-hosting reference. The official runtime is Cloud Run in
`ioterax-prd`, backed by the existing Hub/Foundation PostgreSQL 16 instance in
`foundation-hub-504212` through a private PSC endpoint.

Infrastructure remains owned by Terraform. Application workflows must not
create or change service accounts, networks, ingress, scaling, Cloud Scheduler,
database resources, buckets, secret containers, certificates, DNS, or the
Cloudflare-only edge.

## Release contract

The manual `release` workflow runs only from `master` and accepts an immutable
downstream version such as `1.4.4-ioterax.1`. It:

1. builds native `linux/amd64` and `linux/arm64` images;
2. assembles one multi-platform candidate manifest in a private, non-runtime
   Artifact Registry package;
3. scans both platforms with pinned Trivy 0.74.0 and rejects every High or
   Critical OS or language-package finding, including unfixed findings;
4. generates per-platform CycloneDX SBOMs;
5. copies the approved manifest without changing its digest to the production
   Artifact Registry package and scans it again;
6. publishes GitHub provenance, keyless Cosign signatures, and SBOM
   attestations for the production Artifact Registry digest.

The workflow never publishes `latest` and never accesses Docker Hub or GHCR.
Public image distribution is deferred. Artifact Registry is the sole release
registry and Cloud Run accepts only its reviewed digest.

## Protected production variables

The `deploy-production` workflow consumes only numeric Secret Manager versions;
secret payloads never enter GitHub. Configure these required GitHub Environment
variables:

| Variable | Purpose |
| --- | --- |
| `SHOUTRRR_DB_HOST` | Private PostgreSQL PSC address from Terraform output `shoutrrr_runtime.postgresql_host` |
| `SHOUTRRR_APP_KEY_VERSION` | `shoutrrr-app-key` version |
| `SHOUTRRR_PASSKEY_SECRET_VERSION` | `shoutrrr-passkeys-user-handle-secret` version |
| `SHOUTRRR_DB_PASSWORD_VERSION` | Cross-project `shoutrrr-database-password` version |
| `SHOUTRRR_REDIS_HOST_VERSION` | Shared `redis-host` version |
| `SHOUTRRR_REDIS_PASSWORD_VERSION` | Shared `redis-password` version |
| `SHOUTRRR_REDIS_TLS_CA_VERSION` | Shared `redis-tls-ca-certificate` version mounted as a file |
| `SHOUTRRR_PASSPORT_PRIVATE_KEY_VERSION` | `shoutrrr-passport-private-key` version |
| `SHOUTRRR_PASSPORT_PUBLIC_KEY_VERSION` | `shoutrrr-passport-public-key` version |
| `SHOUTRRR_RESEND_API_KEY_VERSION` | `shoutrrr-resend-api-key` version |

Provider credentials are optional, but each client-ID/client-secret version pair
must be configured atomically:

- `SHOUTRRR_X_CLIENT_ID_VERSION` and `SHOUTRRR_X_CLIENT_SECRET_VERSION`;
- `SHOUTRRR_LINKEDIN_CLIENT_ID_VERSION` and
  `SHOUTRRR_LINKEDIN_CLIENT_SECRET_VERSION`;
- `SHOUTRRR_FACEBOOK_CLIENT_ID_VERSION` and
  `SHOUTRRR_FACEBOOK_CLIENT_SECRET_VERSION`;
- `SHOUTRRR_THREADS_CLIENT_ID_VERSION` and
  `SHOUTRRR_THREADS_CLIENT_SECRET_VERSION`.

OAuth secret payloads are populated directly in Google Secret Manager. The
deployment workflow binds reviewed positive numeric versions and cannot read or
print their values.

Production uses the existing Terraform-managed Redis VM over verified TLS for
cache, queue, and sessions. The runtime image includes PhpRedis; the host and
password remain Secret Manager environment bindings and the private CA is a
read-only secret mount. RabbitMQ is not a Shoutrrr dependency because this
Laravel application has no AMQP queue driver; introducing one would duplicate
the supported Redis queue and add an unnecessary package and credential path.

## Deployment order

The workflow authenticates through the Terraform-managed Workload Identity
provider, verifies the GitHub provenance, Cosign signature, SBOM attestation,
and vulnerability gate for the Artifact Registry digest, and then:

1. updates and executes `shoutrrr-migrate`;
2. runs the read-only `production:assert-no-demo-users` gate through an
   execution-only Job argument override and blocks the deployment if bundled
   development identities are present;
3. updates the terminating `shoutrrr-scheduler` and
   `shoutrrr-queue-worker` Jobs;
4. creates a ready `shoutrrr` web revision with no traffic;
5. promotes traffic only when `promote_web_traffic` is explicitly selected.

Terraform must first enable the service and Job shells while keeping scheduler
and edge activation disabled. After migration and smoke tests, Terraform enables
the Cloudflare-only `shout.ioterax.app` edge and finally unpauses the two
four-hour Cloud Scheduler triggers. The web service retains `min_instances=0`;
the Jobs terminate when their bounded commands complete.
