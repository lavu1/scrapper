import crypto from 'node:crypto';
import { db, transaction } from './db.js';
import { dueAtForFrequency, matchesFilters } from './matching.js';
import { sha256 } from './security.js';

function json(value, fallback = {}) {
  if (value === null || value === undefined) return fallback;
  return typeof value === 'string' ? JSON.parse(value) : value;
}

export async function createCampaign(tenant, input, kind = 'manual', externalId = null) {
  const publicId = crypto.randomUUID();
  const status = input.scheduledAt && new Date(input.scheduledAt) > new Date() ? 'scheduled' : 'pending';

  try {
    const [result] = await db.execute(
      `INSERT INTO campaigns
       (public_id, tenant_id, external_id, kind, title, body, target_url, icon_url, image_url,
        channels, audience_filters, content, status, scheduled_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        publicId,
        tenant.id,
        externalId,
        kind,
        input.title,
        input.body,
        input.targetUrl,
        input.iconUrl || null,
        input.imageUrl || null,
        JSON.stringify(input.channels),
        JSON.stringify(input.audienceFilters || {}),
        JSON.stringify(input.content || null),
        status,
        input.scheduledAt ? new Date(input.scheduledAt) : null,
      ],
    );
    return { id: result.insertId, publicId, status, duplicate: false };
  } catch (error) {
    if (error.code !== 'ER_DUP_ENTRY' || !externalId) throw error;
    const [rows] = await db.execute(
      'SELECT id, public_id, status FROM campaigns WHERE tenant_id = ? AND external_id = ? LIMIT 1',
      [tenant.id, externalId],
    );
    return { id: rows[0].id, publicId: rows[0].public_id, status: rows[0].status, duplicate: true };
  }
}

export async function enqueueCampaign(campaignId) {
  const [rows] = await db.execute(
    `SELECT c.*, t.name tenant_name, t.domain, t.logo_url, t.settings tenant_settings
     FROM campaigns c JOIN tenants t ON t.id = c.tenant_id WHERE c.id = ? LIMIT 1`,
    [campaignId],
  );
  const campaign = rows[0];
  if (!campaign || !['pending', 'scheduled'].includes(campaign.status)) return false;

  const channels = json(campaign.channels, []);
  const content = json(campaign.content, null);
  const audienceFilters = json(campaign.audience_filters, {});
  const basePayload = {
    type: 'campaign',
    title: campaign.title,
    body: campaign.body,
    targetUrl: campaign.target_url,
    iconUrl: campaign.icon_url,
    imageUrl: campaign.image_url,
    content: content ? [content] : [],
  };

  await db.execute(
    `UPDATE campaigns SET status = 'processing', processing_started_at = COALESCE(processing_started_at, NOW())
     WHERE id = ? AND status IN ('pending', 'scheduled')`,
    [campaign.id],
  );

  let queued = 0;
  let digestItems = 0;
  if (channels.includes('push')) {
    let lastId = 0;
    while (true) {
      const [subscriptions] = await db.execute(
        `SELECT * FROM push_subscriptions
         WHERE tenant_id = ? AND status = 'active' AND id > ? ORDER BY id LIMIT 500`,
        [campaign.tenant_id, lastId],
      );
      if (!subscriptions.length) break;
      for (const subscription of subscriptions) {
        lastId = subscription.id;
        const subscriberFilters = json(subscription.filters, {});
        if (content && !matchesFilters(content, subscriberFilters)) continue;
        if (audienceFilters.browser && !matchesFilters({ type: 'push', browser: subscription.browser }, audienceFilters)) continue;
        queued += await insertDelivery({
          tenantId: campaign.tenant_id,
          campaignId: campaign.id,
          channel: 'push',
          pushSubscriptionId: subscription.id,
          recipientHash: subscription.endpoint_hash,
          payload: basePayload,
        });
      }
    }
  }

  if (channels.includes('email')) {
    let lastId = 0;
    while (true) {
      const [subscriptions] = await db.execute(
        `SELECT * FROM email_subscriptions
         WHERE tenant_id = ? AND status = 'active' AND id > ? ORDER BY id LIMIT 500`,
        [campaign.tenant_id, lastId],
      );
      if (!subscriptions.length) break;
      for (const subscription of subscriptions) {
        lastId = subscription.id;
        if (content && !matchesFilters(content, json(subscription.filters, {}))) continue;

        if (campaign.kind === 'content' && subscription.frequency !== 'immediate') {
          const [result] = await db.execute(
            `INSERT IGNORE INTO email_digest_items
             (tenant_id, email_subscription_id, content_key, content, due_at)
             VALUES (?, ?, ?, ?, ?)`,
            [
              campaign.tenant_id,
              subscription.id,
              campaign.external_id || campaign.public_id,
              JSON.stringify(content),
              dueAtForFrequency(subscription.frequency),
            ],
          );
          digestItems += result.affectedRows;
          continue;
        }

        queued += await insertDelivery({
          tenantId: campaign.tenant_id,
          campaignId: campaign.id,
          channel: 'email',
          emailSubscriptionId: subscription.id,
          recipientHash: subscription.email_hash,
          payload: basePayload,
        });
      }
    }
  }

  await db.execute(
    `UPDATE campaigns SET total_count = ?, status = IF(? = 0, 'completed', 'processing'),
     completed_at = IF(? = 0, NOW(), NULL) WHERE id = ?`,
    [queued, queued, queued, campaign.id],
  );
  return { queued, digestItems };
}

export async function insertDelivery({
  tenantId,
  campaignId = null,
  channel,
  pushSubscriptionId = null,
  emailSubscriptionId = null,
  recipientHash,
  payload,
  availableAt = new Date(),
}) {
  try {
    const [result] = await db.execute(
      `INSERT INTO deliveries
       (public_id, tenant_id, campaign_id, channel, push_subscription_id,
        email_subscription_id, recipient_hash, payload, available_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        crypto.randomUUID(), tenantId, campaignId, channel, pushSubscriptionId,
        emailSubscriptionId, recipientHash, JSON.stringify(payload), availableAt,
      ],
    );
    return result.affectedRows;
  } catch (error) {
    if (error.code === 'ER_DUP_ENTRY') return 0;
    throw error;
  }
}

export async function enqueueDueCampaigns() {
  const [campaigns] = await db.execute(
    `SELECT id FROM campaigns
     WHERE status IN ('pending', 'scheduled') AND (scheduled_at IS NULL OR scheduled_at <= NOW())
     ORDER BY id LIMIT 20`,
  );
  for (const campaign of campaigns) await enqueueCampaign(campaign.id);
}

export async function enqueueDueDigests() {
  const [subscriptions] = await db.execute(
    `SELECT DISTINCT email_subscription_id FROM email_digest_items
     WHERE due_at <= NOW() AND claimed_at IS NULL ORDER BY email_subscription_id LIMIT 50`,
  );

  for (const row of subscriptions) {
    await transaction(async (connection) => {
      const [items] = await connection.execute(
        `SELECT d.*, e.tenant_id, e.email_hash
         FROM email_digest_items d JOIN email_subscriptions e ON e.id = d.email_subscription_id
         WHERE d.email_subscription_id = ? AND d.due_at <= NOW() AND d.claimed_at IS NULL
         ORDER BY d.id LIMIT 10 FOR UPDATE SKIP LOCKED`,
        [row.email_subscription_id],
      );
      if (!items.length) return;
      const publicId = crypto.randomUUID();
      const payload = {
        type: 'digest',
        title: `${items.length} new matches for you`,
        body: 'Your latest matching alerts are ready.',
        targetUrl: items[0].content.url,
        content: items.map((item) => json(item.content)),
      };
      const [delivery] = await connection.execute(
        `INSERT INTO deliveries
         (public_id, tenant_id, channel, email_subscription_id, recipient_hash, payload, available_at)
         VALUES (?, ?, 'email', ?, ?, ?, NOW())`,
        [publicId, items[0].tenant_id, row.email_subscription_id, items[0].email_hash, JSON.stringify(payload)],
      );
      const itemIds = items.map((item) => item.id);
      await connection.query(
        `UPDATE email_digest_items SET claimed_at = NOW(), delivery_id = ? WHERE id IN (${itemIds.map(() => '?').join(',')})`,
        [delivery.insertId, ...itemIds],
      );
    });
  }
}

export async function claimDelivery(workerId) {
  return transaction(async (connection) => {
    const [rows] = await connection.execute(
      `SELECT id FROM deliveries
       WHERE status IN ('queued', 'retrying') AND available_at <= NOW()
       ORDER BY available_at, id LIMIT 1 FOR UPDATE SKIP LOCKED`,
    );
    if (!rows.length) return null;
    await connection.execute(
      `UPDATE deliveries SET status = 'processing', attempts = attempts + 1,
       locked_at = NOW(), locked_by = ? WHERE id = ?`,
      [workerId, rows[0].id],
    );
    const [deliveries] = await connection.execute(
      `SELECT d.*, c.title campaign_title, c.target_url campaign_target_url,
              t.name tenant_name, t.domain, t.logo_url, t.vapid_subject,
              t.vapid_public_key, t.vapid_private_key, t.smtp_profile, t.settings tenant_settings,
              p.endpoint, p.p256dh, p.auth_token, p.content_encoding,
              e.email, e.name recipient_name, e.unsubscribe_token_hash
       FROM deliveries d
       JOIN tenants t ON t.id = d.tenant_id
       LEFT JOIN campaigns c ON c.id = d.campaign_id
       LEFT JOIN push_subscriptions p ON p.id = d.push_subscription_id
       LEFT JOIN email_subscriptions e ON e.id = d.email_subscription_id
       WHERE d.id = ? LIMIT 1`,
      [rows[0].id],
    );
    return deliveries[0] || null;
  });
}

export async function markDeliverySent(delivery, providerMessageId = null) {
  await db.execute(
    `UPDATE deliveries SET status = 'sent', provider_message_id = ?, sent_at = NOW(),
     locked_at = NULL, locked_by = NULL, error_code = NULL, error_message = NULL WHERE id = ?`,
    [providerMessageId, delivery.id],
  );
  if (delivery.push_subscription_id) {
    await db.execute(
      `UPDATE push_subscriptions SET failure_count = 0, last_success_at = NOW() WHERE id = ?`,
      [delivery.push_subscription_id],
    );
  }
  if (delivery.email_subscription_id) {
    await db.execute('UPDATE email_subscriptions SET last_sent_at = NOW() WHERE id = ?', [delivery.email_subscription_id]);
  }
  await recordDeliveryEvent(delivery.id, 'sent');
  await refreshCampaignCounts(delivery.campaign_id);
}

export async function markDeliveryExpired(delivery, message) {
  await db.execute(
    `UPDATE deliveries SET status = 'expired', failed_at = NOW(), error_code = 'expired_subscription',
     error_message = ?, locked_at = NULL, locked_by = NULL WHERE id = ?`,
    [String(message).slice(0, 2000), delivery.id],
  );
  if (delivery.push_subscription_id) {
    await db.execute(
      `UPDATE push_subscriptions SET status = 'expired', expired_at = NOW(), failure_count = failure_count + 1
       WHERE id = ?`,
      [delivery.push_subscription_id],
    );
  }
  await recordDeliveryEvent(delivery.id, 'expired', { message: String(message).slice(0, 500) });
  await refreshCampaignCounts(delivery.campaign_id);
}

export async function markDeliveryFailure(delivery, error) {
  const retry = delivery.attempts < delivery.max_attempts;
  const delaySeconds = Math.min(3600, 30 * (2 ** Math.max(0, delivery.attempts - 1)));
  await db.execute(
    `UPDATE deliveries SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
     error_code = ?, error_message = ?, failed_at = IF(? = 'failed', NOW(), failed_at),
     locked_at = NULL, locked_by = NULL WHERE id = ?`,
    [
      retry ? 'retrying' : 'failed', delaySeconds, error.code || error.name || 'delivery_error',
      String(error.message || error).slice(0, 2000), retry ? 'retrying' : 'failed', delivery.id,
    ],
  );
  if (delivery.push_subscription_id) {
    await db.execute('UPDATE push_subscriptions SET failure_count = failure_count + 1 WHERE id = ?', [delivery.push_subscription_id]);
  }
  await recordDeliveryEvent(delivery.id, retry ? 'retrying' : 'failed', {
    attempt: delivery.attempts,
    message: String(error.message || error).slice(0, 500),
  });
  await refreshCampaignCounts(delivery.campaign_id);
}

export async function recordDeliveryEvent(deliveryId, event, metadata = {}) {
  await db.execute(
    'INSERT INTO delivery_events (delivery_id, event, metadata) VALUES (?, ?, ?)',
    [deliveryId, event, JSON.stringify(metadata)],
  );
}

export async function refreshCampaignCounts(campaignId) {
  if (!campaignId) return;
  await db.execute(
    `UPDATE campaigns c SET
       sent_count = (SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.status IN ('sent','delivered','opened','clicked')),
       failed_count = (SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.status IN ('failed','expired','bounced')),
       delivered_count = (SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.delivered_at IS NOT NULL),
       opened_count = (SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.opened_at IS NOT NULL),
       clicked_count = (SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.clicked_at IS NOT NULL),
       status = IF((SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.status IN ('queued','retrying','processing')) = 0, 'completed', c.status),
       completed_at = IF((SELECT COUNT(*) FROM deliveries d WHERE d.campaign_id = c.id AND d.status IN ('queued','retrying','processing')) = 0, COALESCE(c.completed_at, NOW()), c.completed_at)
     WHERE c.id = ?`,
    [campaignId],
  );
}

export async function retryDelivery(publicId) {
  const [result] = await db.execute(
    `UPDATE deliveries SET status = 'queued', attempts = 0, available_at = NOW(), failed_at = NULL,
     error_code = NULL, error_message = NULL WHERE public_id = ? AND status IN ('failed','expired','bounced')`,
    [publicId],
  );
  return result.affectedRows === 1;
}

export function recipientHash(value) {
  return sha256(String(value).trim().toLowerCase());
}
