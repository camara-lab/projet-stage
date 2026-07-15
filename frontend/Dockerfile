# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Next.js (Production)
# Build multi-stage : deps → builder → runner
# ─────────────────────────────────────────────────────────────────────────────

FROM node:20-alpine AS deps

WORKDIR /app

# Installer les dépendances uniquement (couche cachée séparément du build)
COPY package.json package-lock.json* yarn.lock* pnpm-lock.yaml* ./
RUN \
  if [ -f yarn.lock ]; then yarn --frozen-lockfile; \
  elif [ -f package-lock.json ]; then npm ci; \
  elif [ -f pnpm-lock.yaml ]; then corepack enable pnpm && pnpm i --frozen-lockfile; \
  else echo "No lockfile found." && exit 1; \
  fi

# ─────────────────────────────────────────────────────────────────────────────
# Build
# ─────────────────────────────────────────────────────────────────────────────

FROM node:20-alpine AS builder

WORKDIR /app

# Les node_modules viennent du stage précédent
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# Variables build-time (ne contiennent pas de secrets)
ARG API_URL=http://symfony
ARG NEXT_PUBLIC_APP_URL=https://busgo.ma

ENV API_URL=${API_URL}
ENV NEXT_PUBLIC_APP_URL=${NEXT_PUBLIC_APP_URL}
ENV NEXT_TELEMETRY_DISABLED=1
ENV NODE_ENV=production

RUN npm run build

# ─────────────────────────────────────────────────────────────────────────────
# Runner — image minimale sans outils de build
# ─────────────────────────────────────────────────────────────────────────────

FROM node:20-alpine AS runner

WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

# Utilisateur non-root
RUN addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

# Fichiers statiques publics
COPY --from=builder /app/public ./public

# Standalone output (active dans next.config.js avec output: 'standalone')
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000

ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

CMD ["node", "server.js"]
