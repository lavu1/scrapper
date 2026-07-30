import 'dotenv/config';
import crypto from 'node:crypto';

function required(name) {
  const value = process.env[name];
  if (!value) throw new Error(`Missing required environment variable: ${name}`);
  return value;
}

function integer(name, fallback) {
  const value = Number.parseInt(process.env[name] || String(fallback), 10);
  if (!Number.isFinite(value)) throw new Error(`Invalid integer environment variable: ${name}`);
  return value;
}

function boolean(name, fallback = false) {
  const value = process.env[name];
  if (value === undefined) return fallback;
  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
}

export const config = {
  env: process.env.NODE_ENV || 'production',
  port: integer('PORT', 8787),
  publicBaseUrl: (process.env.PUBLIC_BASE_URL || 'http://localhost:8787').replace(/\/$/, ''),
  allowedProxyIps: (process.env.LISTEN_ALLOW_IPS || '127.0.0.1,::1,204.168.224.153').split(',').map((value) => value.trim()).filter(Boolean),
  database: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: integer('DB_PORT', 3306),
    database: process.env.DB_NAME || 'notification_platform',
    user: process.env.DB_USER || 'notification_platform',
    password: process.env.DB_PASSWORD || '',
    connectionLimit: integer('DB_POOL_SIZE', 10),
  },
  admin: {
    user: process.env.ADMIN_USER || 'admin',
    passwordHash: process.env.ADMIN_PASSWORD_HASH || '',
    csrfSecret: process.env.ADMIN_CSRF_SECRET || '',
  },
  ipHashSecret: process.env.IP_HASH_SECRET || '',
  worker: {
    pollMs: integer('WORKER_POLL_MS', 1500),
    campaignPollMs: integer('WORKER_CAMPAIGN_POLL_MS', 10000),
    id: process.env.WORKER_ID || `${process.pid}-${crypto.randomUUID()}`,
  },
  smtpProfiles: {
    pja: {
      host: process.env.SMTP_PJA_HOST,
      port: integer('SMTP_PJA_PORT', 587),
      secure: boolean('SMTP_PJA_SECURE'),
      user: process.env.SMTP_PJA_USER,
      password: process.env.SMTP_PJA_PASSWORD,
      fromAddress: process.env.SMTP_PJA_FROM_ADDRESS,
    },
    psa: {
      host: process.env.SMTP_PSA_HOST,
      port: integer('SMTP_PSA_PORT', 587),
      secure: boolean('SMTP_PSA_SECURE'),
      user: process.env.SMTP_PSA_USER,
      password: process.env.SMTP_PSA_PASSWORD,
      fromAddress: process.env.SMTP_PSA_FROM_ADDRESS,
    },
  },
};

export function assertProductionConfig() {
  if (config.env === 'test') return;
  required('DB_PASSWORD');
  required('ADMIN_PASSWORD_HASH');
  required('ADMIN_CSRF_SECRET');
  required('IP_HASH_SECRET');
}
