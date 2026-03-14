export const API_BASE = "/campuspark/backend/api";

async function readJsonOrText(res) {
  const text = await res.text(); // read raw
  try {
    return JSON.parse(text);
  } catch {
    // Not JSON: return as error with raw body for debugging
    throw new Error(`Server returned non-JSON (HTTP ${res.status}): ${text.slice(0, 300)}`);
  }
}

export async function apiGet(path) {
  const res = await fetch(`${API_BASE}${path}`, { credentials: "include" });
  const data = await readJsonOrText(res);
  if (!data.ok) throw new Error(data.error || "Request failed");
  return data;
}

export async function apiPost(path, body) {
  const res = await fetch(`${API_BASE}${path}`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  });
  const data = await readJsonOrText(res);
  if (!data.ok) throw new Error(data.error || "Request failed");
  return data;
}