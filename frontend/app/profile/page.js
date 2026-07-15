'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getProfile, updateProfile } from '@/lib/api'
import toast from 'react-hot-toast'
import { ProfileSkeleton } from '@/components/Skeleton'

function FieldError({ msg }) {
  if (!msg) return null
  return <p className="text-red-500 text-xs mt-1 font-medium">{msg}</p>
}

export default function ProfilePage() {
  const router = useRouter()

  const [profile,  setProfile]  = useState(null)
  const [loading,  setLoading]  = useState(true)
  const [saving,   setSaving]   = useState(false)
  const [errors,   setErrors]   = useState({})
  const [form,     setForm]     = useState({
    fullName: '', phone: '', cin: '', currentPassword: '', newPassword: '', confirmPassword: '',
  })
  const [changed, setChanged] = useState(false)

  useEffect(() => {
    if (typeof window !== 'undefined' && !localStorage.getItem('token')) {
      router.push('/auth/login')
      return
    }
    fetchProfile()
  }, [])

  const fetchProfile = async () => {
    try {
      const { data } = await getProfile()
      setProfile(data)
      setForm((f) => ({ ...f, fullName: data.fullName || '', phone: data.phone || '', cin: data.cin || '' }))
    } catch {
      toast.error('Impossible de charger le profil')
      router.push('/')
    } finally {
      setLoading(false)
    }
  }

  const set = (field) => (e) => {
    setForm({ ...form, [field]: e.target.value })
    setChanged(true)
    if (errors[field]) setErrors({ ...errors, [field]: null })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setErrors({})

    if (form.newPassword && form.newPassword !== form.confirmPassword) {
      setErrors({ confirmPassword: 'Les mots de passe ne correspondent pas.' })
      return
    }

    const payload = {}
    if (form.fullName !== profile.fullName) payload.fullName = form.fullName
    if (form.phone    !== (profile.phone || '')) payload.phone = form.phone || null
    if (form.cin      !== (profile.cin   || '')) payload.cin   = form.cin   || null
    if (form.newPassword) {
      payload.currentPassword = form.currentPassword
      payload.newPassword     = form.newPassword
    }

    if (Object.keys(payload).length === 0) {
      toast('Aucune modification détectée', { icon: 'ℹ️' })
      return
    }

    setSaving(true)
    try {
      const { data } = await updateProfile(payload)
      setProfile(data)
      setForm((f) => ({
        ...f,
        fullName: data.fullName || '',
        phone: data.phone || '',
        cin: data.cin || '',
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
      }))
      setChanged(false)
      toast.success('Profil mis à jour !')
    } catch (err) {
      const serverErrors = err.response?.data?.errors
      if (serverErrors) {
        setErrors(serverErrors)
      } else {
        toast.error(err.response?.data?.message || 'Erreur lors de la mise à jour')
      }
    } finally {
      setSaving(false)
    }
  }

  const inputCls = (field) =>
    `input-field ${errors[field] ? 'border-red-400 focus:ring-red-300' : ''}`

  if (loading) return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <ProfileSkeleton />
      <Footer />
    </div>
  )

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <div className="max-w-2xl mx-auto px-4 py-10">
        <button onClick={() => router.back()}
          className="flex items-center gap-2 text-gray-500 hover:text-gray-800 mb-6 text-sm font-medium">
          ← Retour
        </button>

        <h1 className="text-2xl font-black text-gray-900 mb-8">Mon profil</h1>

        {/* Infos statiques */}
        <div className="card mb-6">
          <div className="flex items-center gap-4 mb-4">
            <div className="w-14 h-14 rounded-full bg-brand-dark flex items-center justify-center
                            text-2xl font-black text-brand-green">
              {profile?.fullName?.[0]?.toUpperCase() || '?'}
            </div>
            <div>
              <p className="font-black text-gray-900 text-lg">{profile?.fullName}</p>
              <p className="text-gray-500 text-sm">{profile?.email}</p>
            </div>
            <span className="ml-auto text-xs font-bold px-3 py-1 rounded-full
                             bg-brand-dark text-brand-green uppercase tracking-wide">
              {profile?.role || 'PASSAGER'}
            </span>
          </div>
          <p className="text-xs text-gray-400">
            Membre depuis le {profile?.createdAt
              ? new Date(profile.createdAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
              : '—'}
          </p>
        </div>

        {/* Formulaire */}
        <div className="card">
          <h2 className="text-lg font-black text-gray-900 mb-6">Modifier mes informations</h2>

          <form onSubmit={handleSubmit} className="space-y-5" noValidate>

            {/* Nom complet */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nom complet</label>
              <input type="text" value={form.fullName} onChange={set('fullName')}
                className={inputCls('fullName')} placeholder="Youssef Alami" />
              <FieldError msg={errors.fullName} />
            </div>

            {/* Téléphone */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                Téléphone <span className="text-gray-400 font-normal">(optionnel)</span>
              </label>
              <input type="tel" value={form.phone} onChange={set('phone')}
                className={inputCls('phone')} placeholder="06XXXXXXXX" />
              <FieldError msg={errors.phone} />
            </div>

            {/* CIN */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                CIN <span className="text-gray-400 font-normal">(optionnel)</span>
              </label>
              <input type="text" value={form.cin} onChange={set('cin')}
                className={inputCls('cin')} placeholder="AB123456" maxLength={10} />
              <FieldError msg={errors.cin} />
            </div>

            <div className="border-t border-gray-100 pt-5">
              <p className="text-sm font-semibold text-gray-700 mb-4">Changer de mot de passe</p>

              {/* Mot de passe actuel */}
              <div className="mb-4">
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Mot de passe actuel</label>
                <input type="password" value={form.currentPassword} onChange={set('currentPassword')}
                  className={inputCls('currentPassword')} placeholder="Saisissez votre mot de passe actuel" />
                <FieldError msg={errors.currentPassword} />
              </div>

              {/* Nouveau mot de passe */}
              <div className="mb-4">
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nouveau mot de passe</label>
                <input type="password" value={form.newPassword} onChange={set('newPassword')}
                  className={inputCls('newPassword')} placeholder="Minimum 8 car., 1 majuscule, 1 chiffre" />
                <FieldError msg={errors.newPassword} />
              </div>

              {/* Confirmer nouveau mot de passe */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Confirmer le nouveau mot de passe</label>
                <input type="password" value={form.confirmPassword} onChange={set('confirmPassword')}
                  className={inputCls('confirmPassword')} placeholder="••••••••" />
                <FieldError msg={errors.confirmPassword} />
              </div>

              {/* Indicateur de force */}
              {form.newPassword.length > 0 && (
                <div className="mt-3 bg-gray-50 rounded-xl p-3 text-xs text-gray-500 space-y-1">
                  {[
                    ['8 caractères minimum', form.newPassword.length >= 8],
                    ['1 lettre majuscule',   /[A-Z]/.test(form.newPassword)],
                    ['1 chiffre',            /\d/.test(form.newPassword)],
                  ].map(([rule, ok]) => (
                    <div key={rule} className={`flex items-center gap-2 ${ok ? 'text-green-600' : ''}`}>
                      <span>{ok ? '✓' : '○'}</span> {rule}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <button type="submit" disabled={saving || !changed}
              className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                         hover:bg-brand-blue transition disabled:opacity-40 disabled:cursor-not-allowed text-base">
              {saving ? 'Enregistrement...' : 'Enregistrer les modifications'}
            </button>
          </form>
        </div>
      </div>

      <Footer />
    </div>
  )
}
