# VP3 POD Signed Managed Update Agent v65 — Quality Scorecard

## Initial audit

**3.2 / 10**

The POD had licensing eligibility and an update-authorization endpoint, but no component that could safely retrieve, verify, back up, install, health-check, or roll back an application release.

## Implemented quality gates

| Area | Weight | Delivered |
|---|---:|---|
| License and channel authorization | 10 | Active/grace entitlement, `automatic_updates`, channel, storage, critical/recovery policy |
| Provider authentication | 10 | Deployment HMAC, credential version, timestamp, nonce, request UUID, body hash |
| Release authenticity | 15 | Ed25519/RS256 manifest and package signatures, JWKS refresh, issuer/audience/target/time binding |
| Package safety | 10 | HTTPS streaming, host policy, byte limit, SHA-256, ZIP count/size, traversal and symlink rejection |
| Data preservation | 15 | `config.php` and complete `storage/` exclusion, application inventory, database snapshot, integrity hashes |
| Installation safety | 10 | Protected staging, atomic per-file replacement, signed additive migrations, retirement list controls |
| Health and rollback | 15 | Maintenance 503, local check, authenticated new-process HTTP check, automatic and manual rollback |
| Administration and automation | 5 | Settings panel, Update Center, encrypted worker token, manual default, security-only unattended mode |
| Concurrency and receipts | 5 | Filesystem operation lock, release/job/backup/migration/receipt records, redacted errors |
| Regression and documentation | 5 | Real Ed25519 tests, tamper rejection, retained licensing tests, authority contract, deployment setup |

## Final certification target

**10 / 10** after both GitHub workflows pass on the exact PR head.

Live hosting certification remains a deployment gate because GitHub Actions cannot prove shared-host filesystem ownership, Apache rewrite behavior, database permissions, or a full remote release-service transaction.
