import { apiGet, apiPost } from "./api.js";

export async function me() {
  return apiGet("/auth/me.php");
}

export async function login(username_or_email, password) {
  return apiPost("/auth/login.php", { username_or_email, password });
}

export async function register(username, email, password) {
  return apiPost("/auth/register.php", { username, email, password });
}

export async function logout() {
  return apiPost("/auth/logout.php", {});
}