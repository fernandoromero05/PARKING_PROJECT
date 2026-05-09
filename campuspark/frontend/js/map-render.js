export function computeSpotPositions(totalSpots) {
  // Pick column count so the grid is landscape-shaped and fills the whole map
  let COLS;
  if (totalSpots <= 16)      COLS = 4;
  else if (totalSpots <= 24) COLS = 6;
  else                       COLS = 8;

  const ROWS = Math.ceil(totalSpots / COLS);

  // Spread from 3 % to 88 % so spots stay inside the image on both axes
  const L0 = 0.03, L1 = 0.88;
  const T0 = 0.07, T1 = 0.88;

  const colStep = COLS > 1 ? (L1 - L0) / (COLS - 1) : 0;
  const rowStep = ROWS > 1 ? (T1 - T0) / (ROWS - 1) : 0;

  const positions = [];
  for (let i = 0; i < totalSpots; i++) {
    const col = i % COLS;
    const row = Math.floor(i / COLS);
    positions.push({
      left: L0 + col * colStep,
      top:  T0 + row * rowStep,
    });
  }
  return positions;
}

function normalizeValue(value) {
  return String(value || "").trim().toUpperCase();
}

function getSpotClass(spot, currentUserId) {
  const status = normalizeValue(spot.status);
  const type = normalizeValue(spot.spot_type);
  const isMine = (spot.occupied_by_user_id == currentUserId || spot.reserved_by_user_id == currentUserId);

  if (isMine) return "mine";
  if (status === "OCCUPIED") return "occ";
  if (status === "RESERVED") return "reserved";
  if (type === "EV_ONLY") return "ev";
  return "free";
}

export function renderSpots(layerEl, spots, onSpotClick, currentUserId) {
  if (!layerEl) return;
  layerEl.innerHTML = "";
  
  const positions = computeSpotPositions(spots.length);

  spots.forEach((spot, idx) => {
    const div = document.createElement("div");
    const cls = getSpotClass(spot, currentUserId);

    div.className = `spot ${cls}`;
    
    // Position using percentages relative to #mapWrap
    const pos = positions[idx] || { left: 0, top: 0 };
    div.style.left = `${pos.left * 100}%`;
    div.style.top = `${pos.top * 100}%`;
    
    div.textContent = spot.spot_number;
    div.dataset.id = spot.id;

    div.addEventListener("click", (e) => {
      e.stopPropagation();
      onSpotClick(spot);
    });
    
    layerEl.appendChild(div);
  });
}