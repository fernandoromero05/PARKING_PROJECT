import { apiPost } from "./api.js";

export async function fileReport(spot_id, type, note) {
  return apiPost("/reports/file_report.php", { spot_id, type, note });
}