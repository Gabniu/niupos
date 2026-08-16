import { Pool } from "pg";

export const authDatabase = new Pool({
  connectionString: process.env.AUTH_DATABASE_URL,
  max: 10,
  idleTimeoutMillis: 30_000,
  connectionTimeoutMillis: 5_000,
});
