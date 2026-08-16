FROM node:22.14.0-alpine AS dependencies
WORKDIR /app
COPY package.json package-lock.json ./
COPY apps/auth/package.json apps/auth/package.json
RUN npm ci --workspace apps/auth --include-workspace-root=false

FROM node:22.14.0-alpine AS builder
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=dependencies /app/node_modules ./node_modules
COPY --from=dependencies /app/apps/auth/node_modules ./apps/auth/node_modules
COPY package.json package-lock.json ./
COPY apps/auth ./apps/auth

# INERT PLACEHOLDERS, PRESENT ONLY SO THE BUILD CAN RUN.
#
# `next build` sets NODE_ENV=production, and its "collecting page data" step
# imports every route module -- which reaches src/lib/auth.ts and then
# assertProductionAuthConfiguration(), whose first line is
# `if (process.env.NODE_ENV !== "production") return;`. So the guard fires
# during the build and it fails with "Missing production authentication
# configuration: AUTH_DATABASE_URL, BETTER_AUTH_SECRET, BETTER_AUTH_URL"
# before a single one of those values is knowable.
#
# These are SAFE because none is a NEXT_PUBLIC_ variable: Next inlines only
# NEXT_PUBLIC_* into the bundle, and reads everything else from process.env
# when the server starts. compose supplies the real values via
# apps/auth/.env.production, and they replace these entirely -- verified after
# deployment by confirming the running container reports the real database and
# base URL, not these.
#
# The placeholder secret is deliberately not a plausible key. If one of these
# ever DOES reach a running service it should be obvious in a log, not
# something that quietly works.
ENV AUTH_DATABASE_URL="postgresql://build-time-placeholder@127.0.0.1:5432/placeholder"
ENV BETTER_AUTH_SECRET="build-time-placeholder-never-used-at-runtime"
ENV BETTER_AUTH_URL="http://build-time-placeholder.invalid"

RUN npm --workspace apps/auth run build

FROM node:22.14.0-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3004
RUN addgroup --system --gid 1001 nodejs && adduser --system --uid 1001 nextjs
COPY --from=builder --chown=nextjs:nodejs /app/apps/auth/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/apps/auth/.next/static ./apps/auth/.next/static
COPY --from=builder --chown=nextjs:nodejs /app/apps/auth/public ./apps/auth/public
USER nextjs
EXPOSE 3004
CMD ["node", "apps/auth/server.js"]
