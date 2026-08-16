---
id: ADR-0016
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [platform, architecture]
requirements: [REQ-G1-K8S-001, REQ-G1-OBS-001]
modules: [MOD-PLATFORM]
tests: [TEST-G1-K8S-STATIC-001, TEST-G1-K8S-DRYRUN-001]
risks: [RISK-G1-K8S-RUNTIME-001, RISK-G1-OBS-GAPS-001, RISK-G1-SECRETS-001]
---

# ADR-0016 Kubernetes Development Observability Baseline

## Context

Gate 1 requires a reviewable shared-environment deployment shape and an observability starting point without representing local configuration as production evidence.

## Decision

REQ-G1-K8S-001 defines a Kustomize base and development overlay for the API and web. The base supplies Services, rolling Deployments, non-root restricted containers, resources, TCP probes, disruption budgets, default-deny networking, DNS, web-to-API, and explicitly labeled ingress flows. Configuration is non-secret; `nova-api-secrets` must be supplied externally.

REQ-G1-OBS-001 defines opt-in Prometheus pod discovery and an availability alert based only on Prometheus's real `up` metric. Latency, error, saturation, and business alerts remain named requirements until real application/exporter metrics and SLO thresholds exist.

## Consequences and evidence

- TEST-G1-K8S-STATIC-001 checks required files and parses YAML when `ConvertFrom-Yaml` is installed, otherwise it performs basic structure and tab checks.
- TEST-G1-K8S-DRYRUN-001 renders Kustomize and performs a client dry-run when `kubectl` is installed.
- TCP probes are deliberately weaker than HTTP health contracts and do not prove dependency readiness.
- The development overlay uses one replica; the disruption budgets become meaningful only with the two-replica base or greater.
- RISK-G1-SECRETS-001: accidental credential commitment. Control: commit only a Secret reference and provision values out of band.
- RISK-G1-K8S-RUNTIME-001: static validation can miss CNI, scheduler, image, security-context, and runtime failures. Control: execute rollout, disruption, and NetworkPolicy tests on a real non-production cluster.
- RISK-G1-OBS-GAPS-001: `up` alone misses latency, errors, saturation, and business failure. Control: instrument stable metrics, define SLOs, and validate alerts before shared-environment acceptance.

## Acceptance boundary

This ADR proves a configured development baseline only. It does not prove that images build or start, probes succeed, NetworkPolicy is enforced, disruption preserves service, Prometheus has RBAC, alerts fire, or any SLO is met. Those require retained cluster execution evidence.

