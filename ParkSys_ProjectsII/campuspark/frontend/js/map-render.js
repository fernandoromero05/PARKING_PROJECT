// Deterministic layout: place spots in 3 columns of 8 for LOT_A (24 spots).
// For LOT_B/C, it still places sequentially in a grid.
export function computeSpotPositions(totalSpots) {
  const positions = [];
  const cols = Math.ceil(totalSpots / 8);
  const rows = Math.min(8, totalSpots);

  // relative positions within the image
  const leftStart = 0.10;
  const topStart = 0.15;
  const colGap = 0.16;
  const rowGap = 0.08;

  for (let i = 0; i < totalSpots; i++) {
    const r = i % 8;
    const c = Math.floor(i / 8);
    positions.push({
      left: (leftStart + c * colGap),
      top: (topStart + r * rowGap),
    });
  }
  return positions;
}

export function renderSpots(layerEl, spots, onSpotClick) {
  layerEl.innerHTML = "";
  const positions = computeSpotPositions(spots.length);

  spots.forEach((s, idx) => {
    const div = document.createElement("div");
    div.className = `spot ${s.status === "AVAILABLE" ? "free" : "occ"}`;
    div.style.left = `${positions[idx].left * 100}%`;
    div.style.top = `${positions[idx].top * 100}%`;
    div.textContent = s.spot_number;

    div.title = s.status === "AVAILABLE"
      ? `Spot ${s.spot_number}: AVAILABLE`
      : `Spot ${s.spot_number}: OCCUPIED by ${s.occupied_by || "unknown"}`;

    div.addEventListener("click", () => onSpotClick(s));
    layerEl.appendChild(div);
  });
}