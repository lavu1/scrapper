import { config } from './config.js';
import { sha256, signedToken } from './security.js';

export function deliveredUrl(delivery) {
  const value = `${delivery.public_id}:delivered`;
  return `${config.publicBaseUrl}/v1/t/${delivery.public_id}/delivered?token=${encodeURIComponent(signedToken(value))}`;
}

export function openPixelUrl(delivery) {
  const value = `${delivery.public_id}:opened`;
  return `${config.publicBaseUrl}/v1/t/${delivery.public_id}/open.gif?token=${encodeURIComponent(signedToken(value))}`;
}

export function clickUrl(delivery, targetUrl) {
  const value = `${delivery.public_id}:clicked:${sha256(targetUrl)}`;
  return `${config.publicBaseUrl}/v1/t/${delivery.public_id}/click?url=${encodeURIComponent(targetUrl)}&token=${encodeURIComponent(signedToken(value))}`;
}

export function unsubscribeUrl(delivery) {
  const value = `${delivery.email_subscription_id}:unsubscribe`;
  return `${config.publicBaseUrl}/v1/email/${delivery.email_subscription_id}/unsubscribe?token=${encodeURIComponent(signedToken(value))}`;
}
