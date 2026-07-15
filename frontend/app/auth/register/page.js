'use client'
import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { register } from '@/lib/api'
import toast from 'react-hot-toast'

function FieldError({ msg }) {
  if (!msg) return null
  return <p className="text-red-500 text-xs mt-1 font-medium">{msg}</p>
}

export default function RegisterPage() {
  const router = useRouter()
  const [form,        setForm]        = useState({ fullName: '', email: '', phone: '', password: '', confirm: '' })
  const [errors,      setErrors]      = useState({})
  const [loading,     setLoading]     = useState(false)
  const [showPwd,     setShowPwd]     = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)

  const set = (field) => (e) => {
    setForm({ ...form, [field]: e.target.value })
    if (errors[field]) setErrors({ ...errors, [field]: null })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setErrors({})

    if (form.password !== form.confirm) {
      setErrors({ confirm: 'Les mots de passe ne correspondent pas.' })
      return
    }

    setLoading(true)
    try {
      await register({
        fullName: form.fullName,
        email:    form.email,
        phone:    form.phone || undefined,
        password: form.password,
      })
      toast.success('Compte créé ! Connectez-vous 🎉')
      router.push('/auth/login')
    } catch (err) {
      const serverErrors = err.response?.data?.errors
      if (serverErrors && typeof serverErrors === 'object') {
        setErrors(serverErrors)
      } else {
        toast.error(err.response?.data?.message || 'Erreur lors de l\'inscription')
      }
    } finally {
      setLoading(false)
    }
  }

  const inputCls = (field) =>
    `input-field ${errors[field] ? 'border-red-400 focus:ring-red-300' : ''}`

  return (
    <div className="min-h-screen bg-gradient-to-br from-brand-dark via-brand-navy to-brand-blue
                    flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-md">

        <div className="text-center mb-8">
          <Link href="/" className="inline-flex items-center gap-2">
            <span className="text-4xl">🚌</span>
            <span className="font-black text-3xl text-white tracking-tight">
              Bus<span className="text-brand-green">Go</span>
            </span>
          </Link>
          <p className="text-gray-400 mt-2 text-sm">Créez votre compte gratuitement</p>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl p-8">
          <h1 className="text-2xl font-black text-gray-900 mb-6">Inscription</h1>

          <form onSubmit={handleSubmit} className="space-y-4" noValidate>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nom complet</label>
              <input type="text" placeholder="Youssef Alami"
                value={form.fullName} onChange={set('fullName')}
                className={inputCls('fullName')} required />
              <FieldError msg={errors.fullName} />
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
              <input type="email" placeholder="vous@exemple.ma"
                value={form.email} onChange={set('email')}
                className={inputCls('email')} required />
              <FieldError msg={errors.email} />
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                Téléphone <span className="text-gray-400 font-normal">(optionnel)</span>
              </label>
              <input type="tel" placeholder="06XXXXXXXX"
                value={form.phone} onChange={set('phone')}
                className={inputCls('phone')} />
              <FieldError msg={errors.phone} />
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">Mot de passe</label>
              <div className="relative">
                <input
                  type={showPwd ? 'text' : 'password'}
                  placeholder="Minimum 8 caractères, 1 majuscule, 1 chiffre"
                  value={form.password} onChange={set('password')}
                  className={`${inputCls('password')} pr-11`}
                  required minLength={8}
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
              <FieldError msg={errors.password} />
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">Confirmer le mot de passe</label>
              <div className="relative">
                <input
                  type={showConfirm ? 'text' : 'password'}
                  placeholder="••••••••"
                  value={form.confirm} onChange={set('confirm')}
                  className={`${inputCls('confirm')} pr-11`}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowConfirm((v) => !v)}
                  className="absolute inset-y-0 right-3 flex items-center text-gray-400
                             hover:text-gray-700 transition-colors"
                  aria-label={showConfirm ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                >
                  {showConfirm ? (
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
              <FieldError msg={errors.confirm} />
            </div>

            {/* Règles du mot de passe */}
            <div className="bg-gray-50 rounded-xl p-3 text-xs text-gray-500 space-y-1">
              {[
                ['8 caractères minimum', form.password.length >= 8],
                ['1 lettre majuscule', /[A-Z]/.test(form.password)],
                ['1 chiffre', /\d/.test(form.password)],
              ].map(([rule, ok]) => (
                <div key={rule} className={`flex items-center gap-2 ${ok ? 'text-green-600' : ''}`}>
                  <span>{ok ? '✓' : '○'}</span> {rule}
                </div>
              ))}
            </div>

            <button type="submit" disabled={loading}
              className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                         hover:bg-brand-blue transition disabled:opacity-50 text-base mt-2">
              {loading ? 'Création du compte...' : 'Créer mon compte'}
            </button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-gray-500 text-sm">
              Déjà un compte ?{' '}
              <Link href="/auth/login" className="text-sky-600 font-semibold hover:underline">
                Se connecter
              </Link>
            </p>
          </div>
        </div>

        <p className="text-center text-gray-500 text-xs mt-6">
          <Link href="/" className="hover:text-white transition">← Retour à l'accueil</Link>
        </p>
      </div>
    </div>
  )
}
