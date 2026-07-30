import nodemailer from 'nodemailer';
import { config } from './config.js';
import { clickUrl, openPixelUrl, preferencesUrl, unsubscribeUrl } from './tracking.js';

const transports = new Map();

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function parse(value, fallback = {}) {
  if (value === undefined || value === null) return fallback;
  return typeof value === 'string' ? JSON.parse(value) : value;
}

function transport(profileName) {
  if (transports.has(profileName)) return transports.get(profileName);
  const profile = config.smtpProfiles[profileName];
  if (!profile?.host || !profile.user || !profile.password || !profile.fromAddress) {
    throw Object.assign(new Error(`SMTP profile ${profileName || '(none)'} is not configured`), { code: 'smtp_not_configured' });
  }
  const value = nodemailer.createTransport({
    host: profile.host,
    port: profile.port,
    secure: profile.secure,
    auth: { user: profile.user, pass: profile.password },
    pool: true,
    maxConnections: 3,
    maxMessages: 100,
  });
  transports.set(profileName, value);
  return value;
}

function contentCard(delivery, item, color) {
  const destination = clickUrl(delivery, item.url || parse(delivery.payload).targetUrl);
  const details = [item.company, item.location, item.jobType, item.category, item.funding]
    .filter(Boolean)
    .map((value) => `<span style="display:inline-block;margin:0 6px 6px 0;padding:6px 9px;border-radius:6px;background:#f1f5f9;color:#334155;font-size:12px;">${escapeHtml(value)}</span>`)
    .join('');
  const image = item.imageUrl
    ? `<img src="${escapeHtml(item.imageUrl)}" alt="" width="640" style="display:block;width:100%;max-height:280px;object-fit:cover;border:0;">`
    : '';
  return `
    <tr><td style="padding:16px 24px 0;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dbe3ef;border-radius:10px;overflow:hidden;background:#ffffff;">
        ${image ? `<tr><td>${image}</td></tr>` : ''}
        <tr><td style="padding:20px 20px 22px;">
          <h2 style="margin:0 0 8px;font-size:21px;line-height:28px;color:#0f172a;">${escapeHtml(item.title)}</h2>
          <div style="margin:0 0 8px;">${details}</div>
          <p style="margin:8px 0 18px;font-size:14px;line-height:22px;color:#475569;">${escapeHtml(item.description || '')}</p>
          <a href="${escapeHtml(destination)}" style="display:inline-block;background:${escapeHtml(color)};color:#ffffff;text-decoration:none;font-weight:700;padding:12px 17px;border-radius:8px;">View details</a>
        </td></tr>
      </table>
    </td></tr>`;
}

export function renderEmail(delivery) {
  const payload = parse(delivery.payload);
  const settings = parse(delivery.tenant_settings);
  const color = settings.color || '#2563eb';
  const items = payload.content?.length
    ? payload.content
    : [{ title: payload.title, description: payload.body, url: payload.targetUrl, imageUrl: payload.imageUrl }];
  const appButtons = [
    settings.androidUrl ? `<a href="${escapeHtml(clickUrl(delivery, settings.androidUrl))}" style="display:inline-block;margin:0 8px 8px 0;padding:10px 13px;border-radius:7px;background:#0f172a;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">Download Android app</a>` : '',
    settings.iosUrl ? `<a href="${escapeHtml(clickUrl(delivery, settings.iosUrl))}" style="display:inline-block;margin:0 0 8px;padding:9px 13px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#0f172a;text-decoration:none;font-size:13px;font-weight:700;">Download iPhone app</a>` : '',
  ].join('');
  const preferenceLink = preferencesUrl(delivery);
  const websiteUrl = `https://${delivery.domain}`;
  const socialLinks = [
    ['Facebook', `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(websiteUrl)}`],
    ['X', `https://twitter.com/intent/tweet?url=${encodeURIComponent(websiteUrl)}`],
    ['LinkedIn', `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(websiteUrl)}`],
  ].map(([label, url]) => `<a href="${escapeHtml(clickUrl(delivery, url))}" style="color:${escapeHtml(color)};text-decoration:none;">${label}</a>`).join(' &nbsp;|&nbsp; ');

  return `<!doctype html>
  <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(payload.title)}</title></head>
  <body style="margin:0;background:#f1f5f9;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:24px 8px;"><tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #dbe3ef;">
        <tr><td style="background:${escapeHtml(color)};padding:24px;color:#ffffff;">
          ${delivery.logo_url ? `<img src="${escapeHtml(delivery.logo_url)}" alt="${escapeHtml(delivery.tenant_name)}" width="150" style="display:block;max-width:150px;max-height:56px;object-fit:contain;margin:0 0 14px;border:0;">` : ''}
          <div style="font-size:13px;font-weight:700;opacity:.85;letter-spacing:.05em;text-transform:uppercase;">${escapeHtml(delivery.tenant_name)}</div>
          <h1 style="margin:8px 0 0;font-size:27px;line-height:34px;">${escapeHtml(payload.title)}</h1>
          <p style="margin:9px 0 0;font-size:15px;line-height:23px;opacity:.92;">${escapeHtml(payload.body)}</p>
        </td></tr>
        ${items.slice(0, 10).map((item) => contentCard(delivery, item, color)).join('')}
        <tr><td style="padding:22px 24px;">${appButtons}</td></tr>
        <tr><td style="padding:18px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:19px;color:#64748b;">
          <div style="margin-bottom:8px;">Share: ${socialLinks}</div>
          &copy; ${new Date().getUTCFullYear()} ${escapeHtml(delivery.tenant_name)}. All rights reserved.<br>
          <a href="${escapeHtml(clickUrl(delivery, websiteUrl))}" style="color:${escapeHtml(color)};">Visit website</a>
          &nbsp;|&nbsp; <a href="${escapeHtml(preferenceLink)}" style="color:${escapeHtml(color)};">Notification preferences or unsubscribe</a>
          <img src="${escapeHtml(openPixelUrl(delivery))}" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">
        </td></tr>
      </table>
    </td></tr></table>
  </body></html>`;
}

export async function sendEmail(delivery) {
  const payload = parse(delivery.payload);
  const profile = config.smtpProfiles[delivery.smtp_profile];
  const unsubscribe = unsubscribeUrl(delivery);
  const info = await transport(delivery.smtp_profile).sendMail({
    from: { name: delivery.tenant_name, address: profile.fromAddress },
    to: { name: delivery.recipient_name || undefined, address: delivery.email },
    subject: payload.title,
    html: renderEmail(delivery),
    headers: {
      'List-Unsubscribe': `<${unsubscribe}>`,
      'List-Unsubscribe-Post': 'List-Unsubscribe=One-Click',
      'X-Notification-Delivery': delivery.public_id,
    },
  });
  return { messageId: info.messageId || null, accepted: info.accepted || [] };
}

export async function verifyEmailTransport(profileName) {
  return transport(profileName).verify();
}
