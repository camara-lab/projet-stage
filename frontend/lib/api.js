import axios from 'axios'
import { clearAuth } from '@/lib/auth'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
  // withCredentials est OBLIGATOIRE pour que le navigateur envoie
  // le cookie HttpOnly busgo_rt sur les requêtes cross-origin.
  withCredentials: true,
})

// Injecter le JWT (access token) dans chaque requête
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('token')
    if (token) config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Gérer l'expiration du JWT : appeler /refresh sans body
// (le refresh token voyage dans le cookie HttpOnly, géré automatiquement
//  par le navigateur grâce à withCredentials)
api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const original = err.config

    // Ne pas boucler indéfiniment si le refresh lui-même échoue
    if (err.response?.status === 401 && !original._retried) {
      original._retried = true
      try {
        const { data } = await axios.post(
          '/api/auth/refresh',
          {},                          // body vide — le cookie est envoyé automatiquement
          { withCredentials: true },
        )
        // Stocker uniquement l'access token
        localStorage.setItem('token', data.token)
        original.headers.Authorization = `Bearer ${data.token}`
        return axios(original)
      } catch {
        // Refresh échoué → session expirée, déconnecter proprement
        clearAuth()
        if (typeof window !== 'undefined') {
          window.location.href = '/auth/login'
        }
      }
    }

    return Promise.reject(err)
  }
)

// ── Auth ──────────────────────────────────────────────
export const login          = (data) => api.post('/auth/login', data)
export const register       = (data) => api.post('/auth/register', data)
export const forgotPassword = (data) => api.post('/auth/forgot-password', data)
export const resetPassword  = (data) => api.post('/auth/reset-password', data)
// Logout n'envoie plus de body — le serveur lit le cookie
export const logout         = () => api.post('/auth/logout')

// ── Trips ─────────────────────────────────────────────
export const getTrips  = (params) => api.get('/trips', { params })
export const getTrip   = (id)     => api.get(`/trips/${id}`)
export const getCities = ()       => api.get('/cities')

// ── Bookings ──────────────────────────────────────────
export const createBooking  = (data) => api.post('/bookings', data)
export const getMyBookings  = ()     => api.get('/bookings')
export const getBooking     = (id)   => api.get(`/bookings/${id}`)
export const cancelBooking  = (id)   => api.post(`/bookings/${id}/cancel`)
export const downloadTicket = (id)   => api.get(`/bookings/${id}/ticket`, { responseType: 'blob' })

// ── Payments ──────────────────────────────────────────
export const payBooking = (bookingId, data) =>
  api.post(`/payments/booking/${bookingId}`, data)

// ── Profile ───────────────────────────────────────────
export const getProfile    = ()     => api.get('/profile')
export const updateProfile = (data) => api.patch('/profile', data)

export default api
