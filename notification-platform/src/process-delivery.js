import { sendEmail } from './email.js';
import { markDeliveryExpired, markDeliveryFailure, markDeliverySent } from './deliveries.js';
import { isExpiredPushError, sendPush } from './push.js';

export async function processDelivery(delivery, dependencies = {}) {
  const sendPushMessage = dependencies.sendPush || sendPush;
  const sendEmailMessage = dependencies.sendEmail || sendEmail;
  const markSent = dependencies.markSent || markDeliverySent;
  const markExpired = dependencies.markExpired || markDeliveryExpired;
  const markFailure = dependencies.markFailure || markDeliveryFailure;

  try {
    if (delivery.channel === 'push') {
      if (!delivery.endpoint) throw Object.assign(new Error('Push subscription is missing'), { code: 'missing_subscription' });
      const result = await sendPushMessage(delivery);
      await markSent(delivery, result.messageId);
    } else if (delivery.channel === 'email') {
      if (!delivery.email) throw Object.assign(new Error('Email subscription is missing'), { code: 'missing_subscription' });
      const result = await sendEmailMessage(delivery);
      await markSent(delivery, result.messageId);
    } else {
      throw Object.assign(new Error(`Unknown delivery channel: ${delivery.channel}`), { code: 'unknown_channel' });
    }
    return { successful: true };
  } catch (error) {
    if (delivery.channel === 'push' && isExpiredPushError(error)) {
      await markExpired(delivery, error.message);
      return { successful: false, expired: true, error };
    }
    await markFailure(delivery, error);
    return { successful: false, expired: false, error };
  }
}
