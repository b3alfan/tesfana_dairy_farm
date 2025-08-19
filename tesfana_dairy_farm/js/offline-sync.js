import { openDB } from 'https://unpkg.com/idb?module';

const DB_NAME = 'tesfana-sync';
const STORE   = 'forms';

async function getDb() {
  return openDB(DB_NAME, 1, { upgrade(db) { db.createObjectStore(STORE, { autoIncrement: true }); } });
}

document.addEventListener('submit', async e => {
  const form = e.target;
  if (!navigator.onLine && form.method.toUpperCase() === 'POST') {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    const db   = await getDb();
    await db.add(STORE, { url: form.action, data, method: form.method });
    alert('You are offline. Your submission will sync when back online.');
  }
});

window.addEventListener('online', async () => {
  const db  = await getDb();
  const all = await db.getAll(STORE);
  for (const rec of all) {
    await fetch(rec.url, {
      method: rec.method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(rec.data),
    });
    await db.delete(STORE, rec.id);
  }
});
