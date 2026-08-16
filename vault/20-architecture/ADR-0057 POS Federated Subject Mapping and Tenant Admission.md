# ADR-0057 POS Federated Subject Mapping and Tenant Admission

Status: Accepted
Date: 2026-08-13

## Context

NOVA POS is staging Better Auth/OIDC federation while its existing opaque
application sessions remain authoritative. A verified provider identity must
not silently create a local user, match an email address, or trust a tenant
claim from an ID token.

## Decision

The Identity module exposes `FederatedIdentityMapper::admit()`. It admits a
verified `(issuer, subject)` only when all of the following are true:

- the tenant identifier is a valid UUID and the tenant row is `active`;
- the local user has an exact matching `identity_issuer` and
  `identity_subject` pair; and
- that user has an `active` `tenant_memberships` row for the requested tenant.

The adapter returns a typed `FederatedIdentityAdmission` containing the local
user, membership, and tenant identifier. Missing, inactive, malformed, or
cross-tenant records return `null` through one fail-closed boundary. Email,
name, claims, and token-provider organization values are not lookup keys.

The callback now exchanges the one-time code, verifies the ID token, resolves
the exact local subject, and issues the existing opaque POS session inside an
audited transaction. Federation remains disabled by default until deployment
configuration and the integrated rollback/evidence suite are accepted.

## Traceability

- Contract: `apps/api/app/Modules/Identity/Application/Contracts/FederatedIdentityMapper.php`.
- Implementation: `apps/api/app/Modules/Identity/Infrastructure/DatabaseFederatedIdentityMapper.php`.
- Tests: `apps/api/tests/Feature/Modules/Identity/FederatedIdentityResolverTest.php`.
- Related architecture: ADR-0037 and ADR-0051 through ADR-0056.

## Risks and follow-up

- Existing rows need an explicit linking/migration process; no automatic email
  linking is permitted.
- Federation remains disabled by default; enabling it requires deployment
  configuration, registered exact redirect URI, and integrated rollback tests.
- PostgreSQL RLS integration must be exercised with a tenant scope in the full
  server-side suite; SQLite focused tests cover the decision boundary only.
