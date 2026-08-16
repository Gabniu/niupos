# Observability baseline

`prometheus/prometheus.yaml` discovers only pods that explicitly opt in with `prometheus.io/scrape: "true"`; NOVA workloads do not opt in until a real metrics endpoint exists. The initial executable alert covers scrape-target availability via Prometheus's built-in `up` metric.

Before shared-environment acceptance, instrument and document stable application metrics, then add recording/alert rules for:

- request latency using a real histogram and an agreed percentile/SLO;
- server error rate using real request counters and route normalization;
- CPU, memory, queue, database-pool, and worker saturation using installed exporters;
- business-critical checkout and payment symptoms without high-cardinality tenant/customer labels.

No latency, error, saturation, or business metric name is invented here. Alert routing, retention, authentication, RBAC, dashboards, and paging ownership are deployment-specific follow-up work.

