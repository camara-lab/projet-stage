'use client'
import { useState, useEffect, useRef } from 'react'
import Link from 'next/link'
import { useRouter, usePathname } from 'next/navigation'
import { getUser, clearAuth, isLoggedIn } from '@/lib/auth'
import { logout } from '@/lib/api'
import toast from 'react-hot-toast'

function getFirstName(user) {
  if (!user) return 'Voyageur'
  if (user.fullName) return user.fullName.split(' ')[0]
  const raw = user.username || user.email || ''
  const local = raw.split('@')[0].replace(/[0-9_.\-]/g, ' ').trim()
  return local.charAt(0).toUpperCase() + local.slice(1).split(' ')[0] || 'Moi'
}

function getInitials(user) {
  if (!user) return '?'
  if (user.fullName) {
    const parts = user.fullName.trim().split(' ')
    return parts.length >= 2
      ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      : parts[0][0].toUpperCase()
  }
  const raw = user.username || user.email || '?'
  return raw[0].toUpperCase()
}

export default function Navbar() {
  const [user,       setUser]       = useState(null)
  const [menuOpen,   setMenuOpen]   = useState(false)
  const [dropOpen,   setDropOpen]   = useState(false)
  const [aideOpen,   setAideOpen]   = useState(false)
  const dropRef  = useRef(null)
  const aideRef  = useRef(null)
  const router   = useRouter()
  const pathname = usePathname()

  useEffect(() => {
    if (isLoggedIn()) setUser(getUser())
  }, [pathname])

  // Fermer les dropdowns si clic à l'extérieur
  useEffect(() => {
    const handler = (e) => {
      if (dropRef.current && !dropRef.current.contains(e.target)) setDropOpen(false)
      if (aideRef.current && !aideRef.current.contains(e.target)) setAideOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const handleLogout = async () => {
    setDropOpen(false)
    try {
      await logout() // Le serveur lit le cookie et révoque le refresh token
    } catch {}
    clearAuth()
    setUser(null)
    toast.success('À bientôt !')
    router.push('/')
  }

  const navLink = (href, label) => (
    <Link href={href}
      className={`text-sm font-medium transition
        ${pathname === href ? 'text-brand-green' : 'text-gray-300 hover:text-white'}`}>
      {label}
    </Link>
  )

  return (
    <nav className="bg-brand-dark text-white shadow-lg sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">

          {/* Logo */}
          <Link href="/" className="flex items-center gap-2.5 flex-shrink-0">
            <span className="text-3xl">🚌</span>
            <span className="font-black text-2xl tracking-tight">
              Bus<span className="text-brand-green">Go</span>
            </span>
          </Link>

          {/* Desktop nav */}
          <div className="hidden md:flex items-center gap-8">
            {navLink('/', 'Accueil')}
            {navLink('/trips', 'Trajets')}

            {/* Dropdown Aide */}
            <div className="relative" ref={aideRef}>
              <button
                onClick={() => setAideOpen((v) => !v)}
                className={`flex items-center gap-1 text-sm font-medium transition
                  ${pathname.startsWith('/aide') ? 'text-brand-green' : 'text-gray-300 hover:text-white'}`}
              >
                Aide
                <svg className={`w-3 h-3 transition-transform ${aideOpen ? 'rotate-180' : ''}`}
                  fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              {aideOpen && (
                <div className="absolute left-0 mt-2 w-48 bg-white rounded-2xl shadow-xl
                                border border-gray-100 py-1 overflow-hidden">
                  <Link href="/aide"
                    onClick={() => setAideOpen(false)}
                    className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700
                               hover:bg-gray-50 transition">
                    <span>❓</span> FAQ
                  </Link>
                  <Link href="/aide?tab=contact"
                    onClick={() => setAideOpen(false)}
                    className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700
                               hover:bg-gray-50 transition">
                    <span>✉️</span> Nous contacter
                  </Link>
                </div>
              )}
            </div>

            {user ? (
              <>
                {navLink('/dashboard', 'Mes réservations')}

                {/* Avatar dropdown */}
                <div className="relative" ref={dropRef}>
                  <button
                    onClick={() => setDropOpen(!dropOpen)}
                    className="flex items-center gap-2 group">
                    <div className="w-9 h-9 rounded-full bg-brand-green flex items-center justify-center
                                    text-brand-dark text-sm font-black group-hover:ring-2
                                    group-hover:ring-brand-green/50 transition">
                      {getInitials(user)}
                    </div>
                    <span className="text-sm font-semibold text-gray-200">
                      {getFirstName(user)}
                    </span>
                    <svg className={`w-3 h-3 text-gray-400 transition-transform ${dropOpen ? 'rotate-180' : ''}`}
                      fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  {dropOpen && (
                    <div className="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl
                                    border border-gray-100 py-1 overflow-hidden">
                      <div className="px-4 py-3 border-b border-gray-50">
                        <p className="text-xs font-bold text-gray-900">{getFirstName(user)}</p>
                        <p className="text-xs text-gray-400 truncate">
                          {user.username || user.email}
                        </p>
                      </div>
                      <Link href="/profile"
                        onClick={() => setDropOpen(false)}
                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700
                                   hover:bg-gray-50 transition">
                        <span>👤</span> Mon profil
                      </Link>
                      <Link href="/dashboard"
                        onClick={() => setDropOpen(false)}
                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700
                                   hover:bg-gray-50 transition">
                        <span>🎫</span> Mes billets
                      </Link>
                      <div className="border-t border-gray-50 mt-1">
                        <button onClick={handleLogout}
                          className="w-full flex items-center gap-3 px-4 py-2.5 text-sm
                                     text-red-500 hover:bg-red-50 transition">
                          <span>🚪</span> Déconnexion
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </>
            ) : (
              <div className="flex items-center gap-4">
                <Link href="/auth/login"
                  className="flex items-center gap-2 text-gray-300 hover:text-white text-sm font-medium transition">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  Connexion
                </Link>
                <Link href="/auth/register"
                  className="bg-brand-green text-brand-dark text-sm font-bold
                             px-5 py-2.5 rounded-xl hover:bg-green-400 transition
                             shadow-lg shadow-brand-green/25 hover:shadow-brand-green/40
                             hover:-translate-y-0.5 transform duration-200">
                  S'inscrire
                </Link>
              </div>
            )}
          </div>

          {/* Mobile burger */}
          <button className="md:hidden p-2" onClick={() => setMenuOpen(!menuOpen)}
            aria-label="Menu">
            <div className="space-y-1">
              <span className={`block w-6 h-0.5 bg-white transition-all ${menuOpen ? 'rotate-45 translate-y-1.5' : ''}`}></span>
              <span className={`block w-6 h-0.5 bg-white transition-all ${menuOpen ? 'opacity-0' : ''}`}></span>
              <span className={`block w-6 h-0.5 bg-white transition-all ${menuOpen ? '-rotate-45 -translate-y-1.5' : ''}`}></span>
            </div>
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      {menuOpen && (
        <div className="md:hidden bg-brand-navy border-t border-white/10 px-4 pb-4">
          {user && (
            <div className="flex items-center gap-3 py-3 border-b border-white/10 mb-2">
              <div className="w-9 h-9 rounded-full bg-brand-green flex items-center justify-center
                              text-brand-dark text-sm font-black">
                {getInitials(user)}
              </div>
              <div>
                <p className="text-sm font-bold text-white">{getFirstName(user)}</p>
                <p className="text-xs text-gray-400">{user.username || user.email}</p>
              </div>
            </div>
          )}
          <div className="flex flex-col gap-1">
            <Link href="/" onClick={() => setMenuOpen(false)}
              className="text-gray-300 text-sm py-2.5 border-b border-white/5">Accueil</Link>
            <Link href="/trips" onClick={() => setMenuOpen(false)}
              className="text-gray-300 text-sm py-2.5 border-b border-white/5">Trajets</Link>
            <Link href="/aide" onClick={() => setMenuOpen(false)}
              className="text-gray-300 text-sm py-2.5 border-b border-white/5">❓ FAQ</Link>
            <Link href="/aide?tab=contact" onClick={() => setMenuOpen(false)}
              className="text-gray-300 text-sm py-2.5 border-b border-white/5">✉️ Nous contacter</Link>
            {user ? (
              <>
                <Link href="/dashboard" onClick={() => setMenuOpen(false)}
                  className="text-gray-300 text-sm py-2.5 border-b border-white/5">🎫 Mes réservations</Link>
                <Link href="/profile" onClick={() => setMenuOpen(false)}
                  className="text-gray-300 text-sm py-2.5 border-b border-white/5">👤 Mon profil</Link>
                <button onClick={handleLogout}
                  className="text-red-400 text-sm text-left py-2.5">🚪 Déconnexion</button>
              </>
            ) : (
              <>
                <Link href="/auth/login" onClick={() => setMenuOpen(false)}
                  className="text-gray-300 text-sm py-2.5">Connexion</Link>
                <Link href="/auth/register" onClick={() => setMenuOpen(false)}
                  className="bg-brand-green text-brand-dark text-sm font-bold px-4 py-2 rounded-xl text-center mt-1">
                  S'inscrire
                </Link>
              </>
            )}
          </div>
        </div>
      )}
    </nav>
  )
}
