# VP3 POD Licensing Integration v64 Scorecard

| Area | Before | After | Certification |
|---|---:|---:|---|
| Repository and security foundation | 0.8/1.0 | 1.0/1.0 | Existing bootstrap, PDO, CSRF, rate limits, protected storage retained |
| Deployment identity and Domain-linked assignment | 0.2/1.0 | 1.0/1.0 | Account, Domain registration, license, deployment, hostname, and fingerprint |
| Signed provider request security | 0.2/1.0 | 1.0/1.0 | Credential version, HMAC, timestamp, nonce, request UUID, and body hash |
| Signed entitlement verification | 0.2/1.5 | 1.5/1.5 | JWKS, Ed25519/RS256, issuer, audience, time, identity, and key rotation |
| Centralized capabilities and lifecycle behavior | 0.4/1.25 | 1.25/1.25 | Explicit allows/limit/value checks and all required states |
| Offline lease and outage safety | 0.3/1.0 | 1.0/1.0 | Last verified lease without public-site shutdown |
| Storage allowance integration | 0.2/1.0 | 1.0/1.0 | Measurement, 80/90/100 warnings, hard-limit guard, no deletion |
| Update eligibility integration | 0.2/1.0 | 1.0/1.0 | State, channel, integrity, migration, storage, backup, and critical/recovery policy |
| Owner UI, receipts, and operations | 0.3/0.75 | 0.75/0.75 | Status page, validation, heartbeat, storage, rotation, receipts, and events |
| Compatibility, documentation, and regression | 0.3/0.5 | 0.5/0.5 | Additive migration, signed-token regression, no content redesign or destruction |
| **Total** | **3.1/10** | **10/10** | **Certified by Portal Quality run 30412966537; final exact-head run required** |
