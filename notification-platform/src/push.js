import webpush from 'web-push';
import { clickUrl, deliveredUrl } from './tracking.js';

function parse(value, fallback = {}) {
  if (value === undefined || value === null) return fallback;
  return typeof value === 'string' ? JSON.parse(value) : value;
}

export async function sendPush(delivery) {
  const payload = parse(delivery.payload);
  const settings = parse(delivery.tenant_settings);
  webpush.setVapidDetails(
    delivery.vapid_subject,
    delivery.vapid_public_key,
    delivery.vapid_private_key,
  );

  const result = await webpush.sendNotification(
    {
      endpoint: delivery.endpoint,
      keys: { p256dh: delivery.p256dh, auth: delivery.auth_token },
    },
    JSON.stringify({
      title: payload.title,
      body: payload.body,
      icon: payload.iconUrl || delivery.logo_url,
      image: payload.imageUrl || undefined,
      badge: settings.badgeUrl || delivery.logo_url,
      tag: delivery.campaign_id ? `campaign-${delivery.campaign_id}` : `delivery-${delivery.id}`,
      deliveredUrl: deliveredUrl(delivery),
      clickUrl: clickUrl(delivery, payload.targetUrl),
    }),
    {
      TTL: 86_400,
      urgency: 'normal',
      topic: delivery.campaign_id ? `c${delivery.campaign_id}`.slice(0, 32) : undefined,
      contentEncoding: delivery.content_encoding || 'aes128gcm',
    },
  );

  return {
    messageId: result?.headers?.location || null,
    statusCode: result?.statusCode || 201,
  };
}

export function isExpiredPushError(error) {
  return [404, 410].includes(Number(error?.statusCode));
}
