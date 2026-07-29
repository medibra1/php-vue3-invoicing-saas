import axios from 'axios'

/**
 * Single axios instance for the whole app. Components/stores never call
 * axios directly — they import this, so token attachment and the
 * 401 -> refresh flow (registered in main.ts, see interceptors/) apply
 * uniformly everywhere.
 */
export const httpClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
})

/** Backend's uniform error shape (App\Core\Http\JsonErrorResponse). */
export interface ApiErrorResponse {
  error: {
    status: number
    message: string
    detail?: string
  }
}

export function extractErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.error?.message) {
    return error.response.data.error.message
  }

  return fallback
}
