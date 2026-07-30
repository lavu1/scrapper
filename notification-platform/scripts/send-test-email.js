import crypto from 'node:crypto';
import { closeDatabase, db } from '../src/db.js';
import { insertDelivery, recipientHash } from '../src/deliveries.js';
import { randomToken, sha256 } from '../src/security.js';

const [site = 'prime-job-alerts', email] = process.argv.slice(2);
if (! email || !/^\S+@\S+\.\S+$/.test(email)) {
  throw new Error('Usage: npm run test-email -- <site> <email>');
}

try {
  const [tenants] = await db.execute('SELECT * FROM tenants WHERE `key` = ? AND active = 1 LIMIT 1', [site]);
  const tenant = tenants[0];
  if (!tenant) throw new Error(`Unknown tenant: ${site}`);
  const emailHash = recipientHash(email);
  await db.execute(
    `INSERT INTO email_subscriptions
     (public_id, tenant_id, email_hash, email, filters, frequency, status, unsubscribe_token_hash, verified_at)
     VALUES (?, ?, ?, ?, '{}', 'immediate', 'active', ?, NOW())
     ON DUPLICATE KEY UPDATE status = 'active', verified_at = COALESCE(verified_at, NOW()), unsubscribed_at = NULL`,
    [crypto.randomUUID(), tenant.id, emailHash, email.toLowerCase(), sha256(randomToken())],
  );
  const [subscriptions] = await db.execute(
    'SELECT id FROM email_subscriptions WHERE tenant_id = ? AND email_hash = ? LIMIT 1',
    [tenant.id, emailHash],
  );
  const targetUrl = `https://${tenant.domain}`;
  await insertDelivery({
    tenantId: tenant.id,
    channel: 'email',
    emailSubscriptionId: subscriptions[0].id,
    recipientHash: emailHash,
    payload: {
      type: 'system-test',
      title: `${tenant.name} notification email test`,
      body: 'This confirms that the self-hosted email notification service, queue worker, branding, tracking, and failure isolation are operating.',
      targetUrl,
      content: [{
        id: `test-${Date.now()}`,
        type: 'job',
        title: 'Production notification system test',
        company: tenant.name,
        location: 'Zambia',
        jobType: 'System test',
        description: 'This is a controlled delivery test. No application is required.',
        url: targetUrl,
      }],
    },
  });
  console.log(JSON.stringify({ queued: true, site, email: email.toLowerCase() }));
} finally {
  await closeDatabase();
}
