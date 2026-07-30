import { z } from 'zod';

const optionalUrl = z.string().url().max(2048).optional().nullable();
const filterableContentValue = z.union([z.string().trim().max(180), z.array(z.string().trim().max(180)).max(50)]).optional().nullable();
const filters = z.record(z.string(), z.union([
  z.string().max(255),
  z.array(z.string().max(255)).max(50),
  z.boolean(),
  z.number(),
])).optional().default({});

export const pushSubscriptionSchema = z.object({
  site: z.string().min(2).max(40),
  endpoint: z.string().url().max(4096).refine((value) => value.startsWith('https://'), 'HTTPS endpoint required'),
  expirationTime: z.number().nullable().optional(),
  keys: z.object({
    p256dh: z.string().min(20).max(512),
    auth: z.string().min(8).max(255),
  }),
  contentEncoding: z.enum(['aes128gcm', 'aesgcm']).optional().default('aes128gcm'),
  filters,
});

export const unsubscribePushSchema = z.object({
  site: z.string().min(2).max(40),
  endpoint: z.string().url().max(4096),
});

export const emailSubscriptionSchema = z.object({
  site: z.string().min(2).max(40),
  email: z.string().email().max(320).transform((value) => value.trim().toLowerCase()),
  name: z.string().trim().max(160).optional().nullable(),
  frequency: z.enum(['immediate', 'daily', 'weekly']).optional().default('immediate'),
  filters,
});

export const syncedEmailSubscriptionSchema = emailSubscriptionSchema.extend({
  verified: z.boolean().optional().default(true),
  status: z.enum(['active', 'pending', 'unsubscribed']).optional(),
  externalId: z.string().max(190).optional().nullable(),
});

const contentItem = z.object({
  id: z.union([z.string(), z.number()]).transform(String),
  type: z.string().trim().min(2).max(50),
  title: z.string().trim().min(2).max(180),
  description: z.string().trim().max(2000).optional().default(''),
  url: z.string().url().max(2048),
  imageUrl: optionalUrl,
  company: z.string().trim().max(180).optional().nullable(),
  location: filterableContentValue,
  category: filterableContentValue,
  categoryId: filterableContentValue,
  jobType: filterableContentValue,
  country: filterableContentValue,
  studyLevel: filterableContentValue,
  field: filterableContentValue,
  funding: filterableContentValue,
  publishedAt: z.string().datetime().optional().nullable(),
});

export const contentPublishedSchema = z.object({
  site: z.string().min(2).max(40),
  eventId: z.string().min(2).max(190),
  content: contentItem,
  channels: z.array(z.enum(['push', 'email'])).min(1).max(2).optional().default(['push', 'email']),
});

export const campaignSchema = z.object({
  site: z.string().min(2).max(40),
  title: z.string().trim().min(2).max(160),
  body: z.string().trim().min(2).max(2000),
  targetUrl: z.string().url().max(2048),
  iconUrl: optionalUrl,
  imageUrl: optionalUrl,
  channels: z.array(z.enum(['push', 'email'])).min(1).max(2),
  scheduledAt: z.string().datetime().optional().nullable(),
  audienceFilters: filters,
});

export function parse(schema, value) {
  const result = schema.safeParse(value);
  if (!result.success) {
    const error = new Error('Validation failed');
    error.status = 422;
    error.details = result.error.flatten();
    throw error;
  }
  return result.data;
}
