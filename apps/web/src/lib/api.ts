export type ApiError = Error & { status?: number; code?: string };

const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "";

export function apiUrl(path: string): string {
  if (baseUrl === "") return path;
  return `${baseUrl.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;
}

export function accessToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem("nova.access_token");
}

export function selectedTenantId(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem("nova.tenant_id");
}

export async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  const token = accessToken();
  const tenantId = selectedTenantId();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (tenantId) headers.set("X-Tenant-Id", tenantId);
  return fetch(apiUrl(path), { ...init, headers });
}

export async function apiError(response: Response, fallback: string): Promise<ApiError> {
  let message = fallback;
  let code: string | undefined;
  try {
    const body = (await response.json()) as { error?: { message?: string; code?: string } };
    message = body.error?.message ?? message;
    code = body.error?.code;
  } catch {
    // Keep a stable user-facing message when the API response is not JSON.
  }
  const error = new Error(message) as ApiError;
  error.status = response.status;
  error.code = code;
  return error;
}
