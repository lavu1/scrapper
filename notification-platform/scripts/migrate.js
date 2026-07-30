import fs from 'node:fs/promises';
import { db, closeDatabase } from '../src/db.js';

const schema = await fs.readFile(new URL('../database/schema.sql', import.meta.url), 'utf8');
const statements = schema
  .split(/;\s*(?:\r?\n|$)/)
  .map((statement) => statement.trim())
  .filter(Boolean);

try {
  for (const statement of statements) await db.query(statement);
  console.log(`Applied ${statements.length} schema statements.`);
} finally {
  await closeDatabase();
}
