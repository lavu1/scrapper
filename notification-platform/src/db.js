import mysql from 'mysql2/promise';
import { config } from './config.js';

export const db = mysql.createPool({
  ...config.database,
  waitForConnections: true,
  enableKeepAlive: true,
  keepAliveInitialDelay: 0,
  namedPlaceholders: true,
  timezone: 'Z',
  decimalNumbers: true,
});

export async function transaction(callback) {
  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();
    const result = await callback(connection);
    await connection.commit();
    return result;
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

export async function closeDatabase() {
  await db.end();
}
