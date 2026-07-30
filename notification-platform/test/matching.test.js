import test from 'node:test';
import assert from 'node:assert/strict';
import { dueAtForFrequency, matchesFilters } from '../src/matching.js';

const job = {
  type: 'job',
  title: 'Senior Finance Manager',
  description: 'Lead reporting and treasury operations.',
  company: 'Example Zambia',
  location: 'Lusaka',
  category: 'Finance',
  jobType: 'Full time',
  country: 'Zambia',
};

test('empty filters match content', () => {
  assert.equal(matchesFilters(job, {}), true);
});

test('keyword and structured filters are case insensitive', () => {
  assert.equal(matchesFilters(job, { keyword: 'treasury', location: 'lusaka', category: ['finance'] }), true);
});

test('a mismatched structured filter rejects content', () => {
  assert.equal(matchesFilters(job, { country: 'Kenya' }), false);
});

test('daily digests are scheduled for the next 07:00 UTC', () => {
  const due = dueAtForFrequency('daily', new Date('2026-07-30T16:30:00Z'));
  assert.equal(due.toISOString(), '2026-07-31T07:00:00.000Z');
});
