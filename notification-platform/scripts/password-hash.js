import { makePasswordHash, randomToken } from '../src/security.js';

const password = process.argv[2] || randomToken(18);
console.log(JSON.stringify({ password, hash: makePasswordHash(password) }, null, 2));
