# Production image for the NOVA POS web front end (apps/web).
#
# Deliberately the same three-stage shape as infra/docker/auth/Dockerfile —
# install, build, then copy only Next's standalone output into a clean runtime.
# Two Next applications in one repository should not have two different build
# strategies to reason about.
#
# The standalone output contains the compiled app plus only the node_modules it
# actually imports, so the runtime stage never carries the build toolchain or
# the dev dependency tree.

FROM node:22.14.0-alpine AS dependencies
WORKDIR /app
COPY package.json package-lock.json ./
COPY apps/web/package.json apps/web/package.json
# --include-workspace-root=false keeps the other workspaces (auth, contracts)
# out of this image entirely.
RUN npm ci --workspace apps/web --include-workspace-root=false

FROM node:22.14.0-alpine AS builder
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=dependencies /app/node_modules ./node_modules
COPY --from=dependencies /app/apps/web/node_modules ./apps/web/node_modules
COPY package.json package-lock.json ./
COPY apps/web ./apps/web
# NEXT_PUBLIC_* values are inlined into the client bundle at BUILD time, not
# read at runtime. Left empty on purpose: the browser calls /api/v1/... on the
# same origin and next.config.ts rewrites it server-side to NOVA_API_ORIGIN,
# which IS read at runtime. Baking an absolute API URL here would tie the image
# to one hostname.
ENV NEXT_PUBLIC_API_BASE_URL=""
RUN npm --workspace apps/web run build

FROM node:22.14.0-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3011
ENV HOSTNAME=0.0.0.0

# Runs as a non-root user. HOSTNAME must be 0.0.0.0 rather than the Next
# default of localhost, or the server would only accept connections from inside
# the container and the published port would appear dead.
RUN addgroup --system --gid 1001 nodejs && adduser --system --uid 1001 nextjs

COPY --from=builder --chown=nextjs:nodejs /app/apps/web/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/apps/web/.next/static ./apps/web/.next/static
# Static assets served directly by Next — includes the sign-in carousel images
# referenced by src/app/page.tsx, which would 404 without this.
COPY --from=builder --chown=nextjs:nodejs /app/apps/web/public ./apps/web/public

USER nextjs
EXPOSE 3011

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 \
    CMD node -e "fetch('http://127.0.0.1:3011/').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"

CMD ["node", "apps/web/server.js"]
