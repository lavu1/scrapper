import crypto from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import { rateLimit } from 'express-rate-limit';
import { UAParser } from 'ua-parser-js';
import { assertProductionConfig, config } from './config.js';
import { db } from './db.js';
import {
  campaignSchema,
  contentPublishedSchema,
  emailSubscriptionSchema,
  parse,
  pushSubscriptionSchema,
  syncedEmailSubscriptionSchema,
  unsubscribePushSchema,
} from './validation.js';
import { allTenants, originAllowed, tenantByKey, webhookAuthorized } from './tenants.js';
import {
  createCampaign,
  insertDelivery,
  recipientHash,
  recordDeliveryEvent,
  refreshCampaignCounts,
  retryDelivery,
} from './deliveries.js';
import {
  hashIp,
  randomToken,
  safeEqual,
  sha256,
  sign,
  signedToken,
  verifyPassword,
  verifySignedToken,
} from './security.js';

assertProductionConfig();
const app = express();
app.set('trust proxy', 1);
app.disable('x-powered-by');
app.use((request, response, next) => {
  const address = String(request.socket.remoteAddress || '').replace(/^::ffff:/, '');
  if (!config.allowedProxyIps.includes(address)) return response.status(403).json({ error: 'Direct access is not allowed' });
  next();
});
app.use(helmet({ contentSecurityPolicy: false, crossOriginResourcePolicy: { policy: 'cross-origin' } }));
app.use(express.json({ limit: '64kb' }));
app.use(express.urlencoded({ extended: false, limit: '64kb' }));
app.use('/assets', express.static(path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../public'), {
  etag: true,
  maxAge: '1h',
  immutable: false,
}));

const publicOrigins = new Set([
  'https://zambiajobalerts.com', 'https://www.zambiajobalerts.com',
  'https://primejobalerts.com', 'https://www.primejobalerts.com',
  'https://primescholarshipalerts.com', 'https://www.primescholarshipalerts.com',
  'https://zinstablog.com', 'https://www.zinstablog.com',
]);
app.use('/v1', cors({
  origin(origin, callback) {
    callback(null, !origin || publicOrigins.has(origin));
  },
  methods: ['GET', 'POST', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Accept', 'X-Notification-Key'],
  maxAge: 86400,
}));

const subscribeLimiter = rateLimit({ windowMs: 60_000, limit: 20, standardHeaders: 'draft-8', legacyHeaders: false });
const emailLimiter = rateLimit({ windowMs: 60 * 60_000, limit: 10, standardHeaders: 'draft-8', legacyHeaders: false });

function asyncRoute(handler) {
  return (request, response, next) => Promise.resolve(handler(request, response, next)).catch(next);
}

function json(value, fallback = {}) {
  if (value === null || value === undefined) return fallback;
  return typeof value === 'string' ? JSON.parse(value) : value;
}

async function publicTenant(request, site) {
  const tenant = await tenantByKey(site);
  if (!tenant) throw Object.assign(new Error('Unknown notification site'), { status: 404 });
  if (!originAllowed(tenant, request.get('origin'))) {
    throw Object.assign(new Error('Origin is not allowed for this site'), { status: 403 });
  }
  return tenant;
}

async function internalTenant(request, site) {
  const tenant = await tenantByKey(site);
  if (!tenant || !webhookAuthorized(tenant, request.get('x-notification-key'))) {
    throw Object.assign(new Error('Invalid notification webhook credentials'), { status: 401 });
  }
  return tenant;
}

app.get('/health', asyncRoute(async (_request, response) => {
  await db.query('SELECT 1');
  response.json({ status: 'ok', service: 'notification-platform' });
}));

app.get('/v1/config', asyncRoute(async (request, response) => {
  const site = String(request.query.site || '');
  const tenant = await publicTenant(request, site);
  response.set('Cache-Control', 'public, max-age=300');
  response.json({
    site: tenant.key,
    name: tenant.name,
    publicKey: tenant.vapid_public_key,
    logoUrl: tenant.logo_url,
  });
}));

app.post('/v1/push/subscriptions', subscribeLimiter, asyncRoute(async (request, response) => {
  const input = parse(pushSubscriptionSchema, request.body);
  const tenant = await publicTenant(request, input.site);
  const ua = UAParser(request.get('user-agent') || '');
  const publicId = crypto.randomUUID();
  const endpointHash = sha256(input.endpoint);

  await db.execute(
    `INSERT INTO push_subscriptions
     (public_id, tenant_id, endpoint_hash, endpoint, p256dh, auth_token, content_encoding,
      filters, status, failure_count, user_agent, browser, device, platform, ip_hash,
      last_seen_at, expired_at, unsubscribed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, ?, ?, ?, ?, ?, NOW(), NULL, NULL)
     ON DUPLICATE KEY UPDATE
       public_id = VALUES(public_id), endpoint = VALUES(endpoint), p256dh = VALUES(p256dh),
       auth_token = VALUES(auth_token), content_encoding = VALUES(content_encoding),
       filters = VALUES(filters), status = 'active', failure_count = 0,
       user_agent = VALUES(user_agent), browser = VALUES(browser), device = VALUES(device),
       platform = VALUES(platform), ip_hash = VALUES(ip_hash), last_seen_at = NOW(),
       expired_at = NULL, unsubscribed_at = NULL`,
    [
      publicId, tenant.id, endpointHash, input.endpoint, input.keys.p256dh, input.keys.auth,
      input.contentEncoding, JSON.stringify(input.filters), request.get('user-agent') || null,
      ua.browser.name || 'Unknown', ua.device.type || 'desktop', ua.os.name || 'Unknown', hashIp(request.ip),
    ],
  );
  response.status(201).json({ subscribed: true, id: publicId });
}));

app.delete('/v1/push/subscriptions', subscribeLimiter, asyncRoute(async (request, response) => {
  const input = parse(unsubscribePushSchema, request.body);
  const tenant = await publicTenant(request, input.site);
  await db.execute(
    `UPDATE push_subscriptions SET status = 'unsubscribed', unsubscribed_at = NOW()
     WHERE tenant_id = ? AND endpoint_hash = ?`,
    [tenant.id, sha256(input.endpoint)],
  );
  response.json({ unsubscribed: true });
}));

app.post('/v1/email/subscriptions', emailLimiter, asyncRoute(async (request, response) => {
  const input = parse(emailSubscriptionSchema, request.body);
  const tenant = await publicTenant(request, input.site);
  const emailHash = recipientHash(input.email);
  const verifyToken = randomToken();
  const unsubscribeToken = randomToken();
  const publicId = crypto.randomUUID();

  await db.execute(
    `INSERT INTO email_subscriptions
     (public_id, tenant_id, email_hash, email, name, filters, frequency, status,
      verification_token_hash, unsubscribe_token_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
     ON DUPLICATE KEY UPDATE
       public_id = VALUES(public_id), name = VALUES(name), filters = VALUES(filters),
       frequency = VALUES(frequency), status = IF(status = 'active', 'active', 'pending'),
       verification_token_hash = IF(status = 'active', verification_token_hash, VALUES(verification_token_hash)),
       unsubscribe_token_hash = VALUES(unsubscribe_token_hash), unsubscribed_at = NULL`,
    [
      publicId, tenant.id, emailHash, input.email, input.name || null, JSON.stringify(input.filters),
      input.frequency, sha256(verifyToken), sha256(unsubscribeToken),
    ],
  );
  const [subscriptions] = await db.execute(
    'SELECT id, public_id, status FROM email_subscriptions WHERE tenant_id = ? AND email_hash = ? LIMIT 1',
    [tenant.id, emailHash],
  );
  const subscription = subscriptions[0];
  if (subscription.status !== 'active') {
    const verifyUrl = `${config.publicBaseUrl}/v1/email/${subscription.public_id}/verify?token=${encodeURIComponent(verifyToken)}`;
    await insertDelivery({
      tenantId: tenant.id,
      channel: 'email',
      emailSubscriptionId: subscription.id,
      recipientHash: emailHash,
      payload: {
        type: 'verification',
        title: `Confirm your ${tenant.name} alerts`,
        body: 'Confirm your email address to start receiving matching alerts.',
        targetUrl: verifyUrl,
        content: [{ title: 'Confirm email alerts', description: 'Use the button below to confirm your subscription.', url: verifyUrl }],
      },
    });
  }
  response.status(202).json({ subscribed: subscription.status === 'active', verificationQueued: subscription.status !== 'active' });
}));

app.get('/v1/email/:publicId/verify', asyncRoute(async (request, response) => {
  const [rows] = await db.execute('SELECT * FROM email_subscriptions WHERE public_id = ? LIMIT 1', [request.params.publicId]);
  const subscription = rows[0];
  if (!subscription || !request.query.token || !safeEqual(subscription.verification_token_hash || '', sha256(request.query.token))) {
    return response.status(400).send(messagePage('Invalid or expired verification link', 'This verification link is no longer valid.'));
  }
  await db.execute(
    `UPDATE email_subscriptions SET status = 'active', verified_at = COALESCE(verified_at, NOW()),
     verification_token_hash = NULL, unsubscribed_at = NULL WHERE id = ?`,
    [subscription.id],
  );
  response.send(messagePage('Email alerts confirmed', 'Your notification preferences are now active.'));
}));

app.get('/v1/email/:id/unsubscribe', asyncRoute(async (request, response) => {
  const value = `${request.params.id}:unsubscribe`;
  if (verifySignedToken(String(request.query.token || '')) !== value) {
    return response.status(400).send(messagePage('Invalid unsubscribe link', 'This unsubscribe link is no longer valid.'));
  }
  response.send(unsubscribePage(request.params.id, String(request.query.token)));
}));

app.post('/v1/email/:id/unsubscribe', asyncRoute(async (request, response) => {
  const value = `${request.params.id}:unsubscribe`;
  if (verifySignedToken(String(request.query.token || request.body.token || '')) !== value) {
    return response.status(400).send(messagePage('Invalid unsubscribe link', 'This unsubscribe link is no longer valid.'));
  }
  await db.execute(
    `UPDATE email_subscriptions SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?`,
    [request.params.id],
  );
  response.send(messagePage('You are unsubscribed', 'You will no longer receive email alerts from this site.'));
}));

app.all('/v1/email/:id/preferences', asyncRoute(async (request, response) => {
  const value = `${request.params.id}:preferences`;
  const token = String(request.query.token || request.body.token || '');
  if (verifySignedToken(token) !== value) {
    return response.status(400).send(messagePage('Invalid preferences link', 'This preferences link is no longer valid.'));
  }
  const [rows] = await db.execute(
    `SELECT e.*, t.name tenant_name, t.domain FROM email_subscriptions e
     JOIN tenants t ON t.id = e.tenant_id WHERE e.id = ? LIMIT 1`,
    [request.params.id],
  );
  const subscription = rows[0];
  if (!subscription) return response.status(404).send(messagePage('Subscription not found', 'This email subscription no longer exists.'));

  if (request.method === 'POST') {
    const allowedFrequencies = new Set(['immediate', 'daily', 'weekly']);
    const frequency = allowedFrequencies.has(request.body.frequency) ? request.body.frequency : 'immediate';
    const updatedFilters = {};
    for (const key of ['keyword', 'location', 'jobType', 'category']) {
      const item = String(request.body[key] || '').trim().slice(0, 255);
      if (item) updatedFilters[key] = item;
    }
    await db.execute('UPDATE email_subscriptions SET filters = ?, frequency = ? WHERE id = ?', [JSON.stringify(updatedFilters), frequency, subscription.id]);
    subscription.filters = updatedFilters;
    subscription.frequency = frequency;
  }
  response.send(preferencesPage(subscription, token, request.method === 'POST'));
}));

app.post('/v1/internal/email-subscriptions/sync', asyncRoute(async (request, response) => {
  const input = parse(syncedEmailSubscriptionSchema, request.body);
  const tenant = await internalTenant(request, input.site);
  const emailHash = recipientHash(input.email);
  const subscriptionStatus = input.status || (input.verified ? 'active' : 'pending');
  await db.execute(
    `INSERT INTO email_subscriptions
     (public_id, tenant_id, email_hash, email, name, filters, frequency, status,
      verification_token_hash, unsubscribe_token_hash, verified_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, IF(? = 1, NOW(), NULL))
     ON DUPLICATE KEY UPDATE email = VALUES(email), name = VALUES(name), filters = VALUES(filters),
       frequency = VALUES(frequency), status = VALUES(status), verified_at = COALESCE(verified_at, VALUES(verified_at)),
       unsubscribed_at = IF(VALUES(status) = 'unsubscribed', NOW(), NULL)`,
    [
      crypto.randomUUID(), tenant.id, emailHash, input.email, input.name || null, JSON.stringify(input.filters),
      input.frequency, subscriptionStatus, sha256(randomToken()), subscriptionStatus === 'active' ? 1 : 0,
    ],
  );
  response.json({ synced: true });
}));

app.post('/v1/internal/content-published', asyncRoute(async (request, response) => {
  const input = parse(contentPublishedSchema, request.body);
  const tenant = await internalTenant(request, input.site);
  const campaign = await createCampaign(tenant, {
    title: `New ${input.content.type}: ${input.content.title}`,
    body: input.content.description || `A new ${input.content.type} has been published.`,
    targetUrl: input.content.url,
    imageUrl: input.content.imageUrl,
    iconUrl: tenant.logo_url,
    channels: input.channels,
    audienceFilters: {},
    content: input.content,
  }, 'content', input.eventId);
  response.status(campaign.duplicate ? 200 : 202).json(campaign);
}));

app.post('/v1/t/:publicId/delivered', asyncRoute(async (request, response) => {
  const value = `${request.params.publicId}:delivered`;
  if (verifySignedToken(String(request.query.token || '')) !== value) return response.status(403).end();
  const [rows] = await db.execute('SELECT id, campaign_id FROM deliveries WHERE public_id = ? LIMIT 1', [request.params.publicId]);
  if (!rows.length) return response.status(404).end();
  await db.execute(
    `UPDATE deliveries SET status = IF(status = 'sent', 'delivered', status),
     delivered_at = COALESCE(delivered_at, NOW()) WHERE id = ?`,
    [rows[0].id],
  );
  await recordDeliveryEvent(rows[0].id, 'delivered');
  await refreshCampaignCounts(rows[0].campaign_id);
  response.status(204).end();
}));

const transparentGif = Buffer.from('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', 'base64');
app.get('/v1/t/:publicId/open.gif', asyncRoute(async (request, response) => {
  const value = `${request.params.publicId}:opened`;
  if (verifySignedToken(String(request.query.token || '')) === value) {
    const [rows] = await db.execute('SELECT id, campaign_id FROM deliveries WHERE public_id = ? LIMIT 1', [request.params.publicId]);
    if (rows.length) {
      await db.execute(
        `UPDATE deliveries SET status = IF(status IN ('sent','delivered'), 'opened', status),
         opened_at = COALESCE(opened_at, NOW()) WHERE id = ?`,
        [rows[0].id],
      );
      await recordDeliveryEvent(rows[0].id, 'opened');
      await refreshCampaignCounts(rows[0].campaign_id);
    }
  }
  response.set({ 'Content-Type': 'image/gif', 'Cache-Control': 'no-store, private' }).send(transparentGif);
}));

app.get('/v1/t/:publicId/click', asyncRoute(async (request, response) => {
  const target = String(request.query.url || '');
  let parsed;
  try { parsed = new URL(target); } catch { return response.status(400).send('Invalid target URL'); }
  if (!['https:', 'http:'].includes(parsed.protocol)) return response.status(400).send('Invalid target URL');
  const value = `${request.params.publicId}:clicked:${sha256(target)}`;
  if (verifySignedToken(String(request.query.token || '')) !== value) return response.status(403).send('Invalid tracking token');
  const [rows] = await db.execute('SELECT id, campaign_id FROM deliveries WHERE public_id = ? LIMIT 1', [request.params.publicId]);
  if (rows.length) {
    await db.execute(
      `UPDATE deliveries SET status = 'clicked', clicked_at = COALESCE(clicked_at, NOW()) WHERE id = ?`,
      [rows[0].id],
    );
    await recordDeliveryEvent(rows[0].id, 'clicked');
    await refreshCampaignCounts(rows[0].campaign_id);
  }
  response.redirect(303, target);
}));

function adminAuth(request, response, next) {
  const authorization = request.get('authorization') || '';
  if (!authorization.startsWith('Basic ')) {
    response.set('WWW-Authenticate', 'Basic realm="Notification Platform", charset="UTF-8"');
    return response.status(401).send('Authentication required');
  }
  const decoded = Buffer.from(authorization.slice(6), 'base64').toString('utf8');
  const separator = decoded.indexOf(':');
  const user = separator >= 0 ? decoded.slice(0, separator) : '';
  const password = separator >= 0 ? decoded.slice(separator + 1) : '';
  if (!safeEqual(user, config.admin.user) || !verifyPassword(password, config.admin.passwordHash)) {
    response.set('WWW-Authenticate', 'Basic realm="Notification Platform", charset="UTF-8"');
    return response.status(401).send('Invalid credentials');
  }
  next();
}

function adminCsrf(request, response, next) {
  const expected = sign('admin-csrf-v1');
  if (!safeEqual(request.body.csrf || '', expected)) return response.status(403).send('Invalid form token');
  next();
}

app.get('/admin', adminAuth, asyncRoute(async (request, response) => {
  const tenants = await allTenants();
  const selected = String(request.query.site || '');
  const tenant = tenants.find((item) => item.key === selected) || null;
  const params = tenant ? [tenant.id] : [];
  const tenantWhere = tenant ? 'WHERE tenant_id = ?' : '';
  const [pushStats] = await db.execute(`SELECT status, COUNT(*) count FROM push_subscriptions ${tenantWhere} GROUP BY status`, params);
  const [emailStats] = await db.execute(`SELECT status, COUNT(*) count FROM email_subscriptions ${tenantWhere} GROUP BY status`, params);
  const [campaigns] = await db.execute(
    `SELECT c.*, t.name tenant_name FROM campaigns c JOIN tenants t ON t.id = c.tenant_id
     ${tenant ? 'WHERE c.tenant_id = ?' : ''} ORDER BY c.id DESC LIMIT 30`,
    params,
  );
  const [deliveries] = await db.execute(
    `SELECT d.*, t.name tenant_name, c.title campaign_title FROM deliveries d
     JOIN tenants t ON t.id = d.tenant_id LEFT JOIN campaigns c ON c.id = d.campaign_id
     ${tenant ? 'WHERE d.tenant_id = ?' : ''} ORDER BY d.id DESC LIMIT 50`,
    params,
  );
  const filters = {
    q: String(request.query.q || '').trim().slice(0, 120),
    browser: String(request.query.browser || '').trim().slice(0, 80),
    device: String(request.query.device || '').trim().slice(0, 80),
    from: /^\d{4}-\d{2}-\d{2}$/.test(String(request.query.from || '')) ? String(request.query.from) : '',
    to: /^\d{4}-\d{2}-\d{2}$/.test(String(request.query.to || '')) ? String(request.query.to) : '',
  };
  const pushWhere = [];
  const pushParams = [];
  if (tenant) { pushWhere.push('p.tenant_id = ?'); pushParams.push(tenant.id); }
  if (filters.q) { pushWhere.push('(p.public_id LIKE ? OR p.browser LIKE ? OR p.device LIKE ? OR p.platform LIKE ?)'); pushParams.push(...Array(4).fill(`%${filters.q}%`)); }
  if (filters.browser) { pushWhere.push('p.browser = ?'); pushParams.push(filters.browser); }
  if (filters.device) { pushWhere.push('p.device = ?'); pushParams.push(filters.device); }
  if (filters.from) { pushWhere.push('p.created_at >= ?'); pushParams.push(`${filters.from} 00:00:00`); }
  if (filters.to) { pushWhere.push('p.created_at < DATE_ADD(?, INTERVAL 1 DAY)'); pushParams.push(`${filters.to} 00:00:00`); }
  const emailWhere = [];
  const emailParams = [];
  if (tenant) { emailWhere.push('e.tenant_id = ?'); emailParams.push(tenant.id); }
  if (filters.q) { emailWhere.push('(e.public_id LIKE ? OR e.email LIKE ? OR e.name LIKE ?)'); emailParams.push(...Array(3).fill(`%${filters.q}%`)); }
  if (filters.browser || filters.device) emailWhere.push('1 = 0');
  if (filters.from) { emailWhere.push('e.created_at >= ?'); emailParams.push(`${filters.from} 00:00:00`); }
  if (filters.to) { emailWhere.push('e.created_at < DATE_ADD(?, INTERVAL 1 DAY)'); emailParams.push(`${filters.to} 00:00:00`); }
  const [pushSubscribers] = await db.execute(
    `SELECT p.public_id, t.name tenant_name, 'push' channel, p.status, p.browser detail,
            p.device, p.platform, p.last_seen_at last_active_at, p.created_at
     FROM push_subscriptions p JOIN tenants t ON t.id = p.tenant_id
     ${pushWhere.length ? `WHERE ${pushWhere.join(' AND ')}` : ''} ORDER BY p.id DESC LIMIT 100`,
    pushParams,
  );
  const [emailSubscribers] = await db.execute(
    `SELECT e.public_id, t.name tenant_name, 'email' channel, e.status, e.email detail,
            NULL device, NULL platform, COALESCE(e.last_active_at, e.last_sent_at, e.verified_at) last_active_at, e.created_at
     FROM email_subscriptions e JOIN tenants t ON t.id = e.tenant_id
     ${emailWhere.length ? `WHERE ${emailWhere.join(' AND ')}` : ''} ORDER BY e.id DESC LIMIT 100`,
    emailParams,
  );
  const [dailyStats] = await db.execute(
    `SELECT DATE(d.created_at) day, d.channel,
            COUNT(*) total,
            SUM(d.status IN ('sent','delivered','opened','clicked')) successful,
            SUM(d.status IN ('failed','expired','bounced')) failed,
            SUM(d.opened_at IS NOT NULL) opened,
            SUM(d.clicked_at IS NOT NULL) clicked
     FROM deliveries d
     WHERE d.created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) ${tenant ? 'AND d.tenant_id = ?' : ''}
     GROUP BY DATE(d.created_at), d.channel ORDER BY day`,
    tenant ? [tenant.id] : [],
  );
  const [deliveryStats] = await db.execute(
    `SELECT status, COUNT(*) count FROM deliveries ${tenant ? 'WHERE tenant_id = ?' : ''} GROUP BY status`, params,
  );
  const [browsers] = await db.execute(
    `SELECT DISTINCT browser FROM push_subscriptions WHERE browser IS NOT NULL ${tenant ? 'AND tenant_id = ?' : ''} ORDER BY browser`, params,
  );
  const [devices] = await db.execute(
    `SELECT DISTINCT device FROM push_subscriptions WHERE device IS NOT NULL ${tenant ? 'AND tenant_id = ?' : ''} ORDER BY device`, params,
  );
  const subscribers = [...pushSubscribers, ...emailSubscribers]
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 100);
  response.send(adminPage({
    tenants, tenant, pushStats, emailStats, campaigns, deliveries, deliveryStats, subscribers,
    dailyStats, browsers, devices, filters, csrf: sign('admin-csrf-v1'),
  }));
}));

app.post('/admin/campaigns', adminAuth, adminCsrf, asyncRoute(async (request, response) => {
  const input = parse(campaignSchema, {
    site: request.body.site,
    title: request.body.title,
    body: request.body.body,
    targetUrl: request.body.targetUrl,
    iconUrl: request.body.iconUrl || null,
    imageUrl: request.body.imageUrl || null,
    channels: Array.isArray(request.body.channels) ? request.body.channels : [request.body.channels].filter(Boolean),
    scheduledAt: request.body.scheduledAt ? new Date(request.body.scheduledAt).toISOString() : null,
    audienceFilters: {},
  });
  const tenant = await tenantByKey(input.site);
  if (!tenant) throw Object.assign(new Error('Unknown tenant'), { status: 404 });
  await createCampaign(tenant, input);
  response.redirect(303, `/notifications/admin?site=${encodeURIComponent(input.site)}`);
}));

app.post('/admin/deliveries/:publicId/retry', adminAuth, adminCsrf, asyncRoute(async (request, response) => {
  await retryDelivery(request.params.publicId);
  response.redirect(303, request.get('referer') || '/notifications/admin');
}));

app.get('/admin/export.csv', adminAuth, asyncRoute(async (request, response) => {
  const tenant = await tenantByKey(String(request.query.site || ''));
  if (!tenant) return response.status(404).send('Unknown site');
  const [rows] = await db.execute(
    `SELECT 'push' channel, public_id, status, browser detail, created_at FROM push_subscriptions WHERE tenant_id = ?
     UNION ALL
     SELECT 'email' channel, public_id, status, email detail, created_at FROM email_subscriptions WHERE tenant_id = ?
     ORDER BY created_at DESC`,
    [tenant.id, tenant.id],
  );
  const csv = ['channel,id,status,detail,created_at', ...rows.map((row) => [
    row.channel, row.public_id, row.status, row.detail, row.created_at?.toISOString?.() || row.created_at,
  ].map(csvCell).join(','))].join('\n');
  response.set({ 'Content-Type': 'text/csv; charset=utf-8', 'Content-Disposition': `attachment; filename="${tenant.key}-subscribers.csv"` }).send(csv);
}));

app.use((error, _request, response, _next) => {
  const status = Number(error.status) || 500;
  if (status >= 500) console.error(JSON.stringify({ level: 'error', event: 'request_failed', error: error.message, stack: error.stack }));
  response.status(status).json({ error: status >= 500 ? 'Internal server error' : error.message, details: error.details });
});

function csvCell(value) {
  const text = String(value ?? '');
  return `"${text.replaceAll('"', '""')}"`;
}

function escapeHtml(value) {
  return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function messagePage(title, message) {
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(title)}</title></head><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;"><main style="max-width:560px;margin:80px auto;padding:30px;background:#fff;border:1px solid #dbe3ef;border-radius:12px;"><h1>${escapeHtml(title)}</h1><p>${escapeHtml(message)}</p></main></body></html>`;
}

function unsubscribePage(id, token) {
  const action = `${config.publicBaseUrl}/v1/email/${encodeURIComponent(id)}/unsubscribe?token=${encodeURIComponent(token)}`;
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribe</title></head><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;"><main style="max-width:560px;margin:80px auto;padding:30px;background:#fff;border:1px solid #dbe3ef;border-radius:12px;"><h1>Unsubscribe from email alerts?</h1><p>You will stop receiving matching notification emails from this website.</p><form method="post" action="${escapeHtml(action)}"><button style="border:0;border-radius:8px;background:#b91c1c;color:#fff;padding:12px 18px;font-weight:700;cursor:pointer;">Confirm unsubscribe</button></form></main></body></html>`;
}

function preferencesPage(subscription, token, saved) {
  const filters = json(subscription.filters, {});
  const action = `${config.publicBaseUrl}/v1/email/${encodeURIComponent(subscription.id)}/preferences?token=${encodeURIComponent(token)}`;
  const unsubscribe = `${config.publicBaseUrl}/v1/email/${encodeURIComponent(subscription.id)}/unsubscribe?token=${encodeURIComponent(signedToken(`${subscription.id}:unsubscribe`))}`;
  const option = (value, label) => `<option value="${value}" ${subscription.frequency === value ? 'selected' : ''}>${label}</option>`;
  const field = (name, label) => `<label>${label}<input name="${name}" maxlength="255" value="${escapeHtml(Array.isArray(filters[name]) ? filters[name].join(', ') : filters[name] || '')}"></label>`;
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notification preferences</title></head><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;"><main style="max-width:560px;margin:50px auto;padding:30px;background:#fff;border:1px solid #dbe3ef;border-radius:12px;"><h1>${escapeHtml(subscription.tenant_name)} preferences</h1>${saved ? '<p style="padding:10px;border-radius:7px;background:#dcfce7;color:#166534;">Your preferences were saved.</p>' : ''}<p>${escapeHtml(subscription.email)}</p><form method="post" action="${escapeHtml(action)}" style="display:grid;gap:13px;"><input type="hidden" name="token" value="${escapeHtml(token)}"><label>Frequency<select name="frequency">${option('immediate', 'Immediately')}${option('daily', 'Daily digest')}${option('weekly', 'Weekly digest')}</select></label>${field('keyword', 'Keywords')}${field('location', 'Location')}${field('jobType', 'Job type')}${field('category', 'Category')}<button style="border:0;border-radius:8px;background:#2563eb;color:#fff;padding:12px;font-weight:700;cursor:pointer;">Save preferences</button></form><p style="margin-top:24px;"><a href="${escapeHtml(unsubscribe)}" style="color:#b91c1c;">Unsubscribe from all email alerts</a></p><style>label{display:grid;gap:5px;font-weight:700}input,select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}</style></main></body></html>`;
}

function count(stats, status) {
  return stats.find((item) => item.status === status)?.count || 0;
}

function adminPage({ tenants, tenant, pushStats, emailStats, campaigns, deliveries, deliveryStats, subscribers, dailyStats, browsers, devices, filters, csrf }) {
  const selected = tenant?.key || '';
  const options = ['<option value="">All websites</option>', ...tenants.map((item) => `<option value="${escapeHtml(item.key)}" ${item.key === selected ? 'selected' : ''}>${escapeHtml(item.name)}</option>`)].join('');
  const campaignRows = campaigns.map((item) => `<tr><td>${escapeHtml(item.tenant_name)}</td><td>${escapeHtml(item.title)}</td><td><span class="status">${escapeHtml(item.status)}</span></td><td>${item.total_count}</td><td>${item.sent_count}</td><td>${item.failed_count}</td><td>${item.clicked_count}</td><td>${escapeHtml(item.scheduled_at || item.created_at)}</td></tr>`).join('');
  const deliveryRows = deliveries.map((item) => `<tr><td>${escapeHtml(item.tenant_name)}</td><td>${escapeHtml(item.channel)}</td><td>${escapeHtml(item.campaign_title || json(item.payload).title || 'System email')}</td><td><span class="status">${escapeHtml(item.status)}</span></td><td>${item.attempts}/${item.max_attempts}</td><td>${escapeHtml(item.error_message || '')}</td><td>${escapeHtml(item.created_at)}</td><td>${['failed','expired','bounced'].includes(item.status) ? `<form method="post" action="/notifications/admin/deliveries/${escapeHtml(item.public_id)}/retry"><input type="hidden" name="csrf" value="${csrf}"><button>Retry</button></form>` : ''}</td></tr>`).join('');
  const subscriberRows = subscribers.map((item) => `<tr><td>${escapeHtml(item.tenant_name)}</td><td>${escapeHtml(item.channel)}</td><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.detail)}</td><td>${escapeHtml(item.device || '')}</td><td>${escapeHtml(item.platform || '')}</td><td>${escapeHtml(item.last_active_at || '')}</td><td>${escapeHtml(item.created_at)}</td></tr>`).join('');
  const dailyMaximum = Math.max(1, ...dailyStats.map((item) => Number(item.total)));
  const chart = dailyStats.map((item) => `<div class="bar-row"><span>${escapeHtml(item.day)} ${escapeHtml(item.channel)}</span><div class="bar-track"><i style="width:${Math.max(2, Math.round((Number(item.total) / dailyMaximum) * 100))}%"></i></div><strong>${item.total}</strong></div>`).join('');
  const browserOptions = ['<option value="">All browsers</option>', ...browsers.map((item) => `<option ${item.browser === filters.browser ? 'selected' : ''}>${escapeHtml(item.browser)}</option>`)].join('');
  const deviceOptions = ['<option value="">All devices</option>', ...devices.map((item) => `<option ${item.device === filters.device ? 'selected' : ''}>${escapeHtml(item.device)}</option>`)].join('');
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notification Platform</title><style>
  body{margin:0;background:#f1f5f9;color:#0f172a;font:14px/1.5 system-ui,sans-serif}header{background:#0f172a;color:#fff;padding:20px 4vw}main{padding:24px 4vw}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}.card,section{background:#fff;border:1px solid #dbe3ef;border-radius:10px;padding:18px;margin-bottom:20px}.number{font-size:30px;font-weight:800}.muted{color:#64748b}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}input,textarea,select{width:100%;box-sizing:border-box;padding:9px;border:1px solid #cbd5e1;border-radius:7px}button,.button{display:inline-block;border:0;border-radius:7px;padding:9px 13px;background:#2563eb;color:#fff;text-decoration:none;cursor:pointer}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:9px;align-items:end}.full{grid-column:1/-1}.status{padding:3px 7px;border-radius:999px;background:#e2e8f0}.scroll{overflow:auto}.bar-row{display:grid;grid-template-columns:150px 1fr 45px;gap:10px;align-items:center;margin:8px 0;font-size:12px}.bar-track{height:11px;background:#e2e8f0;border-radius:99px;overflow:hidden}.bar-track i{display:block;height:100%;background:#2563eb;border-radius:99px}@media(max-width:900px){.filter-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.form-grid,.filter-grid{grid-template-columns:1fr}}
  </style></head><body><header><h1 style="margin:0">Custom Notification Platform</h1><div style="opacity:.75">Web Push and email delivery across all websites</div></header><main>
  <form method="get" action="/notifications/admin" style="max-width:360px;margin-bottom:18px"><label>Website<select name="site" onchange="this.form.submit()">${options}</select></label></form>
  <div class="grid"><div class="card"><div class="muted">Active push</div><div class="number">${count(pushStats,'active')}</div></div><div class="card"><div class="muted">Expired push</div><div class="number">${count(pushStats,'expired')}</div></div><div class="card"><div class="muted">Active email</div><div class="number">${count(emailStats,'active')}</div></div><div class="card"><div class="muted">Unsubscribed</div><div class="number">${count(pushStats,'unsubscribed') + count(emailStats,'unsubscribed')}</div></div><div class="card"><div class="muted">Successful deliveries</div><div class="number">${['sent','delivered','opened','clicked'].reduce((total,status) => total + Number(count(deliveryStats,status)),0)}</div></div><div class="card"><div class="muted">Failed / expired</div><div class="number">${['failed','expired','bounced'].reduce((total,status) => total + Number(count(deliveryStats,status)),0)}</div></div></div>
  <section><h2>14-day delivery activity</h2>${chart || '<p class="muted">No delivery activity yet.</p>'}</section>
  <section><h2>Subscribers</h2><form method="get" action="/notifications/admin" class="filter-grid"><input type="hidden" name="site" value="${escapeHtml(selected)}"><label>Search<input name="q" value="${escapeHtml(filters.q)}" placeholder="Email, ID, browser, platform"></label><label>Browser<select name="browser">${browserOptions}</select></label><label>Device<select name="device">${deviceOptions}</select></label><label>From<input type="date" name="from" value="${escapeHtml(filters.from)}"></label><label>To<input type="date" name="to" value="${escapeHtml(filters.to)}"></label><button>Filter</button></form><div class="scroll" style="margin-top:15px"><table><thead><tr><th>Site</th><th>Channel</th><th>Status</th><th>Email / browser</th><th>Device</th><th>Platform</th><th>Last active</th><th>Created</th></tr></thead><tbody>${subscriberRows || '<tr><td colspan="8">No subscribers match these filters.</td></tr>'}</tbody></table></div></section>
  <section><h2>Send or schedule notification</h2><form method="post" action="/notifications/admin/campaigns" class="form-grid"><input type="hidden" name="csrf" value="${csrf}"><label>Website<select name="site" required>${tenants.map((item) => `<option value="${escapeHtml(item.key)}" ${item.key === selected ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('')}</select></label><label>Schedule (optional, UTC)<input type="datetime-local" name="scheduledAt"></label><label class="full">Title<input name="title" maxlength="160" required></label><label class="full">Message<textarea name="body" rows="3" maxlength="2000" required></textarea></label><label class="full">Target URL<input type="url" name="targetUrl" required></label><label>Icon URL<input type="url" name="iconUrl"></label><label>Image URL<input type="url" name="imageUrl"></label><label><input style="width:auto" type="checkbox" name="channels" value="push" checked> Web Push</label><label><input style="width:auto" type="checkbox" name="channels" value="email"> Email</label><div class="full"><button type="submit">Queue campaign</button> ${tenant ? `<a class="button" href="/notifications/admin/export.csv?site=${encodeURIComponent(tenant.key)}">Export subscribers</a>` : ''}</div></form></section>
  <section><h2>Campaign history</h2><div class="scroll"><table><thead><tr><th>Site</th><th>Campaign</th><th>Status</th><th>Total</th><th>Sent</th><th>Failed</th><th>Clicked</th><th>Date</th></tr></thead><tbody>${campaignRows || '<tr><td colspan="8">No campaigns yet.</td></tr>'}</tbody></table></div></section>
  <section><h2>Recent deliveries</h2><div class="scroll"><table><thead><tr><th>Site</th><th>Channel</th><th>Notification</th><th>Status</th><th>Attempts</th><th>Error</th><th>Date</th><th></th></tr></thead><tbody>${deliveryRows || '<tr><td colspan="8">No deliveries yet.</td></tr>'}</tbody></table></div></section>
  </main></body></html>`;
}

app.listen(config.port, '0.0.0.0', () => {
  console.log(JSON.stringify({ level: 'info', event: 'server_started', port: config.port }));
});
