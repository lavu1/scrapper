import crypto from 'node:crypto';
import { setTimeout as delay } from 'node:timers/promises';
import { closeDatabase, db } from '../src/db.js';
import { recipientHash } from '../src/deliveries.js';

const [site = 'prime-job-alerts', email] = process.argv.slice(2);
if (!email) throw new Error('Usage: npm run test-isolation -- <site> <email>');

const failedId = crypto.randomUUID();
const successfulId = crypto.randomUUID();

try {
  const [tenants] = await db.execute('SELECT id, domain FROM tenants WHERE `key` = ? LIMIT 1', [site]);
  if (!tenants.length) throw new Error(`Unknown tenant: ${site}`);
  const tenant = tenants[0];
  const [subscriptions] = await db.execute(
    `SELECT id, email_hash FROM email_subscriptions
     WHERE tenant_id = ? AND email_hash = ? AND status = 'active' LIMIT 1`,
    [tenant.id, recipientHash(email)],
  );
  if (!subscriptions.length) throw new Error('Run the email test first to create the active test recipient.');
  const basePayload = {
    type: 'isolation-test',
    title: 'Failure-isolation production test',
    body: 'A successful recipient continued after a separate delivery was deliberately failed.',
    targetUrl: `https://${tenant.domain}`,
    content: [{ id: successfulId, type: 'system', title: 'Isolation test passed', description: 'This email proves the valid delivery was not blocked by the failed delivery ahead of it.', url: `https://${tenant.domain}` }],
  };
  await db.execute(
    `INSERT INTO deliveries
     (public_id, tenant_id, channel, recipient_hash, max_attempts, payload, available_at)
     VALUES (?, ?, 'email', ?, 1, ?, NOW())`,
    [failedId, tenant.id, recipientHash(`missing-${failedId}`), JSON.stringify(basePayload)],
  );
  await db.execute(
    `INSERT INTO deliveries
     (public_id, tenant_id, channel, email_subscription_id, recipient_hash, max_attempts, payload, available_at)
     VALUES (?, ?, 'email', ?, ?, 1, ?, NOW())`,
    [successfulId, tenant.id, subscriptions[0].id, subscriptions[0].email_hash, JSON.stringify(basePayload)],
  );

  for (let attempt = 0; attempt < 30; attempt += 1) {
    const [rows] = await db.execute(
      'SELECT public_id, status, attempts, error_code FROM deliveries WHERE public_id IN (?, ?) ORDER BY id',
      [failedId, successfulId],
    );
    if (rows.length === 2 && rows.every((row) => ['failed', 'sent', 'delivered', 'opened', 'clicked'].includes(row.status))) {
      console.log(JSON.stringify({ passed: rows[0].status === 'failed' && ['sent', 'delivered', 'opened', 'clicked'].includes(rows[1].status), deliveries: rows }));
      process.exitCode = rows[0].status === 'failed' && ['sent', 'delivered', 'opened', 'clicked'].includes(rows[1].status) ? 0 : 1;
      break;
    }
    await delay(1000);
    if (attempt === 29) throw new Error('Timed out waiting for isolation test deliveries.');
  }
} finally {
  await closeDatabase();
}
