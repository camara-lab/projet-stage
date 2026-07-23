'use client'
import { useState, useEffect, Suspense } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getMyBookings, cancelBooking, downloadTicket } from '@/lib/api'
import { getBasePrice } from '@/lib/trips'
import { getUser } from '@/lib/auth'
import { BookingsListSkeleton } from '@/components/Skeleton'
import toast from 'react-hot-toast'

function groupBookings(bookings) {
  const groups = []
  const used   = new Set()

  const sorted = [...bookings].sort(
    (a, b) => new Date(b.createdAt) - new Date(a.createdAt)
  )

  for (const b of sorted) {
    if (used.has(b.id)) continue
    const tripId  = b.trip?.id
    const minute  = new Date(b.createdAt).toISOString().slice(0, 16) // "YYYY-MM-DDTHH:MM"
    const peers   = sorted.filter(
      (x) =>
        !used.has(x.id) &&
        x.trip?.id === tripId &&
        new Date(x.createdAt).toISOString().slice(0, 16) === minute
    )
    peers.forEach((p) => used.add(p.id))
    groups.push(peers)
  }
  return groups
}

function groupAmount(group) {
  const fromUnitPrice = group.reduce((s, b) => {
    const a = parseFloat(b.unitPrice)
    return isNaN(a) ? s : s + a
  }, 0)
  if (fromUnitPrice > 0) return fromUnitPrice

  const fromPayment = group.reduce((s, b) => {
    const a = parseFloat(b.payment?.amount)
    return isNaN(a) ? s : s + a
  }, 0)
  if (fromPayment > 0) return fromPayment

  return group.reduce((s, b) => s + getBasePrice(b.trip), 0)
}

function groupStatus(group) {
  const priority = ['CANCELLED', 'REFUNDED', 'PENDING', 'PAID']
  const statuses  = group.map((b) => b.status)
  return priority.find((s) => statuses.includes(s)) ?? group[0].status
}

function StatusBadge({ status }) {
  const map = {
    PAID:      { label: 'Payée',      cls: 'bg-green-100 text-green-700 border border-green-200' },
    PENDING:   { label: 'En attente', cls: 'bg-yellow-100 text-yellow-700 border border-yellow-200' },
    CANCELLED: { label: 'Annulée',    cls: 'bg-red-100 text-red-600 border border-red-200' },
    REFUNDED:  { label: 'Remboursée', cls: 'bg-purple-100 text-purple-700 border border-purple-200' },
  }
  const s = map[status] || { label: status, cls: 'bg-gray-100 text-gray-500' }
  return <span className={`text-xs font-bold px-2.5 py-1 rounded-full ${s.cls}`}>{s.label}</span>
}

function CancelModal({ group, onConfirm, onClose }) {
  if (!group) return null
  const booking    = group[0]
  const dep        = new Date(booking.trip?.departureTime || booking.trip?.heureDepart)
  const isPaid     = group.some((b) => b.status === 'PAID')
  const hoursUntil = (dep - new Date()) / 3_600_000
  const total      = groupAmount(group)

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6" onClick={(e) => e.stopPropagation()}>
        <div className="text-center mb-5">
          <div className="text-5xl mb-3"></div>
          <h2 className="text-xl font-black text-gray-900 mb-1">Annuler la réservation ?</h2>
          <p className="text-gray-500 text-sm">
            {group.length} siège{group.length > 1 ? 's' : ''} ·{' '}
            {booking.trip?.villeDepart ?? booking.trip?.route?.departureCity?.name}
            {' → '}
            {booking.trip?.villeArrivee ?? booking.trip?.route?.arrivalCity?.name}
          </p>
        </div>

        <div className={`rounded-xl p-4 mb-5 text-sm ${isPaid ? 'bg-amber-50 border border-amber-200' : 'bg-gray-50 border border-gray-200'}`}>
          <p className="font-bold mb-2 text-gray-800">
            {isPaid ? ' Conditions de remboursement' : 'ℹ Annulation sans frais'}
          </p>
          {isPaid ? (
            <ul className="space-y-1 text-gray-600 text-xs">
              <li>• {hoursUntil > 48
                ? `Remboursement intégral : ${total.toFixed(2)} DH (départ dans plus de 48h)`
                : hoursUntil > 24
                  ? ` Remboursement à 50% : ${(total * 0.5).toFixed(2)} DH (moins de 48h)`
                  : ' Aucun remboursement (départ dans moins de 24h)'}</li>
              <li>• Remboursement sous 5 à 7 jours ouvrés</li>
            </ul>
          ) : (
            <p className="text-gray-600 text-xs">Vos réservations en attente seront annulées sans frais.</p>
          )}
        </div>

        <div className="flex gap-3">
          <button onClick={onClose}
            className="flex-1 border-2 border-gray-200 text-gray-600 font-semibold py-3 rounded-xl hover:border-gray-300 transition text-sm">
            Garder
          </button>
          <button onClick={() => onConfirm(group.map((b) => b.id))}
            className="flex-1 bg-red-500 text-white font-bold py-3 rounded-xl hover:bg-red-600 transition text-sm">
            Confirmer l'annulation
          </button>
        </div>
      </div>
    </div>
  )
}

function BookingGroupCard({ group, onCancel, onDownload }) {
  const first     = group[0]
  const depRaw    = first.trip?.heureDepart ?? first.trip?.departureTime
  const dep       = depRaw ? new Date(depRaw) : null
  const isPast    = dep ? dep < new Date() : false
  const status    = groupStatus(group)
  const total     = groupAmount(group)
  const seats     = group.map((b) => b.seatNumber).filter(Boolean).sort((a, b) => a - b)
  const basePrice = getBasePrice(first.trip)

  const villeDepart  = first.trip?.villeDepart  ?? first.trip?.route?.departureCity?.name ?? '—'
  const villeArrivee = first.trip?.villeArrivee ?? first.trip?.route?.arrivalCity?.name ?? '—'

  const payMethod = group.find((b) => b.payment?.paymentMethod)?.payment?.paymentMethod

  const allIds    = group.map((b) => b.id)
  const firstPaid = group.find((b) => b.status === 'PAID')
  const firstPending = group.find((b) => b.status === 'PENDING')

  return (
    <div className={`card transition-all ${status === 'CANCELLED' ? 'opacity-50' : 'hover:shadow-md'}`}>
      <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-3 mb-3 flex-wrap">
            <span className="font-black text-gray-900 text-lg">{villeDepart} → {villeArrivee}</span>
            <StatusBadge status={status} />
            {group.length > 1 && (
              <span className="text-xs bg-brand-dark text-white font-bold px-2 py-0.5 rounded-full">
                {group.length} billets
              </span>
            )}
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-3">
            <div>
              <div className="text-gray-400 text-xs mb-0.5">Date de départ</div>
              <div className="font-semibold text-gray-800">
                {dep ? dep.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : '—'}
              </div>
            </div>
            <div>
              <div className="text-gray-400 text-xs mb-0.5">Heure</div>
              <div className="font-semibold text-gray-800">
                {dep ? dep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
              </div>
            </div>
            <div>
              <div className="text-gray-400 text-xs mb-0.5">Sièges</div>
              <div className="flex flex-wrap gap-1">
                {seats.length > 0
                  ? seats.map((s) => (
                    <span key={s} className="text-xs bg-brand-green/10 text-brand-dark font-bold px-1.5 py-0.5 rounded">
                      N°{s}
                    </span>
                  ))
                  : <span className="font-semibold text-gray-800">—</span>}
              </div>
            </div>
            <div>
              <div className="text-gray-400 text-xs mb-0.5">Référence{group.length > 1 ? 's' : ''}</div>
              <div className="space-y-0.5">
                {allIds.slice(0, 3).map((id) => (
                  <div key={id} className="font-mono text-xs text-gray-500">#{String(id).padStart(5, '0')}</div>
                ))}
                {allIds.length > 3 && (
                  <div className="text-xs text-gray-400">+{allIds.length - 3} autres</div>
                )}
              </div>
            </div>
          </div>

          {group.length > 1 && (
            <div className="bg-gray-50 rounded-xl px-3 py-2 text-xs text-gray-500 space-y-0.5">
              {group.map((b) => (
                <div key={b.id} className="flex justify-between">
                  <span>Siège N°{b.seatNumber}</span>
                  <span className="font-semibold">
                    {parseFloat(b.unitPrice ?? b.payment?.amount ?? basePrice).toFixed(2)} DH
                  </span>
                </div>
              ))}
              {payMethod && (
                <div className="pt-1 text-gray-400 border-t border-gray-200 mt-1">
                  Payé par {payMethod}
                </div>
              )}
            </div>
          )}
        </div>

        <div className="flex flex-col items-end gap-2 min-w-[170px]">
          <div className="text-right mb-1">
            <div className="text-2xl font-black text-brand-dark">{total.toFixed(2)} DH</div>
            <div className="text-xs text-gray-400">
              {group.length > 1 ? `${group.length} billets` : '1 billet'}
              {payMethod ? ` · ${payMethod}` : ''}
            </div>
          </div>

          <div className="flex flex-col gap-2 w-full">
            {firstPaid && (
              <button onClick={() => onDownload(firstPaid.id)}
                className="w-full bg-brand-dark text-white text-xs font-bold py-2.5 px-4 rounded-xl
                           hover:bg-brand-blue transition flex items-center justify-center gap-1.5">
                📄 Télécharger le billet
              </button>
            )}

            {firstPending && (
              <a href={`/payment/${firstPending.id}?bookings=${allIds.join(',')}`}
                className="w-full bg-brand-green text-brand-dark text-xs font-bold py-2.5 px-4
                           rounded-xl hover:bg-green-400 transition text-center">
                💳 Payer {total.toFixed(2)} DH
              </a>
            )}

            {['PAID', 'PENDING'].includes(status) && !isPast && (
              <button onClick={() => onCancel(group)}
                className="w-full border-2 border-red-200 text-red-500 text-xs font-bold py-2 px-4
                           rounded-xl hover:bg-red-50 transition">
                Annuler
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

function LoyaltyBlock({ paidCount, totalSpent }) {
  const points  = Math.floor(totalSpent / 10)
  const tiers   = [
    { label: 'Découvreur',  min: 0,   color: 'from-gray-400 to-gray-500',      icon: '🌱' },
    { label: 'Voyageur',    min: 50,  color: 'from-sky-400 to-blue-600',        icon: '✈️' },
    { label: 'Explorateur', min: 200, color: 'from-purple-400 to-purple-700',   icon: '🗺️' },
    { label: 'Elite',       min: 500, color: 'from-amber-400 to-yellow-500',    icon: '⭐' },
  ]
  const tier     = [...tiers].reverse().find((t) => points >= t.min) ?? tiers[0]
  const nextTier = tiers[tiers.indexOf(tier) + 1]
  const progress = nextTier
    ? Math.min(100, ((points - tier.min) / (nextTier.min - tier.min)) * 100)
    : 100

  return (
    <div className="card overflow-hidden">
      <div className={`bg-gradient-to-r ${tier.color} rounded-xl p-4 mb-4 text-white`}>
        <div className="flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold opacity-80 mb-0.5">Statut fidélité</div>
            <div className="text-xl font-black">{tier.icon} {tier.label}</div>
          </div>
          <div className="text-right">
            <div className="text-3xl font-black">{points}</div>
            <div className="text-xs opacity-80">points</div>
          </div>
        </div>
        {nextTier && (
          <div className="mt-3">
            <div className="flex justify-between text-xs opacity-80 mb-1">
              <span>{tier.label}</span>
              <span>{nextTier.label} ({nextTier.min} pts)</span>
            </div>
            <div className="h-1.5 bg-white/30 rounded-full overflow-hidden">
              <div className="h-full bg-white rounded-full transition-all" style={{ width: `${progress}%` }} />
            </div>
            <div className="text-xs opacity-70 mt-1 text-right">
              {Math.max(0, nextTier.min - points)} pts pour {nextTier.label}
            </div>
          </div>
        )}
      </div>
      <div className="grid grid-cols-2 gap-3 text-center">
        <div>
          <div className="text-2xl font-black text-gray-900">{paidCount}</div>
          <div className="text-xs text-gray-400 font-medium">Trajets payés</div>
        </div>
        <div>
          <div className="text-2xl font-black text-gray-900">{totalSpent.toFixed(0)} DH</div>
          <div className="text-xs text-gray-400 font-medium">Total dépensé</div>
        </div>
      </div>
      <p className="text-xs text-gray-400 text-center mt-3">10 DH = 1 point · Utilisable en réductions</p>
    </div>
  )
}

function DashboardContent() {
  const router       = useRouter()
  const searchParams = useSearchParams()
  const [bookings,     setBookings]     = useState([])
  const [loading,      setLoading]      = useState(true)
  const [filter,       setFilter]       = useState('ALL')
  const [cancelGroup,  setCancelGroup]  = useState(null)
  const user = typeof window !== 'undefined' ? getUser() : null
  const firstName = user?.fullName?.split(' ')[0] ?? user?.username?.split('@')[0] ?? 'Voyageur'

  useEffect(() => {
    if (!localStorage.getItem('token')) { router.push('/auth/login'); return }
    if (searchParams.get('paid') === '1') toast.success('Paiement confirmé ! Bonne route 🎉', { duration: 4000 })
    fetchBookings()
  }, [])

  const fetchBookings = async () => {
    try {
      const { data } = await getMyBookings()
      setBookings(Array.isArray(data) ? data : data.bookings || [])
    } catch {
      toast.error('Impossible de charger vos réservations')
    } finally {
      setLoading(false)
    }
  }

  const handleCancelConfirm = async (ids) => {
    setCancelGroup(null)
    try {
      await Promise.all(ids.map((id) => cancelBooking(id)))
      toast.success(`${ids.length} réservation${ids.length > 1 ? 's' : ''} annulée${ids.length > 1 ? 's' : ''}`)
      fetchBookings()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Erreur lors de l\'annulation')
    }
  }

  const handleDownload = async (id) => {
    try {
      const { data } = await downloadTicket(id)
      const url  = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href  = url
      link.download = `billet-busgo-${String(id).padStart(5, '0')}.pdf`
      link.click()
      URL.revokeObjectURL(url)
      toast.success('Billet téléchargé !')
    } catch {
      toast.error('Impossible de télécharger le billet')
    }
  }

  const groups = groupBookings(bookings)

  const paidGroups    = groups.filter((g) => groupStatus(g) === 'PAID')
  const pendingGroups = groups.filter((g) => groupStatus(g) === 'PENDING')
  const totalSpent    = paidGroups.reduce((s, g) => s + groupAmount(g), 0)

  const FILTER_LABELS = { ALL:'Toutes', PAID:'Payées', PENDING:'En attente', CANCELLED:'Annulées', REFUNDED:'Remboursées' }

  const filteredGroups = filter === 'ALL'
    ? groups
    : groups.filter((g) => groupStatus(g) === filter)

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <CancelModal group={cancelGroup} onConfirm={handleCancelConfirm} onClose={() => setCancelGroup(null)} />

      <div className="max-w-5xl mx-auto px-4 py-10">
        <div className="flex items-start justify-between mb-8 gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-black text-gray-900">Bonjour {firstName} </h1>
            <p className="text-gray-400 text-sm mt-1">Gérez vos réservations et billets</p>
          </div>
          <a href="/" className="bg-brand-dark text-white font-bold text-sm py-2.5 px-5 rounded-xl hover:bg-brand-blue transition flex items-center gap-2">
            Nouvelle réservation
          </a>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div className="card text-center">
            <div className="text-3xl font-black text-gray-900 mb-1">{groups.length}</div>
            <div className="text-xs text-gray-400 font-medium">Réservations</div>
          </div>
          <div className="card text-center">
            <div className="text-3xl font-black text-green-600 mb-1">{paidGroups.length}</div>
            <div className="text-xs text-gray-400 font-medium">Payées</div>
          </div>
          <div className="card text-center">
            <div className="text-3xl font-black text-yellow-600 mb-1">{pendingGroups.length}</div>
            <div className="text-xs text-gray-400 font-medium">En attente</div>
          </div>
          <div>
            <LoyaltyBlock paidCount={paidGroups.length} totalSpent={totalSpent} />
          </div>
        </div>

        <div className="flex flex-wrap gap-2 mb-6">
          {['ALL','PAID','PENDING','CANCELLED','REFUNDED'].map((f) => (
            <button key={f} onClick={() => setFilter(f)}
              className={`px-4 py-1.5 rounded-full text-xs font-bold transition border-2
                ${filter === f ? 'bg-brand-dark text-white border-brand-dark' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'}`}>
              {FILTER_LABELS[f]}
              {f === 'ALL'     && ` (${groups.length})`}
              {f === 'PAID'    && ` (${paidGroups.length})`}
              {f === 'PENDING' && ` (${pendingGroups.length})`}
            </button>
          ))}
        </div>

        {loading ? (
          <BookingsListSkeleton />
        ) : filteredGroups.length === 0 ? (
          <div className="text-center py-20">
            <div className="text-6xl mb-4">🎫</div>
            <h2 className="text-xl font-bold text-gray-700 mb-2">Aucune réservation</h2>
            <p className="text-gray-400 text-sm mb-6">
              {filter === 'ALL' ? 'Commencez par rechercher un trajet' : `Aucune réservation "${FILTER_LABELS[filter]}"`}
            </p>
            {filter !== 'ALL'
              ? <button onClick={() => setFilter('ALL')} className="text-sm text-sky-600 font-semibold hover:underline mr-4">Voir toutes</button>
              : null}
            <a href="/" className="bg-brand-green text-brand-dark font-bold px-6 py-3 rounded-xl hover:bg-green-400 transition inline-block">
              Rechercher un trajet →
            </a>
          </div>
        ) : (
          <div className="space-y-4">
            {filteredGroups.map((group) => (
              <BookingGroupCard
                key={group.map((b) => b.id).join('-')}
                group={group}
                onCancel={setCancelGroup}
                onDownload={handleDownload}
              />
            ))}
          </div>
        )}
      </div>

      <Footer />
    </div>
  )
}

export default function DashboardPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center"><div className="text-5xl animate-bounce">🎫</div></div>}>
      <DashboardContent />
    </Suspense>
  )
}
