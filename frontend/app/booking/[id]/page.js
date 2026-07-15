'use client'
import { useState, useEffect, Suspense } from 'react'
import { useRouter, useParams, useSearchParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { getTrip, createBooking } from '@/lib/api'
import { calcTotalPrice } from '@/components/PassengerSelector'
import { normalizePassengers } from '@/lib/passengers'
import { getBasePrice } from '@/lib/trips'
import toast from 'react-hot-toast'

// Premiers `adults` sièges → ADULT, le reste → CHILD
function autoAssign(seats, adults) {
  const result = {}
  seats.forEach((s, i) => {
    result[s] = i < adults ? 'ADULT' : 'CHILD'
  })
  return result
}

// Grille de sièges réutilisable pour aller et retour
function SeatMapGrid({ rows, totalSeats, bookedSeats, selectedSeats, onToggle, requiredSeats }) {
  const isSeatBooked   = (n) => Array.isArray(bookedSeats) && bookedSeats.includes(n)
  const isSeatSelected = (n) => selectedSeats.includes(n)

  return (
    <div className="space-y-2">
      {Array.from({ length: rows }, (_, row) => (
        <div key={row} className="flex gap-2 items-center">
          <div className="flex gap-1.5">
            {[1, 2].map((col) => {
              const n = row * 4 + col
              if (n > totalSeats) return <div key={col} className="w-9 h-9" />
              const booked   = isSeatBooked(n)
              const selected = isSeatSelected(n)
              const full     = !selected && selectedSeats.length >= requiredSeats
              return (
                <button key={col}
                  onClick={() => !booked && onToggle(n)}
                  disabled={booked || full}
                  title={booked ? 'Occupé' : full ? 'Nombre de sièges atteint' : `Siège ${n}`}
                  className={`w-9 h-9 rounded-lg text-xs font-bold transition-all border-2
                    ${booked
                      ? 'bg-red-100 border-red-200 text-red-300 cursor-not-allowed'
                      : selected
                        ? 'bg-brand-green border-green-400 text-brand-dark scale-110 shadow-md'
                        : full
                          ? 'bg-gray-50 border-gray-100 text-gray-300 cursor-not-allowed'
                          : 'bg-white border-gray-200 text-gray-600 hover:border-brand-green hover:bg-green-50'
                    }`}>
                  {n}
                </button>
              )
            })}
          </div>
          <div className="w-6 text-center text-xs text-gray-300 font-medium">{row + 1}</div>
          <div className="flex gap-1.5">
            {[3, 4].map((col) => {
              const n = row * 4 + col
              if (n > totalSeats) return <div key={col} className="w-9 h-9" />
              const booked   = isSeatBooked(n)
              const selected = isSeatSelected(n)
              const full     = !selected && selectedSeats.length >= requiredSeats
              return (
                <button key={col}
                  onClick={() => !booked && onToggle(n)}
                  disabled={booked || full}
                  title={booked ? 'Occupé' : full ? 'Nombre de sièges atteint' : `Siège ${n}`}
                  className={`w-9 h-9 rounded-lg text-xs font-bold transition-all border-2
                    ${booked
                      ? 'bg-red-100 border-red-200 text-red-300 cursor-not-allowed'
                      : selected
                        ? 'bg-brand-green border-green-400 text-brand-dark scale-110 shadow-md'
                        : full
                          ? 'bg-gray-50 border-gray-100 text-gray-300 cursor-not-allowed'
                          : 'bg-white border-gray-200 text-gray-600 hover:border-brand-green hover:bg-green-50'
                    }`}>
                  {n}
                </button>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}

function BookingContent() {
  const { id }       = useParams()
  const router       = useRouter()
  const searchParams = useSearchParams()

  const { adults, children, babies } = normalizePassengers(searchParams)
  const returnTripId = searchParams.get('returnTripId') || null
  const isRoundTrip  = !!returnTripId

  const requiredSeats = adults + children

  // ── État aller ──
  const [trip,        setTrip]        = useState(null)
  const [seats,       setSeats]       = useState([])
  const [assignments, setAssignments] = useState({})
  const [loading,     setLoading]     = useState(true)

  // ── État retour ──
  const [step,              setStep]              = useState(1)
  const [returnTrip,        setReturnTrip]        = useState(null)
  const [returnSeats,       setReturnSeats]       = useState([])
  const [returnAssignments, setReturnAssignments] = useState({})
  const [returnLoading,     setReturnLoading]     = useState(false)

  const [booking, setBooking] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('token')) { router.push('/auth/login'); return }
    getTrip(id)
      .then(({ data }) => setTrip(data))
      .catch(() => { toast.error('Trajet introuvable'); router.push('/trips') })
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    if (!returnTripId) return
    setReturnLoading(true)
    getTrip(returnTripId)
      .then(({ data }) => setReturnTrip(data))
      .catch(() => toast.error('Trajet retour introuvable'))
      .finally(() => setReturnLoading(false))
  }, [returnTripId])

  // ── Handlers aller ──
  const toggleSeat = (n) => {
    let newSeats
    if (seats.includes(n)) {
      newSeats = seats.filter((s) => s !== n)
    } else {
      if (seats.length >= requiredSeats) {
        toast.error(`Vous avez besoin de ${requiredSeats} siège${requiredSeats > 1 ? 's' : ''} seulement`)
        return
      }
      newSeats = [...seats, n]
    }
    setSeats(newSeats)
    setAssignments(autoAssign(newSeats, adults))
  }

  const assignSeat = (seatNumber, type) => {
    const currentAdults   = Object.values(assignments).filter((t) => t === 'ADULT').length
    const currentChildren = Object.values(assignments).filter((t) => t === 'CHILD').length
    if (type === 'ADULT' && assignments[seatNumber] !== 'ADULT' && currentAdults >= adults) {
      toast.error(`Maximum ${adults} adulte${adults > 1 ? 's' : ''}`)
      return
    }
    if (type === 'CHILD' && assignments[seatNumber] !== 'CHILD' && currentChildren >= children) {
      toast.error(`Maximum ${children} enfant${children > 1 ? 's' : ''}`)
      return
    }
    setAssignments((prev) => ({ ...prev, [seatNumber]: type }))
  }

  // ── Handlers retour ──
  const toggleReturnSeat = (n) => {
    let newSeats
    if (returnSeats.includes(n)) {
      newSeats = returnSeats.filter((s) => s !== n)
    } else {
      if (returnSeats.length >= requiredSeats) {
        toast.error(`Vous avez besoin de ${requiredSeats} siège${requiredSeats > 1 ? 's' : ''} seulement`)
        return
      }
      newSeats = [...returnSeats, n]
    }
    setReturnSeats(newSeats)
    setReturnAssignments(autoAssign(newSeats, adults))
  }

  const assignReturnSeat = (seatNumber, type) => {
    const currentAdults   = Object.values(returnAssignments).filter((t) => t === 'ADULT').length
    const currentChildren = Object.values(returnAssignments).filter((t) => t === 'CHILD').length
    if (type === 'ADULT' && returnAssignments[seatNumber] !== 'ADULT' && currentAdults >= adults) {
      toast.error(`Maximum ${adults} adulte${adults > 1 ? 's' : ''}`)
      return
    }
    if (type === 'CHILD' && returnAssignments[seatNumber] !== 'CHILD' && currentChildren >= children) {
      toast.error(`Maximum ${children} enfant${children > 1 ? 's' : ''}`)
      return
    }
    setReturnAssignments((prev) => ({ ...prev, [seatNumber]: type }))
  }

  if (loading) return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="text-5xl mb-4 animate-bounce">🚌</div>
        <p className="text-gray-500">Chargement du trajet...</p>
      </div>
    </div>
  )
  if (!trip) return null

  // ── Valeurs dérivées — aller ──
  const dep         = new Date(trip.heureDepart)
  const arr         = new Date(trip.heureArrivee)
  const totalSeats  = trip.capaciteBus || 40
  const bookedSeats = trip.siegesOccupes || []
  const rows        = Math.ceil(totalSeats / 4)
  const basePrice   = getBasePrice(trip)
  const total       = calcTotalPrice(basePrice, { adults, children, babies })
  const childPrice  = +(basePrice * 0.75).toFixed(2)
  const multiPax    = (adults + children + babies) > 1

  const needsAssignment     = children > 0
  const assignedAdults      = Object.values(assignments).filter((t) => t === 'ADULT').length
  const assignedChildren    = Object.values(assignments).filter((t) => t === 'CHILD').length
  const assignmentComplete  = !needsAssignment ||
    (seats.length === requiredSeats && assignedAdults === adults && assignedChildren === children)
  const canBook = seats.length === requiredSeats && assignmentComplete && !booking

  // ── Valeurs dérivées — retour ──
  const retDep               = returnTrip ? new Date(returnTrip.heureDepart) : null
  const retArr               = returnTrip ? new Date(returnTrip.heureArrivee) : null
  const retTotalSeats        = returnTrip?.capaciteBus || 40
  const retBookedSeats       = returnTrip?.siegesOccupes || []
  const retRows              = Math.ceil(retTotalSeats / 4)
  const retBasePrice         = returnTrip ? getBasePrice(returnTrip) : 0
  const retTotal             = calcTotalPrice(retBasePrice, { adults, children, babies })
  const retChildPrice        = +(retBasePrice * 0.75).toFixed(2)
  const retAssignedAdults    = Object.values(returnAssignments).filter((t) => t === 'ADULT').length
  const retAssignedChildren  = Object.values(returnAssignments).filter((t) => t === 'CHILD').length
  const retAssignComplete    = !needsAssignment ||
    (returnSeats.length === requiredSeats && retAssignedAdults === adults && retAssignedChildren === children)
  const canReturnBook = returnSeats.length === requiredSeats && retAssignComplete && !booking

  // ── Label du bouton ──
  const btnLabel = (() => {
    if (booking) return 'Réservation en cours...'
    if (step === 1) {
      if (seats.length < requiredSeats) {
        const missing = requiredSeats - seats.length
        return `Sélectionnez ${missing} siège${missing > 1 ? 's' : ''} de plus`
      }
      if (!assignmentComplete) return 'Assignez les types de passagers ↓'
      if (isRoundTrip) return 'Continuer : sièges retour →'
      return `Réserver ${requiredSeats} siège${requiredSeats > 1 ? 's' : ''} · ${total.toFixed(2)} DH →`
    }
    // step 2
    if (returnSeats.length < requiredSeats) {
      const missing = requiredSeats - returnSeats.length
      return `Sélectionnez ${missing} siège${missing > 1 ? 's' : ''} de plus`
    }
    if (!retAssignComplete) return 'Assignez les types de passagers ↓'
    return `Réserver les 2 trajets · ${(total + retTotal).toFixed(2)} DH →`
  })()

  const btnDisabled = booking ||
    (step === 1 && !canBook) ||
    (step === 2 && !canReturnBook)

  const handleBook = async () => {
    if (isRoundTrip && step === 1 && canBook) {
      setStep(2)
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return
    }
    if (btnDisabled) return
    setBooking(true)
    try {
      const results = []
      for (const seatNumber of seats) {
        const passengerType = assignments[seatNumber] || 'ADULT'
        const { data } = await createBooking({ tripId: parseInt(id), seatNumber, passengerType })
        results.push(data)
      }
      if (isRoundTrip) {
        for (const seatNumber of returnSeats) {
          const passengerType = returnAssignments[seatNumber] || 'ADULT'
          const { data } = await createBooking({ tripId: parseInt(returnTripId), seatNumber, passengerType })
          results.push(data)
        }
      }
      toast.success(`${results.length} réservation${results.length > 1 ? 's' : ''} créée${results.length > 1 ? 's' : ''} !`)
      const allIds = results.map((r) => r.id).join(',')
      router.push(
        `/payment/${results[0].id}?bookings=${allIds}&adults=${adults}&children=${children}&babies=${babies}`
      )
    } catch (err) {
      toast.error(err.response?.data?.message || 'Erreur lors de la réservation')
    } finally {
      setBooking(false)
    }
  }

  // Données actives selon l'étape
  const activeTrip        = step === 1 ? trip        : returnTrip
  const activeDep         = step === 1 ? dep         : retDep
  const activeArr         = step === 1 ? arr         : retArr
  const activeTotalSeats  = step === 1 ? totalSeats  : retTotalSeats
  const activeBookedSeats = step === 1 ? bookedSeats : retBookedSeats
  const activeRows        = step === 1 ? rows        : retRows
  const activeBasePrice   = step === 1 ? basePrice   : retBasePrice
  const activeTotal       = step === 1 ? total       : retTotal
  const activeChildPrice  = step === 1 ? childPrice  : retChildPrice
  const activeSeats       = step === 1 ? seats       : returnSeats
  const activeToggle      = step === 1 ? toggleSeat  : toggleReturnSeat
  const activeAssign      = step === 1 ? assignSeat  : assignReturnSeat
  const activeAssignments = step === 1 ? assignments : returnAssignments
  const activeAssignedA   = step === 1 ? assignedAdults   : retAssignedAdults
  const activeAssignedC   = step === 1 ? assignedChildren : retAssignedChildren
  const activeAssignDone  = step === 1 ? assignmentComplete : retAssignComplete

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <div className="max-w-5xl mx-auto px-4 py-10">

        {/* Bouton retour */}
        <button
          onClick={() => {
            if (isRoundTrip && step === 2) { setStep(1); window.scrollTo({ top: 0, behavior: 'smooth' }) }
            else router.back()
          }}
          className="flex items-center gap-2 text-gray-500 hover:text-gray-800 mb-6 text-sm font-medium">
          ← {isRoundTrip && step === 2 ? 'Sièges aller' : 'Retour aux trajets'}
        </button>

        {/* Stepper aller-retour */}
        {isRoundTrip && (
          <div className="flex items-center gap-3 mb-8 bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div className={`flex items-center gap-2 ${step > 1 ? 'text-brand-green' : 'text-brand-dark'}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-black flex-shrink-0
                ${step > 1 ? 'bg-brand-green text-white' : 'bg-brand-dark text-white'}`}>
                {step > 1 ? '✓' : '1'}
              </div>
              <div>
                <div className="text-sm font-bold">Sièges aller</div>
                {step > 1 && seats.length > 0 && (
                  <div className="text-xs text-gray-500">
                    {trip.villeDepart} → {trip.villeArrivee} · sièges {seats.join(', ')}
                  </div>
                )}
              </div>
            </div>
            <div className="flex-1 h-0.5 bg-gray-200 relative overflow-hidden rounded-full">
              <div className={`absolute inset-y-0 left-0 bg-brand-green transition-all duration-500 ${step > 1 ? 'right-0' : 'right-full'}`} />
            </div>
            <div className={`flex items-center gap-2 ${step === 2 ? 'text-brand-dark' : 'text-gray-400'}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-black flex-shrink-0
                ${step === 2 ? 'bg-brand-dark text-white' : 'bg-gray-200 text-gray-400'}`}>
                2
              </div>
              <div className="text-sm font-bold">Sièges retour</div>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

          {/* ── Plan de bus ── */}
          <div className="lg:col-span-2">
            <div className="card">
              <h2 className="text-lg font-black text-gray-900 mb-0.5">
                {isRoundTrip
                  ? (step === 1 ? 'Étape 1 — Sièges aller' : 'Étape 2 — Sièges retour')
                  : 'Choisissez vos sièges'}
              </h2>
              {activeTrip && (
                <p className="text-sm text-gray-500 mb-4">
                  {activeTrip.villeDepart} → {activeTrip.villeArrivee}
                  {activeDep && ` · ${activeDep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`}
                </p>
              )}

              <div className="flex items-center gap-3 mb-5">
                <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                  <div
                    className="h-full bg-brand-green rounded-full transition-all duration-300"
                    style={{ width: `${(activeSeats.length / requiredSeats) * 100}%` }}
                  />
                </div>
                <span className={`text-sm font-black whitespace-nowrap
                  ${activeSeats.length === requiredSeats ? 'text-brand-green' : 'text-gray-500'}`}>
                  {activeSeats.length} / {requiredSeats} siège{requiredSeats > 1 ? 's' : ''}
                </span>
              </div>

              {/* Badges passagers */}
              <div className="flex flex-wrap gap-2 mb-5">
                {adults > 0 && (
                  <span className="text-xs bg-gray-100 text-gray-600 font-semibold px-2.5 py-1 rounded-full">
                    👤 {adults} adulte{adults > 1 ? 's' : ''} · siège requis
                  </span>
                )}
                {children > 0 && (
                  <span className="text-xs bg-sky-50 text-sky-600 font-semibold px-2.5 py-1 rounded-full">
                    🧒 {children} enfant{children > 1 ? 's' : ''} · siège requis
                  </span>
                )}
                {babies > 0 && (
                  <span className="text-xs bg-green-50 text-brand-green font-semibold px-2.5 py-1 rounded-full">
                    👶 {babies} bébé{babies > 1 ? 's' : ''} · sur les genoux · gratuit
                  </span>
                )}
              </div>

              {/* Légende */}
              <div className="flex flex-wrap gap-4 mb-5 text-xs font-medium">
                <div className="flex items-center gap-2">
                  <div className="w-5 h-5 rounded bg-gray-200 border border-gray-300"></div>
                  <span className="text-gray-500">Disponible</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-5 h-5 rounded bg-brand-green"></div>
                  <span className="text-gray-500">Sélectionné</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-5 h-5 rounded bg-red-200"></div>
                  <span className="text-gray-500">Occupé</span>
                </div>
              </div>

              <div className="flex justify-end mb-4 pr-2">
                <div className="w-10 h-10 rounded-full border-4 border-gray-300
                                flex items-center justify-center text-lg"></div>
              </div>

              {step === 2 && returnLoading ? (
                <div className="flex items-center justify-center py-10 text-gray-400">
                  <div className="text-3xl animate-bounce mr-3">🚌</div>
                  Chargement du plan retour…
                </div>
              ) : (
                <SeatMapGrid
                  rows={activeRows}
                  totalSeats={activeTotalSeats}
                  bookedSeats={activeBookedSeats}
                  selectedSeats={activeSeats}
                  onToggle={activeToggle}
                  requiredSeats={requiredSeats}
                />
              )}
            </div>
          </div>

          {/* ── Colonne droite ── */}
          <div className="space-y-4">

            {/* Résumé aller (compact, visible en étape 2) */}
            {isRoundTrip && step === 2 && (
              <div className="bg-green-50 border-2 border-brand-green rounded-xl p-3">
                <div className="text-xs font-bold text-brand-green uppercase tracking-wider mb-1">✓ Aller confirmé</div>
                <div className="font-black text-gray-900 text-sm">
                  {trip.villeDepart} → {trip.villeArrivee}
                </div>
                <div className="text-xs text-gray-500">
                  {dep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })} · sièges {seats.join(', ')} · {total.toFixed(2)} DH
                </div>
              </div>
            )}

            {/* Récapitulatif prix */}
            <div className="card">
              <h3 className="font-black text-gray-900 mb-4">
                {isRoundTrip ? (step === 1 ? 'Trajet aller' : 'Trajet retour') : 'Récapitulatif'}
              </h3>
              <div className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-gray-500">Trajet</span>
                  <span className="font-semibold">
                    {activeTrip ? `${activeTrip.villeDepart} → ${activeTrip.villeArrivee}` : '—'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Départ</span>
                  <span className="font-semibold">
                    {activeDep
                      ? `${activeDep.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} · ${activeDep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
                      : '—'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Arrivée</span>
                  <span className="font-semibold">
                    {activeArr ? activeArr.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
                  </span>
                </div>

                <div className="flex justify-between items-start">
                  <span className="text-gray-500">Sièges</span>
                  <div className="text-right">
                    {activeSeats.length === 0 ? (
                      <span className="text-gray-300 font-medium">—</span>
                    ) : (
                      <div className="flex flex-wrap gap-1 justify-end">
                        {activeSeats.map((s) => (
                          <span key={s}
                            className="bg-brand-green text-brand-dark text-xs font-black
                                       px-2 py-0.5 rounded-full">
                            N°{s}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                </div>

                <div className="border-t border-gray-100 pt-3 space-y-1.5">
                  {multiPax ? (
                    <>
                      {adults > 0 && (
                        <div className="flex justify-between text-xs text-gray-500">
                          <span>{adults} adulte{adults > 1 ? 's' : ''} × {activeBasePrice} DH</span>
                          <span>{(adults * activeBasePrice).toFixed(2)} DH</span>
                        </div>
                      )}
                      {children > 0 && (
                        <div className="flex justify-between text-xs text-sky-600">
                          <span>{children} enfant{children > 1 ? 's' : ''} × {activeChildPrice} DH <span className="text-sky-400">(−25%)</span></span>
                          <span>{(children * activeChildPrice).toFixed(2)} DH</span>
                        </div>
                      )}
                      {babies > 0 && (
                        <div className="flex justify-between text-xs text-brand-green">
                          <span>{babies} bébé{babies > 1 ? 's' : ''} · gratuit</span>
                          <span>0.00 DH</span>
                        </div>
                      )}
                      <div className="flex justify-between items-center pt-2 border-t border-gray-100">
                        <span className="font-bold text-gray-700">
                          {isRoundTrip ? (step === 1 ? 'Aller' : 'Retour') : 'Total'}
                        </span>
                        <span className="text-2xl font-black text-brand-dark">{activeTotal.toFixed(2)} DH</span>
                      </div>
                    </>
                  ) : (
                    <div className="flex justify-between items-center">
                      <span className="font-bold text-gray-700">
                        {isRoundTrip ? (step === 1 ? 'Aller' : 'Retour') : 'Total'}
                      </span>
                      <span className="text-2xl font-black text-brand-dark">{activeBasePrice} DH</span>
                    </div>
                  )}

                  {/* Total combiné en étape 2 */}
                  {isRoundTrip && step === 2 && (
                    <div className="flex justify-between items-center pt-2 border-t-2 border-brand-green">
                      <span className="font-black text-gray-900">Total aller-retour</span>
                      <span className="text-2xl font-black text-brand-green">{(total + retTotal).toFixed(2)} DH</span>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* ── Panneau d'assignation ── */}
            {needsAssignment && activeSeats.length > 0 && (
              <div className="card">
                <h3 className="font-black text-gray-900 mb-1 text-sm">Qui voyage sur quel siège ?</h3>

                <div className="flex gap-3 mb-3">
                  <span className={`text-xs font-semibold px-2 py-0.5 rounded-full
                    ${activeAssignedA === adults ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    👤 {activeAssignedA}/{adults} adulte{adults > 1 ? 's' : ''}
                  </span>
                  {children > 0 && (
                    <span className={`text-xs font-semibold px-2 py-0.5 rounded-full
                      ${activeAssignedC === children ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-500'}`}>
                      🧒 {activeAssignedC}/{children} enfant{children > 1 ? 's' : ''}
                    </span>
                  )}
                </div>

                <div className="space-y-2">
                  {activeSeats.map((s) => (
                    <div key={s} className="flex items-center justify-between gap-2">
                      <span className="text-sm font-semibold text-gray-700 shrink-0">Siège N°{s}</span>
                      <div className="flex gap-1">
                        <button
                          onClick={() => activeAssign(s, 'ADULT')}
                          className={`px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all
                            ${activeAssignments[s] === 'ADULT'
                              ? 'bg-brand-green border-green-400 text-brand-dark'
                              : 'bg-white border-gray-200 text-gray-500 hover:border-brand-green'}`}>
                          👤 Adulte
                        </button>
                        <button
                          onClick={() => activeAssign(s, 'CHILD')}
                          className={`px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all
                            ${activeAssignments[s] === 'CHILD'
                              ? 'bg-sky-400 border-sky-500 text-white'
                              : 'bg-white border-gray-200 text-gray-500 hover:border-sky-400'}`}>
                          🧒 Enfant −25%
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                {activeSeats.length === requiredSeats && !activeAssignDone && (
                  <p className="mt-2 text-xs text-amber-600 font-medium">
                    ⚠ Il manque {adults - activeAssignedA > 0
                      ? `${adults - activeAssignedA} adulte${adults - activeAssignedA > 1 ? 's' : ''}`
                      : `${children - activeAssignedC} enfant${children - activeAssignedC > 1 ? 's' : ''}`}.
                  </p>
                )}
              </div>
            )}

            {/* Bouton principal */}
            <button onClick={handleBook}
              disabled={btnDisabled}
              className="w-full bg-brand-green text-brand-dark font-black py-4 rounded-xl
                         hover:bg-green-400 transition disabled:opacity-40
                         disabled:cursor-not-allowed text-base">
              {btnLabel}
            </button>

            <div className="bg-blue-50 rounded-xl p-3 text-xs text-blue-700 border border-blue-100">
               Vos sièges seront confirmés après le paiement
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default function BookingPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-5xl animate-bounce">🚌</div>
      </div>
    }>
      <BookingContent />
    </Suspense>
  )
}
