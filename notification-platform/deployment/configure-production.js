import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import mysql from 'mysql2/promise';
import { makePasswordHash } from '../src/security.js';

const environmentPath = '/etc/notification-platform.env';
const tenantSecretsPath = '/etc/notification-tenant-secrets.json';
const adminCredentialsPath = '/root/notification-platform-admin.txt';
const smtpPath = '/tmp/notification-smtp.json';

try {
  await fs.access(environmentPath);
  console.log('Production configuration already exists; secrets were not rotated.');
  process.exit(0);
} catch {}

const smtp = JSON.parse(await fs.readFile(smtpPath, 'utf8'));
const token = (bytes = 32) => crypto.randomBytes(bytes).toString('base64url');
const databasePassword = token(36);
const adminPassword = token(18);
const tenantSecrets = Object.fromEntries([
  'zambia-job-alerts', 'prime-job-alerts', 'prime-scholarship-alerts', 'zinstablog',
].map((site) => [site, token(32)]));

const connection = await mysql.createConnection({ user: 'root', socketPath: '/var/run/mysqld/mysqld.sock' });
try {
  await connection.query('CREATE DATABASE IF NOT EXISTS notification_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
  await connection.query(`CREATE USER IF NOT EXISTS 'notification_platform'@'127.0.0.1' IDENTIFIED BY '${databasePassword}'`);
  await connection.query(`ALTER USER 'notification_platform'@'127.0.0.1' IDENTIFIED BY '${databasePassword}'`);
  await connection.query("GRANT ALL PRIVILEGES ON notification_platform.* TO 'notification_platform'@'127.0.0.1'");
} finally {
  await connection.end();
}

function value(input) {
  return JSON.stringify(String(input ?? ''));
}

function smtpLines(prefix, profile) {
  const port = Number(profile.MAIL_PORT || 587);
  return [
    `SMTP_${prefix}_HOST=${value(profile.MAIL_HOST)}`,
    `SMTP_${prefix}_PORT=${port}`,
    `SMTP_${prefix}_SECURE=${port === 465 || String(profile.MAIL_SCHEME || '').toLowerCase() === 'smtps'}`,
    `SMTP_${prefix}_USER=${value(profile.MAIL_USERNAME)}`,
    `SMTP_${prefix}_PASSWORD=${value(profile.MAIL_PASSWORD)}`,
    `SMTP_${prefix}_FROM_ADDRESS=${value(profile.MAIL_FROM_ADDRESS)}`,
  ];
}

const lines = [
  'NODE_ENV=production',
  'PORT=8787',
  'PUBLIC_BASE_URL=https://api.alphilnetworks.com/notifications',
  'LISTEN_ALLOW_IPS=127.0.0.1,::1,204.168.224.153',
  'DB_HOST=127.0.0.1',
  'DB_PORT=3306',
  'DB_NAME=notification_platform',
  'DB_USER=notification_platform',
  `DB_PASSWORD=${value(databasePassword)}`,
  'DB_POOL_SIZE=10',
  'ADMIN_USER=admin',
  `ADMIN_PASSWORD_HASH=${value(makePasswordHash(adminPassword))}`,
  `ADMIN_CSRF_SECRET=${value(token(32))}`,
  `IP_HASH_SECRET=${value(token(32))}`,
  ...smtpLines('PJA', smtp.pja),
  ...smtpLines('PSA', smtp.psa),
  'WORKER_POLL_MS=1500',
  'WORKER_CAMPAIGN_POLL_MS=10000',
  'WORKER_ID=production-worker-1',
  `TENANT_WEBHOOK_SECRETS_JSON=${value(JSON.stringify(tenantSecrets))}`,
  '',
];

await fs.writeFile(environmentPath, lines.join('\n'), { mode: 0o600 });
await fs.writeFile(tenantSecretsPath, `${JSON.stringify(tenantSecrets, null, 2)}\n`, { mode: 0o600 });
await fs.writeFile(adminCredentialsPath, `URL=https://api.alphilnetworks.com/notifications/admin\nUsername=admin\nPassword=${adminPassword}\n`, { mode: 0o600 });
console.log('Created database, secure environment, tenant webhook secrets, and admin credentials.');
