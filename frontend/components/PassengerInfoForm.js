'use client'
import { useState } from 'react'

const NATIONALITES = ['Marocaine', 'Française', 'Espagnole', 'Algérienne', 'Tunisienne', 'Sénégalaise', 'Autre']
const REDUCTIONS   = [
  { id: 'ADULTE',   label: 'Adulte (plein tarif)' },
  { id: 'ETUDIANT', label: 'Étudiant' },
  { id: 'ENFANT',   label: 'Enfant' },
  { id: 'SENIOR',   label: 'Senior (60+)' },
]
const BESOINS = [
  { id: '',         label: 'Aucun' },
  { id: 'FAUTEUIL', label: 'Fauteuil roulant' },
  { id: 'PMR',      label: 'Assistance PMR' },
  { id: 'AUTRE',    label: 'Autre' },
]

const NAME_RE     = /^[A-Za-zÀ-ÿ' -]{2,50}$/
const CIN_RE      = /^[A-Za-z]{1,2}\d{4,7}$/
const PASSPORT_RE = /^[A-Za-z0-9]{6,9}$/

export function emptyPassenger(type) {
  return {
    type,                       // 'adult' | 'child' | 'baby'
    civilite:     'M.',
    prenom:       '',
    nom:          '',
    dateNaissance:'',
    nationalite:  'Marocaine',
    docType:      'CIN',
    docNumero:    '',
    docExpiration:'',
    reduction:    type === 'child' ? 'ENFANT' : 'ADULTE',
    besoin:       '',
    besoinAutre:  '',
  }
}

export function validatePassenger(p) {
  const errors = {}
  const today = new Date().toISOString().split('T')[0]

  if (!p.prenom.trim())                 errors.prenom = 'Le prénom est requis'
  else if (!NAME_RE.test(p.prenom.trim())) errors.prenom = 'Prénom invalide (lettres uniquement, min. 2 caractères)'

  if (!p.nom.trim())                    errors.nom = 'Le nom est requis'
  else if (!NAME_RE.test(p.nom.trim())) errors.nom = 'Nom invalide (lettres uniquement, min. 2 caractères)'

  if (!p.dateNaissance)                 errors.dateNaissance = 'La date de naissance est requise'
  else if (p.dateNaissance >= today)    errors.dateNaissance = 'La date doit être dans le passé'

  // Document obligatoire pour les adultes uniquement
  if (p.type === 'adult' || p.docNumero.trim()) {
    if (!p.docNumero.trim()) {
      errors.docNumero = 'Le numéro de document est requis'
    } else if (p.docType === 'CIN' && !CIN_RE.test(p.docNumero.trim())) {
      errors.docNumero = 'Format CIN invalide (ex: AB123456)'
    } else if (p.docType === 'PASSEPORT' && !PASSPORT_RE.test(p.docNumero.trim())) {
      errors.docNumero = 'Format passeport invalide (6 à 9 caractères alphanumériques)'
    }
    if (p.docType === 'PASSEPORT') {
      if (!p.docExpiration)              errors.docExpiration = "La date d'expiration est requise"
      else if (p.docExpiration <= today) errors.docExpiration = 'Le passeport est expiré'
    }
  }

  if (p.besoin === 'AUTRE' && !p.besoinAutre.trim()) {
    errors.besoinAutre = 'Précisez votre besoin'
  }

  return errors
}

export function isPassengerComplete(p) {
  return Object.keys(validatePassenger(p)).length === 0
}

const TYPE_LABELS = { adult: 'Adulte', child: 'Enfant', baby: 'Bébé' }

function Field({ label, required, error, children }) {
  return (
    <div>
      <label className="block text-xs font-bold text-gray-600 mb-1">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      {children}
      {error && <p className="text-xs text-red-500 mt-1">{error}</p>}
    </div>
  )
}

function PassengerCard({ passenger, index, onChange }) {
  const [touched, setTouched] = useState({})
  const errors   = validatePassenger(passenger)
  const complete = Object.keys(errors).length === 0

  const set = (field) => (e) => {
    onChange({ ...passenger, [field]: e.target.value })
  }
  const touch = (field) => () => setTouched((t) => ({ ...t, [field]: true }))
  const err = (field) => (touched[field] ? errors[field] : undefined)

  return (
    <div className={`card border-2 transition ${complete ? 'border-brand-green/40' : 'border-transparent'}`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
          <span>👤</span> Passager {index + 1} – {TYPE_LABELS[passenger.type]}
        </h3>
        {complete && <span className="badge-green">✓ Complet</span>}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <Field label="Civilité">
          <div className="flex gap-2">
            {['M.', 'Mme'].map((c) => (
              <button key={c} type="button"
                onClick={() => onChange({ ...passenger, civilite: c })}
                className={`px-4 py-2 rounded-xl text-sm font-bold border-2 transition
                  ${passenger.civilite === c
                    ? 'border-brand-green bg-green-50 text-brand-dark'
                    : 'border-gray-200 text-gray-500 hover:border-gray-300'}`}>
                {c}
              </button>
            ))}
          </div>
        </Field>

        <Field label="Nationalité">
          <select value={passenger.nationalite} onChange={set('nationalite')} className="input-field text-sm">
            {NATIONALITES.map((n) => <option key={n} value={n}>{n}</option>)}
          </select>
        </Field>

        <Field label="Prénom" required error={err('prenom')}>
          <input value={passenger.prenom} onChange={set('prenom')} onBlur={touch('prenom')}
            placeholder="ex: Youssef" className="input-field text-sm" />
        </Field>

        <Field label="Nom" required error={err('nom')}>
          <input value={passenger.nom} onChange={set('nom')} onBlur={touch('nom')}
            placeholder="ex: Alami" className="input-field text-sm" />
        </Field>

        <Field label="Date de naissance" required error={err('dateNaissance')}>
          <input type="date" value={passenger.dateNaissance} onChange={set('dateNaissance')}
            onBlur={touch('dateNaissance')} max={new Date().toISOString().split('T')[0]}
            className="input-field text-sm" />
        </Field>

        <Field label="Réduction">
          <select value={passenger.reduction} onChange={set('reduction')} className="input-field text-sm">
            {REDUCTIONS.map((r) => <option key={r.id} value={r.id}>{r.label}</option>)}
          </select>
        </Field>

        <Field label="Type de document" required={passenger.type === 'adult'}>
          <div className="flex gap-2">
            {[{ id: 'CIN', label: 'CIN' }, { id: 'PASSEPORT', label: 'Passeport' }].map((d) => (
              <button key={d.id} type="button"
                onClick={() => onChange({ ...passenger, docType: d.id })}
                className={`flex-1 px-3 py-2 rounded-xl text-sm font-bold border-2 transition
                  ${passenger.docType === d.id
                    ? 'border-brand-green bg-green-50 text-brand-dark'
                    : 'border-gray-200 text-gray-500 hover:border-gray-300'}`}>
                {d.label}
              </button>
            ))}
          </div>
        </Field>

        <Field label="Numéro du document" required={passenger.type === 'adult'} error={err('docNumero')}>
          <input value={passenger.docNumero} onChange={set('docNumero')} onBlur={touch('docNumero')}
            placeholder={passenger.docType === 'CIN' ? 'ex: AB123456' : 'ex: XA1234567'}
            className="input-field text-sm uppercase" />
        </Field>

        {passenger.docType === 'PASSEPORT' && (
          <Field label="Date d'expiration du passeport" required error={err('docExpiration')}>
            <input type="date" value={passenger.docExpiration} onChange={set('docExpiration')}
              onBlur={touch('docExpiration')} min={new Date().toISOString().split('T')[0]}
              className="input-field text-sm" />
          </Field>
        )}

        <Field label="Besoins spécifiques (optionnel)">
          <select value={passenger.besoin} onChange={set('besoin')} className="input-field text-sm">
            {BESOINS.map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
          </select>
        </Field>

        {passenger.besoin === 'AUTRE' && (
          <Field label="Précisez votre besoin" required error={err('besoinAutre')}>
            <input value={passenger.besoinAutre} onChange={set('besoinAutre')} onBlur={touch('besoinAutre')}
              placeholder="Décrivez votre besoin" className="input-field text-sm" />
          </Field>
        )}
      </div>
    </div>
  )
}

export default function PassengerInfoForm({ passengers, onChange }) {
  const completeCount = passengers.filter(isPassengerComplete).length

  const updateAt = (index) => (updated) => {
    const next = [...passengers]
    next[index] = updated
    onChange(next)
  }

  return (
    <div className="space-y-4">
      {/* Indicateur de progression */}
      <div className="card !p-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-bold text-gray-700">Informations des passagers</span>
          <span className={`text-xs font-black ${completeCount === passengers.length ? 'text-brand-green' : 'text-gray-400'}`}>
            {completeCount}/{passengers.length} complet{completeCount > 1 ? 's' : ''}
          </span>
        </div>
        <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div className="h-full bg-brand-green rounded-full transition-all duration-300"
            style={{ width: `${(completeCount / passengers.length) * 100}%` }} />
        </div>
      </div>

      {passengers.map((p, i) => (
        <PassengerCard key={i} passenger={p} index={i} onChange={updateAt(i)} />
      ))}
    </div>
  )
}
