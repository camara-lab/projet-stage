'use client'

// Catalogue des extras : prix en DH, max = quantité maximale par réservation
// (frontend uniquement pour l'instant : à brancher sur l'API quand le backend gérera les extras)
export const EXTRAS_CATALOG = [
  {
    group: 'Bagages',
    items: [
      { id: 'BAG_CABINE',  icon: '🎒', name: 'Bagage cabine supplémentaire', desc: 'Un sac de plus à bord (max 10 kg)',        price: 50,  max: 2 },
      { id: 'BAG_SOUTE',   icon: '🧳', name: 'Bagage en soute',              desc: 'Valise supplémentaire en soute (max 23 kg)', price: 30,  max: 3 },
      { id: 'BAG_VOLUME',  icon: '📦', name: 'Bagage volumineux',            desc: 'Objet encombrant (max 32 kg)',               price: 80,  max: 2 },
      { id: 'VELO',        icon: '🚲', name: 'Transport de vélo',            desc: 'Vélo démonté ou housse dédiée',              price: 100, max: 1 },
    ],
  },
  {
    group: 'Confort',
    items: [
      { id: 'SIEGE_PREMIUM', icon: '💺', name: 'Siège Premium',              desc: 'Siège large à l\'avant du bus',    price: 25, max: 1 },
      { id: 'ESPACE_JAMBES', icon: '🦵', name: 'Plus d\'espace jambes',      desc: 'Rangée avec espace supplémentaire', price: 35, max: 1 },
      { id: 'PRIORITAIRE',   icon: '⚡', name: 'Embarquement prioritaire',   desc: 'Montez à bord en premier',          price: 15, max: 1 },
    ],
  },
  {
    group: 'Assurance',
    items: [
      { id: 'ASSUR_ANNUL',  icon: '🛡️', name: 'Assurance annulation', desc: 'Remboursement à 100% jusqu\'à 2h avant le départ', price: 20, max: 1, recommended: true },
      { id: 'ASSUR_VOYAGE', icon: '🏥', name: 'Assurance voyage',     desc: 'Couverture bagages et incidents pendant le trajet', price: 35, max: 1 },
    ],
  },
  {
    group: 'Services complémentaires',
    items: [
      { id: 'SMS',      icon: '💬', name: 'SMS de confirmation',       desc: 'Billet et rappels par SMS',              price: 5,  max: 1 },
      { id: 'WHATSAPP', icon: '📲', name: 'Notification WhatsApp',     desc: 'Suivi du trajet en temps réel',          price: 3,  max: 1 },
      { id: 'PMR',      icon: '♿', name: 'Assistance PMR',            desc: 'Accompagnement personnalisé en gare',     price: 0,  max: 1 },
      { id: 'ANIMAL',   icon: '🐾', name: 'Animal de compagnie',       desc: 'Petit animal en cage (selon disponibilité)', price: 40, max: 1 },
    ],
  },
]

const ALL_ITEMS = EXTRAS_CATALOG.flatMap((g) => g.items)

export function getExtraById(id) {
  return ALL_ITEMS.find((i) => i.id === id)
}

export function calcExtrasTotal(selection) {
  return Object.entries(selection || {}).reduce((sum, [id, qty]) => {
    const item = getExtraById(id)
    return item ? sum + item.price * qty : sum
  }, 0)
}

export function selectedExtrasList(selection) {
  return Object.entries(selection || {})
    .filter(([, qty]) => qty > 0)
    .map(([id, qty]) => ({ ...getExtraById(id), qty }))
    .filter((i) => i.id)
}

function ExtraCard({ item, qty, onSet }) {
  const selected = qty > 0
  const useQty   = item.max > 1

  return (
    <div className={`rounded-xl border-2 p-3.5 transition-all duration-200
      ${selected ? 'border-brand-green bg-green-50 scale-[1.01]' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
      <div className="flex items-start gap-3">
        <span className="text-2xl flex-shrink-0">{item.icon}</span>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-0.5">
            <span className="font-bold text-gray-900 text-sm">{item.name}</span>
            {item.recommended && (
              <span className="text-[10px] font-black bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full uppercase">
                Recommandé
              </span>
            )}
            {item.price === 0 && (
              <span className="text-[10px] font-black bg-green-100 text-green-700 px-2 py-0.5 rounded-full uppercase">
                Gratuit
              </span>
            )}
          </div>
          <p className="text-xs text-gray-400">{item.desc}</p>
          <p className="text-sm font-black text-brand-dark mt-1">
            {item.price === 0 ? 'Inclus' : `${item.price} DH${useQty ? ' / unité' : ''}`}
          </p>
        </div>

        <div className="flex-shrink-0">
          {useQty ? (
            <div className="flex items-center gap-2">
              <button type="button" onClick={() => onSet(Math.max(0, qty - 1))}
                disabled={qty === 0}
                className="w-8 h-8 rounded-full border-2 border-gray-200 font-black text-gray-500
                           hover:border-brand-green hover:text-brand-green transition
                           disabled:opacity-30 disabled:cursor-not-allowed active:scale-90">
                −
              </button>
              <span className={`w-5 text-center font-black text-sm ${selected ? 'text-brand-dark' : 'text-gray-400'}`}>
                {qty}
              </span>
              <button type="button" onClick={() => onSet(Math.min(item.max, qty + 1))}
                disabled={qty >= item.max}
                className="w-8 h-8 rounded-full border-2 border-gray-200 font-black text-gray-500
                           hover:border-brand-green hover:text-brand-green transition
                           disabled:opacity-30 disabled:cursor-not-allowed active:scale-90">
                +
              </button>
            </div>
          ) : (
            <button type="button" onClick={() => onSet(selected ? 0 : 1)}
              role="switch" aria-checked={selected}
              className={`w-12 h-7 rounded-full p-0.5 transition-colors duration-200
                ${selected ? 'bg-brand-green' : 'bg-gray-200'}`}>
              <span className={`block w-6 h-6 bg-white rounded-full shadow transition-transform duration-200
                ${selected ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

export default function ExtrasSelector({ value, onChange }) {
  const setQty = (id, qty) => {
    const next = { ...value }
    if (qty > 0) next[id] = qty
    else delete next[id]
    onChange(next)
  }

  const total = calcExtrasTotal(value)

  return (
    <div className="card">
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-black text-gray-900 text-base">✨ Extras &amp; services</h2>
        {total > 0 && (
          <span className="text-sm font-black text-brand-dark bg-green-50 border border-green-100 px-3 py-1 rounded-full">
            +{total} DH
          </span>
        )}
      </div>

      <div className="space-y-5">
        {EXTRAS_CATALOG.map((group) => (
          <div key={group.group}>
            <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest mb-2.5">
              {group.group}
            </h3>
            <div className="space-y-2.5">
              {group.items.map((item) => (
                <ExtraCard key={item.id} item={item} qty={value?.[item.id] || 0}
                  onSet={(q) => setQty(item.id, q)} />
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
