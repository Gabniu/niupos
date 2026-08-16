import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emits .next/standalone: the app plus only the node_modules it actually
  // imports, with its own server.js. infra/docker/web/Dockerfile copies that
  // directory into a clean runtime image, the same shape apps/auth uses.
  // Without it there is no server.js to run and the image would have to carry
  // the entire dependency tree.
  output: "standalone",

  // Do not advertise the framework in an X-Powered-By header.
  poweredByHeader: false,
  reactStrictMode: true,

  // FOR LOCAL DEVELOPMENT ONLY. In the deployed stack nginx routes /api/
  // straight to the API container and this rewrite never fires.
  //
  // The reason it cannot be relied on in production is easy to miss: **Next
  // evaluates rewrites() at BUILD time**, not per request. The result is
  // serialised into .next/routes-manifest.json, so `process.env.NOVA_API_ORIGIN`
  // is read by `next build` and whatever it resolved to then is frozen into the
  // image. Setting it in compose has no effect at all — the container starts,
  // looks healthy, and every API call 500s with ECONNREFUSED to the default
  // address. Found exactly that way on the first deploy.
  //
  // (Same shape as Laravel's `config:cache` baking env() at container start —
  // two different frameworks, one lesson: know which values are frozen at build
  // time and which are read live.)
  async rewrites() {
    const apiOrigin = process.env.NOVA_API_ORIGIN ?? "http://127.0.0.1:8000";
    return [{ source: "/api/v1/:path*", destination: `${apiOrigin}/api/v1/:path*` }];
  },
};

export default nextConfig;
