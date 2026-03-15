const LOT_LAYOUTS = {
  'LOT_A': {
    rows: 8,
    cols: 4,
    leftStart: 0.15,
    topStart: 0.15,
    colGap: 0.18,
    rowGap: 0.09
  },
  'LOT_B': {
    rows: 4,
    cols: 4,
    leftStart: 0.20,
    topStart: 0.25,
    colGap: 0.20,
    rowGap: 0.18
  },
  'LOT_C': {
    rows: 4,
    cols: 3,
    leftStart: 0.25,
    topStart: 0.30,
    colGap: 0.20,
    rowGap: 0.18
  }
};

export function computeSpotPositions(totalSpots, lotCode = 'LOT_A') {
  const layout = LOT_LAYOUTS[lotCode] || LOT_LAYOUTS['LOT_A'];
  const positions = [];

  for (let i = 0; i < totalSpots; i++) {
    const r = i % layout.rows;
    const c = Math.floor(i / layout.rows);
    positions.push({
      left: layout.leftStart + c * layout.colGap,
      top: layout.topStart + r * layout.rowGap,
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

  if (isMine && status === "RESERVED") return "mine-reserved";
  if (isMine) return "mine";
  if (status === "RESERVED") return "reserved";
  if (status === "OCCUPIED") return "occ";
  if (type === "EV_ONLY") return "ev";
  return "free";
}

export function renderSpots(layerEl, spots, onSpotClick, currentUserId, lotCode = 'LOT_A') {
  if (!layerEl) return;
  layerEl.innerHTML = "";
  
  const positions = computeSpotPositions(spots.length, lotCode);

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