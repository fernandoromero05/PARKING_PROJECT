export function computeSpotPositions(totalSpots) {
  const positions = [];
  const cols = Math.ceil(totalSpots / 8);

  const leftStart = 0.10;
  const topStart = 0.15;
  const colGap = 0.16;
  const rowGap = 0.08;

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
  const spotType = normalizeValue(spot.spot_type);
  const status = normalizeValue(spot.status);

  if (spotType === "EV_ONLY") {
    if (status === "AVAILABLE") return "ev";
    if (status === "RESERVED") return "ev-reserved";
    if (status === "OCCUPIED") return "ev-occ";
    return "ev";
  }

  if (status === "AVAILABLE") return "free";
  if (status === "RESERVED") return "reserved";
  if (status === "OCCUPIED") return "occ";
  return "free";
}

export function renderSpots(layerEl, spots, onSpotClick) {
  layerEl.innerHTML = "";
  const positions = computeSpotPositions(spots.length);

  spots.forEach((spot, idx) => {
    const div = document.createElement("div");
    const cls = getSpotClass(spot);

    div.className = `spot ${cls}`;
    div.style.left = `${positions[idx].left * 100}%`;
    div.style.top = `${positions[idx].top * 100}%`;
    div.textContent = spot.spot_number;

    div.title = `Spot ${spot.spot_number}: ${spot.status}${normalizeValue(spot.spot_type) === "EV_ONLY" ? " (EV_ONLY)" : ""}`;

    div.addEventListener("click", () => onSpotClick(spot));
    layerEl.appendChild(div);
  });
}