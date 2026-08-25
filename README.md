<div align="center">

<img src=".github/assets/og.webp" alt="Shoutrrr" width="100%" />

# Shoutrrr

**An open-source, self-hostable alternative to Buffer, Typefully & Hootsuite.**

Write once, publish everywhere. Schedule posts to X, Bluesky, LinkedIn, Facebook, Instagram, Threads, and Discord from one calendar — on your own server, with your own data.

[![License](https://img.shields.io/github/license/coollabsio/shoutrrr?style=for-the-badge&color=4c1)](LICENSE)
[![Stars](https://img.shields.io/github/stars/coollabsio/shoutrrr?style=for-the-badge&logo=github&color=f5c518)](https://github.com/coollabsio/shoutrrr/stargazers)
[![Latest release](https://img.shields.io/github/v/release/coollabsio/shoutrrr?style=for-the-badge&logo=github&color=6f42c1&sort=semver)](https://github.com/coollabsio/shoutrrr/releases)
[![GHCR pulls](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fghcr-badge.elias.eu.org%2Fapi%2Fcoollabsio%2Fshoutrrr%2Fshoutrrr&query=%24.downloadCount&label=docker%20pulls&style=for-the-badge&logo=docker&logoColor=white&color=2496ED)](https://github.com/coollabsio/shoutrrr/pkgs/container/shoutrrr)

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Inertia](https://img.shields.io/badge/Inertia-3-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)

</div>

> This repository is the Apache-2.0-licensed iot.EraX downstream distribution
> of [coollabsio/shoutrrr](https://github.com/coollabsio/shoutrrr). It preserves
> upstream attribution while providing the minimal, vulnerability-gated runtime
> used by `iot.EraX Shout`.

## What is Shoutrrr?

Shoutrrr is a social media scheduling tool you run yourself. Connect your accounts, draft a post once, and send it to every network at the same time — or queue it to go out on a recurring schedule. No monthly seat fees, no third party holding your tokens or your data.

It's built for individuals and teams: invite collaborators into a shared workspace, keep clients or brands separated, and see how your posts perform — all from a single, fast interface.

## Why Shoutrrr?

- **You own everything** — your posts, your audience tokens, your analytics. Self-hosted on your infrastructure.
- **One post, every platform** — compose once and publish to multiple accounts, tweaking the text per network when you want.
- **Plan ahead** — a posting queue with recurring time slots and a month calendar, so your feed stays consistent without you babysitting it.
- **Made for teams** — workspaces, roles, and email invites keep clients and collaborators tidy.
- **No vendor lock-in** — open source under the Apache 2.0 license, runs anywhere Docker does.

## Supported platforms

| Platform         | Connect with                   | Publishing                                                | Threads         | Analytics                            |
| ---------------- | ------------------------------ | --------------------------------------------------------- | --------------- | ------------------------------------ |
| **X** (Twitter)  | OAuth 2.0                      | ✅ (Free: ≤280 chars / 2m20 video; Premium tiers: ≤25,000 chars / up to 4h video, up to 4 media) | ✅              | likes, reposts, replies, impressions |
| **Bluesky**      | ATProto OAuth or app passwords | ✅ (≤300 graphemes, up to 4 images or 1 video)            | ✅              | likes, reposts, replies              |
| **LinkedIn**     | OAuth 2.0 (OIDC)               | ✅ (≤3000 chars, up to 9 images or 1 video)               | — (single post) | not available for personal accounts  |
| **Facebook** (Pages) | OAuth 2.0 (Facebook Login) | ✅ (≤63,206 chars, up to 10 images or 1 video)            | — (single post) | likes, comments, shares, impressions |
| **Instagram**    | OAuth 2.0 (Facebook Login)     | ✅ (media required, ≤2,200 chars, up to 10 images or 1 video/Reel) | — (single post) | likes, comments, shares, views       |
| **Threads**      | OAuth 2.0                      | ✅ (≤500 chars, up to 10 images or 1 video)               | ✅              | likes, replies, reposts, views       |
| **Discord**      | Channel webhook URL            | ✅ (≤2000 chars, up to 10 files ≤10 MiB)                  | ✅              | reactions                            |

## Features

- 📝 **Composer** — draft with media and alt text, see live per-account text and video limits (including detected X subscription limits), and automatically split long posts into threads where the platform supports it.
- 🚀 **Multi-account publishing** — fan one post out to many accounts at once, with optional per-platform overrides. Each target publishes independently and retries on failure.
- 🗓️ **Queue & calendar** — set recurring posting slots (in your workspace's timezone), drop drafts into the queue, and review everything on a month calendar. Publish instantly whenever you like.
- 📊 **Analytics** — follower and post-count trends per account, plus per-post engagement (likes, reposts, replies, impressions) where the provider API supports it.
- 🔗 **Connected accounts** — link accounts via OAuth (X, LinkedIn) or app password (Bluesky), group them into reusable sets, and get nudged when one needs reconnecting. Tokens are stored encrypted and refreshed automatically; a successful authenticated X tier refresh also restores a stale attention state.
- 👥 **Workspaces & team** — multiple workspaces with role-based memberships, email invitations, and ownership transfer. Every bit of data is scoped to its workspace.
- 🔔 **Notifications** — in-app alerts when a post publishes or fails, or when an account needs attention.
- 🔐 **Secure by default** — email/password with verification, two-factor (TOTP), passkeys (WebAuthn), and optional social login (Google, X, LinkedIn).

## Self-hosting

The hardened iot.EraX production image is private in Google Artifact Registry;
public image distribution is deferred. It contains PHP 8.5, FrankenPHP,
ffmpeg, production Composer dependencies, and compiled browser assets. Bun,
Node.js, Yarn, npm, Composer, Git, SSR assets, development dependencies, and
`node_modules` are absent from the final runtime.

Each release is built for `linux/amd64` and `linux/arm64`, scanned for operating
system and language vulnerabilities before and after publication, signed with
keyless Cosign, and accompanied by provenance and CycloneDX SBOM attestations.
No mutable `latest` tag is published.

Self-hosters choose an explicit immutable image reference. It may be an image
built locally from this checkout or a digest in a registry they control. The
Compose contract does not assume Docker Hub, GHCR, or access to the private
iot.EraX registry.

Build the runtime locally, for example:

```bash
docker build --tag shoutrrr-local:1.4.4-ioterax.1 .
```

### Docker Compose

The bundled self-hosting stack uses a digest-pinned PostgreSQL 18 base and runs
migration, web, queue, and scheduler as separate containers. Its small local
database companion removes the unused vulnerable `gosu` binary and is scanned
by the same container gate. Copy the example environment, set the explicit
image reference and local pull policy, generate an application key, and set a
strong database password:

```bash
git clone https://github.com/ioterax/shoutrrr.git
cd shoutrrr
cp .env.example.prod .env

# Set SHOUTRRR_IMAGE=shoutrrr-local:1.4.4-ioterax.1,
# SHOUTRRR_PULL_POLICY=never, and POSTGRES_PASSWORD in .env first.
docker run --rm shoutrrr-local:1.4.4-ioterax.1 php artisan key:generate --show
# Copy the generated key into APP_KEY in .env.

docker compose -f docker-compose.production.yaml up -d --build
```

Open `http://localhost:8080` and create the first account. For a public HTTPS
deployment, set the final `APP_URL`, `OCTANE_HTTPS=true`,
`SESSION_SECURE_COOKIE=true`, and a precise trusted-proxy range. Use `*` only
when the application cannot be reached except through that proxy.

The application image itself does not run migrations or background processes
implicitly. Compose expresses their lifecycle explicitly, making the same image
suitable for orchestrators that separate web services from batch Jobs.

> `docker-compose.production.yaml` is a public self-hosting reference. The
> official iot.EraX deployment does not use its PostgreSQL container: Cloud Run
> connects privately to the existing Hub/Foundation PostgreSQL 16 instance and
> runs migrations, scheduler dispatch, and queue draining as terminating Cloud
> Run Jobs. See [the production delivery contract](docs/ioterax-production.md).

### Local runtime preview

`docker-compose.development.yaml` builds exactly the minimal production runtime
from the current checkout. It includes an isolated PostgreSQL container and can
optionally load the upstream demo account:

```bash
cp .env.example.prod .env
# Set APP_KEY and POSTGRES_PASSWORD in .env.
docker compose -f docker-compose.development.yaml --profile demo up -d --build
```

The demo credentials are `test@example.com` / `password`. Omit
`--profile demo` when you do not want seeded data.

The source-development workflow remains Bun-based, as defined by upstream. Bun
is a build dependency only and does not enter either runtime image.

The image accepts videos up to Shoutrrr's 1 GiB application ceiling by default.
For large or public deployments, configure appropriate proxy limits and prefer
object storage so uploads do not traverse the application process.

### Security headers & Content-Security-Policy

Outside `local`, Shoutrrr sends a strict, nonce-based **Content-Security-Policy** along with `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and (in production) `Strict-Transport-Security`. This is deliberate hardening — but if you customise the frontend or front the app with an unusual proxy/CDN, it's the first place to look when something renders wrong.

**If the UI loads unstyled or a feature is broken, open your browser's dev console and check for CSP violations.** Common causes and fixes (all in `app/Http/Middleware/SecurityHeaders.php`):

- **Assets served from a different origin than `APP_URL`** (e.g. a CDN host) are blocked by `default-src 'self'`. Serve built assets from the app origin, or add the host to `script-src`/`style-src`/`img-src`.
- **Third-party embeds or analytics scripts** are blocked — `script-src` only trusts the app's own nonced scripts (`'strict-dynamic'`). Add the source explicitly if you need it.
- **Images/avatars from arbitrary hosts** are allowed (`img-src` permits `https:`); tighten this if you prefer.

The CSP is intentionally **not** sent in `local` (`APP_ENV=local`) because it is incompatible with the Vite dev server's hot-reload. To verify the production policy locally, run a build and serve with a non-local env (`bun run build && APP_ENV=production php artisan serve`). Note that `Strict-Transport-Security` requires the app to be served over HTTPS.

## Connecting your accounts

**Bluesky** connects two ways, and neither needs you to register a developer app:

- **OAuth (recommended)** — users sign in on Bluesky and authorize Shoutrrr without handing over a password. It's zero-config: Shoutrrr publishes an [ATProto OAuth](https://atproto.com/specs/oauth) client-metadata document at `${APP_URL}/oauth/bluesky/client-metadata.json` (with keys at `${APP_URL}/oauth/bluesky/jwks.json`), and Bluesky's authorization server fetches those to identify your instance. The signing key is generated once and stored encrypted — there's nothing to add to `.env`. **The one requirement:** `APP_URL` must be a public HTTPS URL, because Bluesky has to reach those two documents over the internet. (In `local` dev, Shoutrrr falls back to a loopback client so OAuth still works on `localhost`.)
- **App password** — users paste a Bluesky [app password](https://bsky.app/settings/app-passwords). No setup, and it works anywhere — including private or LAN deployments Bluesky can't reach for OAuth.

**X**, **LinkedIn**, and the **Meta** platforms (Facebook, Instagram, Threads) publish through your own developer app, so you'll register one with each provider and add the credentials to `.env`. The redirect URIs must match what you register (they default to `${APP_URL}/...`):

```dotenv
# X — https://developer.x.com
X_CLIENT_ID=
X_CLIENT_SECRET=
X_REDIRECT_URI="${APP_URL}/accounts/callback/x"

# LinkedIn — https://www.linkedin.com/developers
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/accounts/callback/linkedin"

# Facebook + Instagram — one Meta app (https://developers.facebook.com).
# Instagram accounts are discovered via their linked Facebook Pages, so both
# platforms share these credentials. Publishing to non-test accounts requires
# Meta App Review + Business Verification.
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI="${APP_URL}/accounts/callback/meta"

# Threads — a separate Meta app (https://developers.facebook.com).
THREADS_CLIENT_ID=
THREADS_CLIENT_SECRET=
THREADS_REDIRECT_URI="${APP_URL}/accounts/callback/threads"
```

Optionally, let people sign in with a social account instead of a password:

```dotenv
SOCIALITE_ENABLED=true
SOCIALITE_PROVIDERS=google            # comma-separated: google,x,linkedin
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

> **Heads up:** publishing, scheduling, engagement polling, and analytics capture rely on a running queue worker and scheduler. The provided Docker setups start both for you. Metrics and engagement are enabled by default and can be disabled with `METRICS_ENABLED=false` / `ENGAGEMENT_ENABLED=false` (see `config/metrics.php` and `config/engagement.php`). X accounts read the authenticated account's subscription type when connected or when **Refresh tier** is used in Connected Accounts; Free accounts use a 280-character / 140-second-video limit, while Basic, Premium, and Premium+ accounts use 25,000 characters and can upload videos up to four hours. When a post targets more than one X account, the composer enforces the strictest selected account limit.

## Development

Shoutrrr is a Laravel 13 (PHP 8.5) app with a React 19 + TypeScript frontend on [Inertia](https://inertiajs.com) v3, [Tailwind v4](https://tailwindcss.com), and [shadcn/ui](https://ui.shadcn.com). It runs on [Laravel Octane](https://laravel.com/docs/octane) (FrankenPHP), with typed routes generated by [Wayfinder](https://github.com/laravel/wayfinder).

```bash
composer setup   # install deps, copy .env, generate app + Passport keys, bun install, build assets
composer dev     # serve + queue + scheduler + logs + Vite, all at once
```

> Uses [Bun](https://bun.sh) for the frontend (`bun install`, `bun run …`) — not npm/pnpm.

To test SMTP locally during development, run [Mailpit](https://github.com/axllent/mailpit):

```bash
docker run --rm --name mail --pull always -p 1025:1025 -p 8025:8025 axllent/mailpit:latest
```

Then set these mail values in your `.env` file:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_SCHEME=null
MAIL_FROM_ADDRESS="hello@shoutrrr.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### How publishing works

A post is composed once, then split into one **target** per connected account. The scheduler dispatches due posts every minute; a queued `PublishPostTarget` job then publishes each target independently, with retries, idempotency, and a per-attempt audit trail. Scheduled jobs refresh OAuth tokens before they expire, fetch replies, and check for due metrics captures every 15 minutes. Metrics refresh cadence is controlled in `config/metrics.php`.

### API & MCP tokens

The REST API and MCP integration authenticate with bearer tokens minted by [Laravel Passport](https://laravel.com/docs/passport), which signs and verifies every token with an RSA keypair. **You don't need to provision these keys** — the first time a workspace issues an API key, Shoutrrr generates the pair automatically (`ApiKeyManager::ensureEncryptionKeysExist()` runs `passport:keys`) and stores it in `storage/oauth-private.key` / `oauth-public.key`. The bundled Docker setups persist `storage` on a named volume, so the keys survive redeploys.

If you'd rather provision them explicitly — for example to share one keypair across multiple app instances behind a load balancer — do either of the following before issuing keys:

- Run `php artisan passport:keys` once and keep `storage` persistent, **or**
- Set the `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` env vars to the key contents (the auto-generation step is skipped when both are present).

To turn auto-generation off entirely, set `PASSPORT_AUTO_GENERATE_KEYS=false`. Issuing an API key without keys present then fails loudly instead of writing new ones — useful when the keypair is managed externally and Shoutrrr must never mint its own.

### Tooling

| Concern             | Tool                                                       | Command                                         |
| ------------------- | ---------------------------------------------------------- | ----------------------------------------------- |
| Tests               | [Pest](https://pestphp.com)                                | `composer test`                                 |
| PHP style           | [Pint](https://laravel.com/docs/pint)                      | `composer lint`                                 |
| PHP static analysis | [Larastan](https://github.com/larastan/larastan) (level 7) | `composer types:check`                          |
| PHP refactoring     | [Rector](https://getrector.com)                            | `composer refactor:check` / `composer refactor` |
| JS lint / format    | [oxlint](https://oxc.rs) / [oxfmt](https://oxc.rs)         | `bun run lint:check` / `bun run format:check`   |

Run the full local gate (lint, format, type-check, refactor check, Pest suite) with `composer ci:check`.

## Core maintainers

<table>
  <tr>
    <td align="center" width="200">
      <a href="https://github.com/andrasbacsai">
        <img src="https://github.com/andrasbacsai.png" width="90" alt="Andras Bacsai" /><br />
        <sub><b>Andras Bacsai</b></sub>
      </a><br /><br />
      <a href="https://github.com/andrasbacsai"><img src="https://img.shields.io/badge/-181717?style=flat-square&logo=github&logoColor=white" alt="GitHub" /></a>
      <a href="https://x.com/heyandras"><img src="https://img.shields.io/badge/-000000?style=flat-square&logo=x&logoColor=white" alt="X" /></a>
      <a href="https://bsky.app/profile/heyandras.dev"><img src="https://img.shields.io/badge/-0285FF?style=flat-square&logo=bluesky&logoColor=white" alt="Bluesky" /></a>
      <a href="https://blog.andrasbacsai.com/"><img src="https://img.shields.io/badge/-4c1?style=flat-square&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEwIi8+PGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz48cGF0aCBkPSJNMTIgMmExNS4zIDE1LjMgMCAwIDEgNCAxMCAxNS4zIDE1LjMgMCAwIDEtNCAxMCAxNS4zIDE1LjMgMCAwIDEtNC0xMCAxNS4zIDE1LjMgMCAwIDEgNC0xMHoiLz48L3N2Zz4%3D" alt="Website" /></a>
    </td>
    <td align="center" width="200">
      <a href="https://github.com/adiologydev">
        <img src="https://github.com/adiologydev.png" width="90" alt="Aditya Tripathi" /><br />
        <sub><b>Aditya Tripathi</b></sub>
      </a><br /><br />
      <a href="https://github.com/adiologydev"><img src="https://img.shields.io/badge/-181717?style=flat-square&logo=github&logoColor=white" alt="GitHub" /></a>
      <a href="https://x.com/adityatripathid"><img src="https://img.shields.io/badge/-000000?style=flat-square&logo=x&logoColor=white" alt="X" /></a>
      <a href="https://bsky.app/profile/adiology.bsky.social"><img src="https://img.shields.io/badge/-0285FF?style=flat-square&logo=bluesky&logoColor=white" alt="Bluesky" /></a>
      <a href="https://adiology.dev"><img src="https://img.shields.io/badge/-4c1?style=flat-square&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEwIi8+PGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz48cGF0aCBkPSJNMTIgMmExNS4zIDE1LjMgMCAwIDEgNCAxMCAxNS4zIDE1LjMgMCAwIDEtNCAxMCAxNS4zIDE1LjMgMCAwIDEtNC0xMCAxNS4zIDE1LjMgMCAwIDEgNC0xMHoiLz48L3N2Zz4%3D" alt="Website" /></a>
    </td>
  </tr>
</table>

See all the people who have contributed in the [contributors list](https://github.com/coollabsio/shoutrrr/graphs/contributors).

## Star history

[![RepoStars](https://repostars.dev/api/embed?repo=coollabsio%2Fshoutrrr&theme=noir)](https://repostars.dev/?repos=coollabsio%2Fshoutrrr&theme=noir)

## License

Open-source software licensed under the [Apache 2.0 license](LICENSE).
