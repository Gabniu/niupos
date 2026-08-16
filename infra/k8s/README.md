# NOVA Kubernetes development baseline

Render with `kubectl kustomize infra/k8s/overlays/dev`. The overlay is non-production and uses placeholder local image names. Build or load those images into the selected development cluster before applying.

Create `nova-api-secrets` out of band; it must contain the environment-specific application key and data-service credentials. No Secret object or credential value is committed. External PostgreSQL, Redis, RabbitMQ, and Elasticsearch egress rules must be added only after their namespace/labels and ports are selected.

The TCP probes prove only that a process accepts connections. Replace them with committed HTTP startup/readiness/liveness contracts after the applications implement those endpoints. The development overlay runs one replica, so its base PodDisruptionBudgets cannot guarantee voluntary-disruption availability; the two-replica base is the shared-environment starting point.

The default-deny policy permits DNS, web-to-API traffic, and ingress-controller traffic from a namespace explicitly labeled `nova-pos/ingress=true`. A cluster CNI that enforces NetworkPolicy is required.

