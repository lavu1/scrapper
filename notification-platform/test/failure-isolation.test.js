import test from 'node:test';
import assert from 'node:assert/strict';
import { processDelivery } from '../src/process-delivery.js';

test('one failed recipient is recorded without throwing or preventing the next recipient', async () => {
  const events = [];
  const dependencies = {
    sendEmail: async (delivery) => {
      if (delivery.email === 'broken@example.com') throw Object.assign(new Error('Mailbox rejected'), { code: 'smtp_rejected' });
      return { messageId: `message-${delivery.id}` };
    },
    markSent: async (delivery) => events.push(['sent', delivery.id]),
    markFailure: async (delivery) => events.push(['failed', delivery.id]),
  };

  const first = await processDelivery({ id: 1, channel: 'email', email: 'broken@example.com' }, dependencies);
  const second = await processDelivery({ id: 2, channel: 'email', email: 'working@example.com' }, dependencies);

  assert.equal(first.successful, false);
  assert.equal(second.successful, true);
  assert.deepEqual(events, [['failed', 1], ['sent', 2]]);
});

test('an expired browser endpoint is isolated and marked expired', async () => {
  const events = [];
  const result = await processDelivery(
    { id: 3, channel: 'push', endpoint: 'https://push.example.test/expired' },
    {
      sendPush: async () => { throw Object.assign(new Error('Gone'), { statusCode: 410 }); },
      markExpired: async (delivery) => events.push(['expired', delivery.id]),
      markFailure: async (delivery) => events.push(['failed', delivery.id]),
    },
  );

  assert.equal(result.expired, true);
  assert.deepEqual(events, [['expired', 3]]);
});
