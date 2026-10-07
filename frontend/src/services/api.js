import axios from 'axios'

const configuredApiUrl = import.meta.env.VITE_API_URL
const configuredApi = configuredApiUrl
  ? new URL(configuredApiUrl, window.location.href)
  : new URL(`${window.location.protocol}//${window.location.hostname}:8000/api`)
if (
  ['localhost', '127.0.0.1'].includes(configuredApi.hostname)
  && !['localhost', '127.0.0.1'].includes(window.location.hostname)
) {
  configuredApi.hostname = window.location.hostname
}
const API_BASE_URL = configuredApi.toString()
const API_ORIGIN = new URL(API_BASE_URL, window.location.href).origin

export const resolveMediaUrl = (url) => {
  if (!url || /^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(url)) {
    return url || ''
  }

  return new URL(url, API_ORIGIN).toString()
}

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Accept': 'application/json',
  },
  timeout: 3600000, // 1 hour for large video uploads
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (config.data instanceof FormData) {
      // Let the browser set the correct multipart boundary header for FormData.
      delete config.headers['Content-Type']
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
