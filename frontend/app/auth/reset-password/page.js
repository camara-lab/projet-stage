'use client'
import { useState, Suspense } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { resetPassword } from '@/lib/api'
import toast from 'react-hot-toast'

function ResetPasswordContent() {
  const router       = useRouter()
  const searchParams = useSearchParams()
  const token        = searchParams.get('token') || ''

  const [form,    setForm]    = useState({ password: '', confirm: '' })
  const [showPwd, setShowPwd] = useState(false)
  const [showCfm, setShowCfm] = useState(false)
  const [loading, setLoading] = useState(false)
  const [done,    setDone]    = useState(false)
  const [error,   setError]   = useState('')

  const EyeIcon = ({ open }) => open ? (
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none"
         viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round"
        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
           a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243
           M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29
           M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943
           9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
    </svg>
  ) : (
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none"
         viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round"
        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
           9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  )

  if (!token) {
    return (
      <div className="text-center py-4">
        <div className="text-5xl mb-4">⚠️</div>
        <h2 className="text-xl font-black text-gray-900 mb-2">Lien invalide</h2>
        <p className="text-gray-500 text-sm mb-6">
          Ce lien de réinitialisation est invalide ou manquant.
        </p>
        <Link href="/auth/forgot-password"
          className="text-sm text-brand-green font-bold hover:underline">
          Faire une nouvelle demande →
        </Link>
      </div>
    )
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    if (form.password !== form.confirm) {
      setError('Les mots de passe ne correspondent pas.')
      return
    }
    if (form.password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères.')
      return
    }
    setLoading(true)
    try {
      await resetPassword({ token, password: form.password })
      setDone(true)
      toast.success('Mot de passe modifié avec succès !')
      setTimeout(() => router.push('/auth/login'), 2500)
    } catch (err) {
      setError(err.response?.data?.message || 'Lien invalide ou expiré.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bg-white rounded-2xl shadow-2xl p-8">
      {!done ? (
        <>
          <div className="text-center mb-6">
            <div className="w-14 h-14 bg-brand-green/10 rounded-2xl flex items-center
                            justify-center mx-auto mb-4">
              <svg className="w-7 h-7 text-brand-green" fill="none" viewBox="0 0 24 24"
                   stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0
                     00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h1 className="text-2xl font-black text-gray-900 mb-1">Nouveau mot de passe</h1>
            <p className="text-gray-500 text-sm">Choisissez un mot de passe sécurisé (8 caractères min.).</p>
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
                Nouveau mot de passe
              </label>
              <div className="relative">
                <input
                  type={showPwd ? 'text' : 'password'}
                  placeholder="••••••••"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  className="input-field pr-11"
                  required
                  autoFocus
                />
                <button type="button" onClick={() => setShowPwd((v) => !v)}
                  className="absolute inset-y-0 right-3 flex items-center text-gray-400
                             hover:text-gray-700 transition-colors">
                  <EyeIcon open={showPwd} />
                </button>
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                Confirmer le mot de passe
              </label>
              <div className="relative">
                <input
                  type={showCfm ? 'text' : 'password'}
                  placeholder="••••••••"
                  value={form.confirm}
                  onChange={(e) => setForm({ ...form, confirm: e.target.value })}
                  className={`input-field pr-11 ${
                    form.confirm && form.confirm !== form.password
                      ? 'border-red-400 focus:ring-red-200'
                      : ''
                  }`}
                  required
                />
                <button type="button" onClick={() => setShowCfm((v) => !v)}
                  className="absolute inset-y-0 right-3 flex items-center text-gray-400
                             hover:text-gray-700 transition-colors">
                  <EyeIcon open={showCfm} />
                </button>
              </div>
              {form.confirm && form.confirm !== form.password && (
                <p className="text-xs text-red-500 mt-1 font-medium">
                  Les mots de passe ne correspondent pas.
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                         hover:bg-brand-blue transition disabled:opacity-50 text-base">
              {loading ? 'Modification en cours...' : 'Changer le mot de passe'}
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
          <h2 className="text-xl font-black text-gray-900 mb-2">Mot de passe modifié !</h2>
          <p className="text-gray-500 text-sm">
            Vous allez être redirigé vers la page de connexion...
          </p>
        </div>
      )}

      <div className="mt-6 text-center">
        <Link href="/auth/login"
          className="text-sm text-gray-500 hover:text-gray-700 font-medium transition">
          ← Retour à la connexion
        </Link>
      </div>
    </div>
  )
}

export default function ResetPasswordPage() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-brand-dark via-brand-navy to-brand-blue
                    flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <Link href="/" className="inline-flex items-center gap-2">
            <span className="text-4xl">🚌</span>
            <span className="font-black text-3xl text-white tracking-tight">
              Bus<span className="text-brand-green">Go</span>
            </span>
          </Link>
          <p className="text-gray-400 mt-2 text-sm">Réinitialisation du mot de passe</p>
        </div>

        <Suspense fallback={<div className="bg-white rounded-2xl shadow-2xl p-8 text-center text-gray-400">Chargement...</div>}>
          <ResetPasswordContent />
        </Suspense>

        <p className="text-center text-gray-500 text-xs mt-6">
          <Link href="/" className="hover:text-white transition">← Retour à l'accueil</Link>
        </p>
      </div>
    </div>
  )
}
