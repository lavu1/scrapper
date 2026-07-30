import crypto from 'node:crypto';
import webpush from 'web-push';
import { db, closeDatabase } from '../src/db.js';
import { randomToken, sha256 } from '../src/security.js';

const defaults = [
  {
    key: 'zambia-job-alerts',
    name: 'Zambia Job Alerts',
    domain: 'zambiajobalerts.com',
    logoUrl: 'https://zambiajobalerts.com/wp-content/uploads/2024/02/cropped-logo-192x192.png',
    smtpProfile: 'pja',
    settings: {
      color: '#142b63',
      contentLabel: 'job',
      allowedDomains: ['www.zambiajobalerts.com'],
      androidUrl: 'https://play.google.com/store/apps/details?id=com.solutions.alphil.zambiajobalerts',
      iosUrl: 'https://apps.apple.com/app/zambia-job-alerts/id6761562142',
    },
  },
  {
    key: 'prime-job-alerts',
    name: 'Prime Job Alerts',
    domain: 'primejobalerts.com',
    logoUrl: 'https://primejobalerts.com/favicon.ico',
    smtpProfile: 'pja',
    settings: {
      color: '#082f49',
      contentLabel: 'job',
      allowedDomains: ['www.primejobalerts.com'],
      androidUrl: 'https://play.google.com/store/apps/details?id=com.alphil.networks.primejobsglobal',
      iosUrl: 'https://apps.apple.com/zm/app/prime-jobs-global/id6788025548',
    },
  },
  {
    key: 'prime-scholarship-alerts',
    name: 'Prime Scholarship Alerts',
    domain: 'primescholarshipalerts.com',
    logoUrl: 'https://primescholarshipalerts.com/images/brand/prime-scholarship-alerts-logo.png',
    smtpProfile: 'psa',
    settings: {
      color: '#2563eb',
      contentLabel: 'scholarship',
      allowedDomains: ['www.primescholarshipalerts.com'],
    },
  },
  {
    key: 'zinstablog',
    name: 'ZinstaBlog',
    domain: 'zinstablog.com',
    logoUrl: 'https://zinstablog.com/favicon.ico',
    smtpProfile: 'pja',
    settings: {
      color: '#7c3aed',
      contentLabel: 'story',
      allowedDomains: ['www.zinstablog.com'],
    },
  },
];

const suppliedSecrets = JSON.parse(process.env.TENANT_WEBHOOK_SECRETS_JSON || '{}');
const result = [];

try {
  for (const definition of defaults) {
    const [existingRows] = await db.execute('SELECT * FROM tenants WHERE `key` = ? LIMIT 1', [definition.key]);
    const existing = existingRows[0];
    const vapid = existing
      ? { publicKey: existing.vapid_public_key, privateKey: existing.vapid_private_key }
      : webpush.generateVAPIDKeys();
    const webhookSecret = suppliedSecrets[definition.key] || randomToken(32);

    await db.execute(
      `INSERT INTO tenants
        (\`key\`, name, domain, logo_url, vapid_subject, vapid_public_key, vapid_private_key,
         webhook_secret_hash, smtp_profile, settings, active)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
       ON DUPLICATE KEY UPDATE
         name = VALUES(name), domain = VALUES(domain), logo_url = VALUES(logo_url),
         vapid_subject = VALUES(vapid_subject), smtp_profile = VALUES(smtp_profile),
         settings = VALUES(settings), active = 1`,
      [
        definition.key,
        definition.name,
        definition.domain,
        definition.logoUrl,
        `mailto:admin@${definition.domain}`,
        vapid.publicKey,
        vapid.privateKey,
        existing ? existing.webhook_secret_hash : sha256(webhookSecret),
        definition.smtpProfile,
        JSON.stringify(definition.settings),
      ],
    );

    result.push({
      key: definition.key,
      publicKey: vapid.publicKey,
      webhookSecret: existing && !suppliedSecrets[definition.key] ? '(unchanged)' : webhookSecret,
      correlationId: crypto.randomUUID(),
    });
  }

  console.log(JSON.stringify(result, null, 2));
} finally {
  await closeDatabase();
}
