# VP3 Managed-Updates License Scope v64.1

## Product decision

The POD application remains fully usable without a VP3 license.

A VP3 license is required only for:

- managed automatic downloads and updates;
- update-channel eligibility supplied by VP3.me;
- future managed services that explicitly request a licensed capability.

A VP3 license is not required for:

- the public website;
- the owner and client portals;
- CRM, contacts, communications, calling, messaging, publishing, media, portfolio, events, appointments, proposals, feeds, backups, exports, and recovery;
- manual deployment of a downloaded or administrator-supplied release;
- preserving or accessing existing customer data.

## Unlicensed installation behavior

An unlicensed or not-yet-provisioned POD:

1. loads normally;
2. retains all existing application data and local features;
3. may be updated manually by the owner;
4. reports managed automatic updates as disabled;
5. returns HTTP 403 from the managed-update eligibility endpoint;
6. includes `vp3_license_required_for_managed_updates` in the denial reasons;
7. explicitly reports `site_operational=true` and `manual_deployment_allowed=true`.

The licensing authority does not become a runtime dependency for ordinary page loads.

## Licensed installation behavior

After the owner obtains a VP3 license and provisions the account, Domain registration, deployment, credential, and signed entitlement:

1. the owner opens `/portal/vp3-license.php`;
2. the entitlement is validated;
3. the `automatic_updates` capability is confirmed;
4. the update agent calls `POST /api/vp3-license/update-eligibility.php`;
5. signed, compatible, backed-up releases on the allowed channel may be installed automatically.

Adding a license later does not require reinstalling the POD or replacing customer data.

## Deployment

No new SQL is required for v64.1. The existing migration remains:

`database/vp3_pod_licensing_v64.sql`

Deploy the v64.1 files while preserving live `config.php` and the complete `storage/` directory.

Until VP3.me licensing is available, leave the VP3 provisioning identifiers and deployment credential empty. Do not schedule the license heartbeat or connect the automatic updater. The POD will continue to operate normally.
