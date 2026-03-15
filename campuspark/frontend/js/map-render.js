export function computeSpotPositions(totalSpots) {
  const positions = [];
  const leftStart = 0.05;
  const topStart = 0.10;
  const colGap = 0.10;
  const rowGap = 0.10;

  for (let i = 0; i < totalSpots; i++) {
    const r = i % 8;
    const c = Math.floor(i / 8);
    positions.push({
      left: leftStart + c * colGap,
      top: topStart + r * rowGap,
    });
  }
  return positions;
}

function normalizeValue(value) {
  return String(value || "").trim().toUpperCase();
}

function getSpotClass(spot) {
  const status = normalizeValue(spot.status);
  const type = normalizeValue(spot.spot_type);

  if (status === "OCCUPIED") return "occ";
  if (status === "RESERVED") return "reserved";
  
  // Available states
  if (type === "EV_ONLY") return "ev";
  return "free";
}

export function renderSpots(layerEl, spots, onSpotClick) {
  if (!layerEl) return;
  layerEl.innerHTML = "";
  
  // Simple layout: let's try to fit them in a grid
  const positions = computeSpotPositions(spots.length);

  spots.forEach((spot, idx) => {
    const div = document.createElement("div");
    const cls = getSpotClass(spot);

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