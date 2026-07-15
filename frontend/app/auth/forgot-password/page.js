'use client'
import { useState } from 'react'
import Link from 'next/link'
import { forgotPassword } from '@/lib/api'

export default function ForgotPasswordPage() {
  const [email,   setEmail]   = useState('')
  const [loading, setLoading] = useState(false)
  const [sent,    setSent]    = useState(false)
  const [devLink, setDevLink] = useState(null)
  const [error,   setError]   = useState('')

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const { data } = await forgotPassword({ email })
      setSent(true)
      if (data._dev_token) {
        setDevLink(`/auth/reset-password?token=${data._dev_token}`)
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Une erreur est survenue.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-brand-dark via-brand-navy to-brand-blue
                    flex items-center justify-center px-4">
      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-8">
          <Link href="/" className="inline-flex items-center gap-2">
            <span className="text-4xl">🚌</span>
            <span className="font-black text-3xl text-white tracking-tight">
              Bus<span className="text-brand-green">Go</span>
            </span>
          </Link>
          <p className="text-gray-400 mt-2 text-sm">Réinitialisation du mot de passe</p>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl p-8">
          {!sent ? (
            <>
              <div className="text-center mb-6">
                <div className="w-14 h-14 bg-brand-green/10 rounded-2xl flex items-center
                                justify-center mx-auto mb-4">
                  <svg className="w-7 h-7 text-brand-green" fill="none" viewBox="0 0 24 24"
                       stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4
                         a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                  </svg>
                </div>
                <h1 className="text-2xl font-black text-gray-900 mb-1">Mot de passe oublié ?</h1>
                <p className="text-gray-500 text-sm">
                  Entrez votre email et nous vous enverrons un lien de réinitialisation.
                </p>
              </div>

              {error && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm
                                rounded-xl px-4 py-3">
                  {error}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-5">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                    Adresse email
                  </label>
                  <input
                    type="email"
                    placeholder="vous@exemple.ma"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="input-field"
                    required
                    autoFocus
                  />
                </div>
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                             hover:bg-brand-blue transition disabled:opacity-50 text-base">
                  {loading ? 'Envoi en cours...' : 'Envoyer le lien de réinitialisation'}
                </button>
              </form>
            </>
          ) : (
            <div className="text-center py-4">
              <div className="w-16 h-16 bg-green-50 rounded-full flex items-center
                              justify-center mx-auto mb-5">
                <svg className="w-8 h-8 text-brand-green" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h2 className="text-xl font-black text-gray-900 mb-2">Email envoyé !</h2>
              <p className="text-gray-500 text-sm mb-6">
                Si l'adresse <strong>{email}</strong> est enregistrée, vous recevrez
                un lien valable <strong>1 heure</strong>.
              </p>

              {/* Lien dev uniquement — à retirer en production */}
              {devLink && (
                <div className="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 text-left">
                  <p className="text-xs font-black text-amber-700 uppercase tracking-wider mb-2">
                    🛠 Mode développement
                  </p>
                  <p className="text-xs text-amber-600 mb-2">
                    En production, ce lien serait envoyé par email.
                  </p>
                  <Link href={devLink}
                    className="text-xs font-bold text-brand-green hover:underline break-all">
                    Cliquer ici pour réinitialiser →
                  </Link>
                </div>
              )}

              <button
                onClick={() => { setSent(false); setEmail('') }}
                className="text-sm text-gray-500 hover:text-gray-700 font-medium transition">
                ← Renvoyer un email
              </button>
            </div>
          )}

          <div className="mt-6 text-center">
            <Link href="/auth/login"
              className="text-sm text-gray-500 hover:text-gray-700 font-medium transition">
              ← Retour à la connexion
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
