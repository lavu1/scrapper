import assert from 'node:assert/strict';
import test from 'node:test';
import { renderEmail } from '../src/email.js';

test('responsive email includes branding, job details, app links, social links, tracking, and preferences', () => {
  const html = renderEmail({
    id: 1,
    public_id: 'delivery-test',
    email_subscription_id: 22,
    tenant_name: 'Prime Job Alerts',
    domain: 'primejobalerts.com',
    logo_url: 'https://primejobalerts.com/favicon.ico',
    tenant_settings: JSON.stringify({
      color: '#082f49',
      androidUrl: 'https://play.google.com/store/apps/details?id=test',
      iosUrl: 'https://apps.apple.com/app/test/id123',
    }),
    payload: JSON.stringify({
      title: 'New jobs matching your subscription',
      body: 'A matching role was published.',
      targetUrl: 'https://primejobalerts.com/jobs/test',
      content: [{
        title: 'Finance Officer', company: 'Example Company', location: 'Lusaka',
        jobType: 'Full time', description: 'A short job description.', url: 'https://primejobalerts.com/jobs/test',
      }],
    }),
  });

  for (const expected of [
    'Prime Job Alerts', 'Finance Officer', 'Example Company', 'Lusaka', 'Full time',
    'Download Android app', 'Download iPhone app', 'Facebook', 'LinkedIn',
    'All rights reserved', 'Notification preferences or unsubscribe', 'open.gif',
  ]) assert.match(html, new RegExp(expected));
});
