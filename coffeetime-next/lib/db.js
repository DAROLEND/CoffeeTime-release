import mysql from "mysql2/promise";

// Singleton pool — reused across all API route calls in dev/prod
let pool;

export function getPool() {
  if (!pool) {
    pool = mysql.createPool({
      host:     process.env.DB_HOST     || "localhost",
      port:     Number(process.env.DB_PORT) || 3306,
      user:     process.env.DB_USER     || "root",
      password: process.env.DB_PASSWORD || "",
      database: process.env.DB_NAME     || "CoffeeTime",
      waitForConnections: true,
      connectionLimit:    10,
      queueLimit:         0,
      charset:            "utf8mb4",
    });
  }
  return pool;
}

/**
 * Run a query and return rows.
 * @param {string} sql
 * @param {any[]} params
 * @returns {Promise<any[]>}
 */
export async function query(sql, params = []) {
  const db = getPool();
  const [rows] = await db.execute(sql, params);
  return rows;
}

/**
 * Run a query and return the first row (or null).
 */
export async function queryOne(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] ?? null;
}
