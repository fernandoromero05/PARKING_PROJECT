import { apiGet, apiPost } from "./api.js?v=6";

export async function me() {
  return apiGet("/auth/me.php");
}

export async function login(username_or_email, password) {
  return apiPost("/auth/login.php", { username_or_email, password });
}

/**
 * Register a new user with vehicle details.
 * All fields are mandatory.
 */
export async function register(username, email, password, vehicle_plate, vehicle_make, vehicle_type) {
  const payload = {
    username,
    email,
    password,
    vehicle_plate,
    vehicle_make,
    vehicle_type
  };
  console.log("Sending registration payload:", payload);
  return apiPost("/auth/register.php", payload);
}

export async function logout() {
  return apiPost("/auth/logout.php", {});
}