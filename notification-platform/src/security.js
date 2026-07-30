import crypto from 'node:crypto';
import { config } from './config.js';

export function sha256(value) {
  return crypto.createHash('sha256').update(String(value)).digest('hex');
}

export function randomToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString('base64url');
}

export function hashIp(ip) {
  return crypto.createHmac('sha256', config.ipHashSecret).update(ip || 'unknown').digest('hex');
}

export function safeEqual(left, right) {
  const a = Buffer.from(String(left));
  const b = Buffer.from(String(right));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export function sign(value) {
  return crypto.createHmac('sha256', config.admin.csrfSecret).update(String(value)).digest('base64url');
}

export function signedToken(value) {
  return `${value}.${sign(value)}`;
}

export function verifySignedToken(token) {
  const position = String(token).lastIndexOf('.');
  if (position < 1) return null;
  const value = token.slice(0, position);
  return safeEqual(token.slice(position + 1), sign(value)) ? value : null;
}

export function verifyPassword(password, encoded) {
  if (!encoded?.startsWith('scrypt$')) return false;
  const [, salt, expected] = encoded.split('$');
  const actual = crypto.scryptSync(password, salt, 64).toString('hex');
  return safeEqual(actual, expected);
}

export function makePasswordHash(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  return `scrypt$${salt}$${crypto.scryptSync(password, salt, 64).toString('hex')}`;
}
