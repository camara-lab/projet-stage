'use client'
import { useState, useRef, useEffect } from 'react'

const TYPES = [
  {
    key:   'adults',
    label: 'Adultes',
    sub:   '12 ans et plus',
    note:  'Tarif plein',
    min:   1,
    color: 'text-gray-500',
  },
  {
    key:   'children',
    label: 'Enfants',
    sub:   '2 – 11 ans',
    note:  '−25 % sur le tarif',
    min:   0,
    color: 'text-sky-600',
  },
  {
    key:   'babies',
    label: 'Bébés',
    sub:   'Moins de 2 ans',
    note:  'Gratuit · sans siège',
    min:   0,
    color: 'text-brand-green',
  },
]

/**
 * Sélecteur de passagers avec compteurs +/−.
 *
 * Props :
 *   value    { adults: number, children: number, babies: number }
 *   onChange (newValue) => void
 *   className  string (optionnel — ajouté sur le wrapper)
 */
export default function PassengerSelector({ value, onChange, className = '' }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    const handler = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const total = value.adults + value.children + value.babies
  const label = (() => {
    const parts = []
    if (value.adults)   parts.push(`${value.adults} adulte${value.adults > 1 ? 's' : ''}`)
    if (value.children) parts.push(`${value.children} enfant${value.children > 1 ? 's' : ''}`)
    if (value.babies)   parts.push(`${value.babies} bébé${value.babies > 1 ? 's' : ''}`)
    return parts.length ? parts.join(' · ') : '1 passager'
  })()

  const inc = (key) => {
    if (total >= 9) return
    onChange({ ...value, [key]: value[key] + 1 })
  }

  const dec = (key) => {
    const type = TYPES.find((t) => t.key === key)
    if (value[key] <= type.min) return
    onChange({ ...value, [key]: value[key] - 1 })
  }

  return (
    <div className={`relative ${className}`} ref={ref}>

      {/* ── Trigger ── */}
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="input-field w-full text-left flex items-center justify-between gap-2"
      >
        <span className="flex items-center gap-2 min-w-0">
          <span className="text-base flex-shrink-0">👥</span>
          <span className="font-medium text-gray-700 truncate text-sm">{label}</span>
        </span>
        <svg
          className={`w-4 h-4 text-gray-400 flex-shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
          fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {/* ── Panel ── */}
      {open && (
        <div className="absolute z-50 top-full mt-2 left-0 bg-white rounded-2xl shadow-2xl
                        border border-gray-100 p-5 w-72">

          <p className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">
            Passagers (max 9)
          </p>

          <div className="space-y-4">
            {TYPES.map(({ key, label, sub, note, min, color }) => (
              <div key={key} className="flex items-center justify-between gap-3">
                {/* Infos */}
                <div className="min-w-0">
                  <div className="text-sm font-semibold text-gray-800">{label}</div>
                  <div className="text-xs text-gray-400">{sub}</div>
                  <div className={`text-xs font-semibold ${color}`}>{note}</div>
                </div>

                {/* Compteur */}
                <div className="flex items-center gap-3 flex-shrink-0">
                  <button
                    type="button"
                    onClick={() => dec(key)}
                    disabled={value[key] <= min}
                    className="w-8 h-8 rounded-full border-2 border-gray-200 flex items-center
                               justify-center text-gray-600 text-lg font-bold leading-none
                               hover:border-brand-green hover:text-brand-green transition
                               disabled:opacity-25 disabled:cursor-not-allowed"
                  >
                    −
                  </button>
                  <span className="w-5 text-center font-black text-gray-900 text-sm tabular-nums">
                    {value[key]}
                  </span>
                  <button
                    type="button"
                    onClick={() => inc(key)}
                    disabled={total >= 9}
                    className="w-8 h-8 rounded-full border-2 border-gray-200 flex items-center
                               justify-center text-gray-600 text-lg font-bold leading-none
                               hover:border-brand-green hover:text-brand-green transition
                               disabled:opacity-25 disabled:cursor-not-allowed"
                  >
                    +
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Note bas de panel */}
          <div className="mt-4 pt-3 border-t border-gray-100 text-xs text-gray-400 leading-relaxed">
            Les bébés voyagent <strong>gratuitement</strong> sans siège attribué.
            Minimum 1 adulte requis.
          </div>

          <button
            type="button"
            onClick={() => setOpen(false)}
            className="mt-3 w-full bg-brand-dark text-white text-sm font-bold py-2.5 rounded-xl
                       hover:bg-brand-blue transition"
          >
            Confirmer · {total} passager{total > 1 ? 's' : ''}
          </button>
        </div>
      )}
    </div>
  )
}

/**
 * Calcule le prix total selon le type de passager.
 *   adults   : tarif plein
 *   children : tarif × 0.75 (−25 %)
 *   babies   : gratuit
 */
export function calcTotalPrice(basePrice, passengers) {
  const price = parseFloat(basePrice) || 0
  return (
    passengers.adults   * price +
    passengers.children * price * 0.75 +
    passengers.babies   * 0
  )
}
