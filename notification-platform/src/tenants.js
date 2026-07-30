import { db } from './db.js';
import { safeEqual, sha256 } from './security.js';

const cache = new Map();
const ttlMs = 60_000;

function parseTenant(row) {
  if (!row) return null;
  return {
    ...row,
    settings: typeof row.settings === 'string' ? JSON.parse(row.settings || '{}') : (row.settings || {}),
  };
}

export async function tenantByKey(key) {
  const cached = cache.get(key);
  if (cached && cached.expiresAt > Date.now()) return cached.tenant;
  const [rows] = await db.execute('SELECT * FROM tenants WHERE `key` = ? AND active = 1 LIMIT 1', [key]);
  const tenant = parseTenant(rows[0]);
  if (tenant) cache.set(key, { tenant, expiresAt: Date.now() + ttlMs });
  return tenant;
}

export async function tenantById(id) {
  const [rows] = await db.execute('SELECT * FROM tenants WHERE id = ? AND active = 1 LIMIT 1', [id]);
  return parseTenant(rows[0]);
}

export async function allTenants() {
  const [rows] = await db.execute('SELECT * FROM tenants WHERE active = 1 ORDER BY name');
  return rows.map(parseTenant);
}

export function originAllowed(tenant, origin) {
  if (!tenant || !origin) return false;
  let parsed;
  try {
    parsed = new URL(origin);
  } catch {
    return false;
  }
  const allowed = new Set([tenant.domain, ...(tenant.settings.allowedDomains || [])]);
  return parsed.protocol === 'https:' && allowed.has(parsed.hostname.toLowerCase());
}

export function webhookAuthorized(tenant, suppliedSecret) {
  return Boolean(tenant && suppliedSecret && safeEqual(tenant.webhook_secret_hash, sha256(suppliedSecret)));
}

export function clearTenantCache() {
  cache.clear();
}
