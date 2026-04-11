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
  if (res.status === 403) {
    const data = await res.json().catch(() => ({}));
    showBanModal(data.error || "Your account has been suspended.");
    return new Promise(() => {}); 
  }
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
  if (res.status === 403) {
    const data = await res.json().catch(() => ({}));
    showBanModal(data.error || "Your account has been suspended.");
    return new Promise(() => {});
  }
  const data = await readJsonOrText(res);
  if (!data.ok) throw new Error(data.error || "Request failed");
  return data;
}

function showBanModal(message) {
  if (document.getElementById('banOverlay')) return;
  if (window.location.pathname.endsWith('login.html')) return;

  const overlay = document.createElement('div');
  overlay.id = 'banOverlay';
  overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); display:flex; justify-content:center; align-items:center; z-index:99999; backdrop-filter:blur(10px); font-family:sans-serif;';
  
  overlay.innerHTML = `
    <div style="background:white; color:#333; padding:2.5rem; border-radius:16px; max-width:420px; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
      <div style="color:#d93025; font-size:3rem; margin-bottom:1rem;">🚫</div>
      <h2 style="color:#d93025; margin:0 0 1rem 0; font-size:1.8rem;">Account Banned</h2>
      <p style="margin:1rem 0; font-size:1.1rem; line-height:1.5; color:#555;">${message}</p>
      <p style="margin-bottom:2rem; font-size:0.9rem; color:#888;">Your session has been terminated. Please follow the community guidelines in the future.</p>
      <button onclick="window.location.href='login.html'" style="background:#d93025; color:white; border:0; padding:14px 28px; border-radius:8px; font-weight:bold; cursor:pointer; width:100%; font-size:1rem; transition:background 0.2s;">Return to Login</button>
    </div>
  `;
  document.body.appendChild(overlay);
}