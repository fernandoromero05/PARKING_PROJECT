// MVP: no real camera QR. User inputs code like LOT_A-12.
export function normalizeCode(code) {
  return (code || "").trim().toUpperCase();
}