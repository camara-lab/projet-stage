'use client'

export const getToken = () =>
  typeof window !== 'undefined' ? localStorage.getItem('token') : null

/**
 * Décode le payload JWT pour obtenir les données utilisateur.
 * Le refresh token n'est plus en localStorage — il est dans un cookie HttpOnly.
 */
export const getUser = () => {
  if (typeof window === 'undefined') return null
  try {
    const token = localStorage.getItem('token')
    if (!token) return null
    const payload = JSON.parse(atob(token.split('.')[1]))
    return payload
  } catch { return null }
}

export const isLoggedIn = () => !!getToken()

/**
 * Après login : sauvegarder uniquement l'access token JWT.
 * Le refresh token est dans le cookie HttpOnly géré par le navigateur.
 */
export const saveAuth = (data) => {
  localStorage.setItem('token', data.token)
}

/**
 * Nettoyer l'état d'authentification côté client.
 * Le cookie busgo_rt sera supprimé par le serveur lors du /logout.
 */
export const clearAuth = () => {
  localStorage.removeItem('token')
  // Nettoyage héritage (migration depuis l'ancienne implémentation)
  localStorage.removeItem('refresh_token')
  localStorage.removeItem('user')
}
