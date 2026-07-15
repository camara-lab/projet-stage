'use client'
import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { login } from '@/lib/api'
import { saveAuth } from '@/lib/auth'
import toast from 'react-hot-toast'

export default function LoginPage() {
  const router = useRouter()
  const [form, setForm]       = useState({ email: '', password: '' })
  const [loading, setLoading] = useState(false)
  const [showPwd, setShowPwd] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const { data } = await login(form)
      saveAuth(data)
      toast.success('Connexion réussie ! 🎉')
      router.push('/')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Identifiants incorrects')
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
          <p className="text-gray-400 mt-2 text-sm">Connectez-vous à votre compte</p>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl p-8">
          <h1 className="text-2xl font-black text-gray-900 mb-6">Connexion</h1>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                Adresse email
              </label>
              <input
                type="email"
                placeholder="vous@exemple.ma"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                className="input-field"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                Mot de passe
              </label>
              <div className="relative">
                <input
                  type={showPwd ? 'text' : 'password'}
                  placeholder="••••••••"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  className="input-field pr-11"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPwd((v) => !v)}
                  className="absolute inset-y-0 right-3 flex items-center text-gray-400
                             hover:text-gray-700 transition-colors"
                  aria-label={showPwd ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                >
                  {showPwd ? (
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
                      <path strokeLinecap="round" strokeLinejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                           9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  )}
                </button>
              </div>
            </div>

            <div className="flex justify-end -mt-2">
              <Link href="/auth/forgot-password"
                className="text-xs text-sky-600 font-semibold hover:underline">
                Mot de passe oublié ?
              </Link>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                         hover:bg-brand-blue transition disabled:opacity-50 text-base">
              {loading ? 'Connexion en cours...' : 'Se connecter'}
            </button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-gray-500 text-sm">
              Pas encore de compte ?{' '}
              <Link href="/auth/register" className="text-sky-600 font-semibold hover:underline">
                S'inscrire gratuitement
              </Link>
            </p>
          </div>

          {/* Demo accounts 
          <div className="mt-6 bg-gray-50 rounded-xl p-4 border border-gray-100">
            <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
              Comptes de test
            </p>
            <div className="space-y-1 text-xs text-gray-600">
              <div className="flex justify-between">
                <span>passager@test.ma</span>
                <span className="font-mono text-gray-400">mot de passe</span>
              </div>
            </div>
          </div>
          */}
        </div>

        <p className="text-center text-gray-500 text-xs mt-6">
          <Link href="/" className="hover:text-white transition"> ← Retour à l'accueil</Link>
        </p>
      </div>
    </div>
  )
}
