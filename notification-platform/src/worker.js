import { setTimeout as delay } from 'node:timers/promises';
import { assertProductionConfig, config } from './config.js';
import {
  claimDelivery,
  enqueueDueCampaigns,
  enqueueDueDigests,
} from './deliveries.js';
import { closeDatabase } from './db.js';
import { processDelivery } from './process-delivery.js';

assertProductionConfig();
let stopping = false;
let lastSchedulerRun = 0;

async function processOne() {
  const delivery = await claimDelivery(config.worker.id);
  if (!delivery) return false;
  const result = await processDelivery(delivery);
  if (!result.successful) {
    console.error(JSON.stringify({
      level: 'error', event: 'delivery_failed', delivery: delivery.public_id,
      channel: delivery.channel, attempt: delivery.attempts, error: result.error.message,
    }));
  }
  return true;
}

async function loop() {
  while (!stopping) {
    try {
      if (Date.now() - lastSchedulerRun >= config.worker.campaignPollMs) {
        lastSchedulerRun = Date.now();
        await enqueueDueCampaigns();
        await enqueueDueDigests();
      }
      const worked = await processOne();
      if (!worked) await delay(config.worker.pollMs);
    } catch (error) {
      console.error(JSON.stringify({ level: 'error', event: 'worker_loop_failed', error: error.message }));
      await delay(Math.max(config.worker.pollMs, 3000));
    }
  }
}

async function shutdown(signal) {
  if (stopping) return;
  stopping = true;
  console.log(JSON.stringify({ level: 'info', event: 'worker_stopping', signal }));
  await closeDatabase();
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

console.log(JSON.stringify({ level: 'info', event: 'worker_started', worker: config.worker.id }));
await loop();
