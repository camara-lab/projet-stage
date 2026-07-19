'use client'
import { useState, useEffect, useRef, Suspense } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getTrips, getCities } from '@/lib/api'
import toast from 'react-hot-toast'
import { TripsListSkeleton } from '@/components/Skeleton'
import PassengerSelector, { calcTotalPrice } from '@/components/PassengerSelector'
import { normalizePassengers } from '@/lib/passengers'
import { getBasePrice } from '@/lib/trips'
import { saveRecentSearch } from '@/lib/recentSearches'

const POPULAR_ROUTES = [
  { from: 'Casablanca', to: 'Marrakech' },
  { from: 'Rabat',      to: 'Fès' },
  { from: 'Tanger',     to: 'Casablanca' },
  { from: 'Agadir',     to: 'Marrakech' },
]

function offsetDate(dateStr, days) {
  if (!dateStr) return new Date().toISOString().split('T')[0]
  const d = new Date(dateStr + 'T00:00:00')
  d.setDate(d.getDate() + days)
  return d.toISOString().split('T')[0]
}

function fmtDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', {
    weekday: 'long', day: 'numeric', month: 'long',
  })
}

function NoTripsFound({ date, from, to, router }) {
  const yesterday = offsetDate(date, -1)
  const tomorrow  = offsetDate(date, +1)
  const today     = new Date().toISOString().split('T')[0]
  const canGoBack = yesterday >= today

  const go = (d, f = from, t = to) =>
    router.push(`/trips?from=${encodeURIComponent(f)}&to=${encodeURIComponent(t)}&date=${d}`)

  return (
    <div className="py-10">
      <div className="text-center mb-8">
        <div className="text-7xl mb-4">🚌</div>
        <h2 className="text-xl font-bold text-gray-800 mb-2">Aucun trajet trouvé</h2>
        <p className="text-gray-400 text-sm mb-6">
          {from && to
            ? `Pas de trajet ${from} → ${to} pour le ${fmtDate(date)}`
            : 'Essayez une autre date ou destination'}
        </p>

        {date && (
          <div className="flex flex-wrap items-center justify-center gap-3 mb-8">
            {canGoBack && (
              <button onClick={() => go(yesterday)}
                className="flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-gray-200
                           text-sm font-semibold text-gray-700 hover:border-brand-green
                           hover:text-brand-green transition">
                ← Veille · {fmtDate(yesterday)}
              </button>
            )}
            <button onClick={() => go(tomorrow)}
              className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-dark text-white
                         text-sm font-bold hover:bg-brand-blue transition">
              Lendemain · {fmtDate(tomorrow)} →
            </button>
          </div>
        )}
      </div>

      <div className="border-t border-gray-100 pt-8">
        <h3 className="text-center text-base font-bold text-gray-700 mb-5">
          Destinations populaires
        </h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {POPULAR_ROUTES.map((r) => (
            <button key={r.from + r.to}
              onClick={() => go(date || today, r.from, r.to)}
              className="card !p-3 text-left hover:border-brand-green border-2 border-transparent
                         transition group">
              <div className="text-xs text-gray-500 font-medium mb-1">{r.from}</div>
              <div className="flex items-center gap-1 my-1">
                <div className="w-1.5 h-1.5 rounded-full bg-brand-green"></div>
                <div className="flex-1 h-px bg-gray-200"></div>
                <div className="w-1.5 h-1.5 rounded-full bg-red-400"></div>
              </div>
              <div className="text-xs text-gray-500 font-medium">{r.to}</div>
              <div className="mt-2 text-xs text-sky-600 font-semibold group-hover:text-brand-green transition">
                Voir →
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

function TripCard({ trip, onBook, passengers, isSelected, buttonLabel }) {
  const dep   = new Date(trip.heureDepart)
  const arr   = new Date(trip.heureArrivee)
  const dur   = Math.round((arr - dep) / 60000)
  const h     = Math.floor(dur / 60)
  const m     = dur % 60
  const dispo = trip.placesDisponibles !== undefined
    ? trip.placesDisponibles
    : (trip.capaciteBus || 0)

  const basePrice  = getBasePrice(trip)
  const total      = calcTotalPrice(basePrice, passengers)
  const multiPax   = (passengers.adults + passengers.children + passengers.babies) > 1
  const childPrice = +(basePrice * 0.75).toFixed(2)

  const busType = trip.capaciteBus >= 50 ? 'Grand Confort'
                : trip.capaciteBus >= 40 ? 'Confort'
                : 'Standard'

  return (
    <div className={`card hover:shadow-lg transition-all border-2
      ${isSelected
        ? 'border-brand-green bg-green-50'
        : 'border-transparent hover:border-brand-green'}`}>

      {/* En-tête : compagnie, type de bus, équipements */}
      <div className="flex flex-wrap items-center justify-between gap-2 pb-3 mb-4 border-b border-gray-100">
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-1.5">
            <span className="w-7 h-7 rounded-lg bg-brand-dark text-white flex items-center justify-center text-sm">🚌</span>
            <span className="font-black text-sm text-brand-dark">Bus<span className="text-brand-green">Go</span></span>
          </div>
          <span className="text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
            {busType} · {trip.capaciteBus} places
          </span>
          <span className="text-xs font-semibold text-brand-green bg-green-50 border border-green-100 px-2.5 py-1 rounded-full">
            Trajet direct
          </span>
        </div>
        <div className="flex items-center gap-3 text-xs text-gray-400 font-medium">
          <span title="Wi-Fi à bord">📶 Wi-Fi</span>
          <span title="Climatisation">❄️ Clim</span>
          <span title="Prises USB">🔌 USB</span>
          <span title="1 bagage en soute inclus">🧳 1 bagage</span>
        </div>
      </div>

      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">

        <div className="flex items-center gap-6 flex-1">
          <div className="text-center">
            <div className="text-2xl font-black text-gray-900">
              {dep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
            </div>
            <div className="text-sm font-semibold text-gray-600">{trip.villeDepart || '—'}</div>
          </div>
          <div className="flex-1 flex flex-col items-center gap-1 min-w-[80px]">
            <div className="text-xs text-gray-400 font-medium">{h}h{m > 0 ? m + 'min' : ''}</div>
            <div className="w-full flex items-center gap-1">
              <div className="w-2 h-2 rounded-full bg-brand-green flex-shrink-0"></div>
              <div className="flex-1 h-0.5 bg-gray-200"></div>
              <div className="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></div>
            </div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-black text-gray-900">
              {arr.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
            </div>
            <div className="text-sm font-semibold text-gray-600">{trip.villeArrivee || '—'}</div>
          </div>
        </div>

        <div className="flex flex-wrap gap-2 md:w-48">
          {dispo > 5 && <span className="badge-green">{dispo} places disponibles</span>}
          {dispo <= 5 && dispo > 0 && (
            <span className="badge-yellow">Plus que {dispo} places</span>
          )}
          {dispo === 0 && <span className="badge-red">Complet</span>}
        </div>

        <div className="flex flex-col items-end gap-2 min-w-[160px]">
          {multiPax ? (
            <div className="text-right">
              <div className="text-xs text-gray-400 space-y-0.5 mb-1">
                {passengers.adults > 0 && (
                  <div>{passengers.adults} adulte{passengers.adults > 1 ? 's' : ''} × {basePrice} DH</div>
                )}
                {passengers.children > 0 && (
                  <div className="text-sky-500">
                    {passengers.children} enfant{passengers.children > 1 ? 's' : ''} × {childPrice} DH
                    <span className="ml-1 text-sky-400">(−25 %)</span>
                  </div>
                )}
                {passengers.babies > 0 && (
                  <div className="text-brand-green">
                    {passengers.babies} bébé{passengers.babies > 1 ? 's' : ''} · gratuit
                  </div>
                )}
              </div>
              <div>
                <span className="text-2xl font-black text-brand-dark">{total.toFixed(2)}</span>
                <span className="text-gray-400 text-sm font-medium"> DH</span>
              </div>
              <div className="text-xs text-gray-400">total voyage</div>
            </div>
          ) : (
            <div>
              <span className="text-3xl font-black text-brand-dark">{basePrice}</span>
              <span className="text-gray-400 text-sm font-medium"> DH</span>
            </div>
          )}

          <button
            onClick={() => onBook(trip)}
            disabled={dispo === 0}
            className={`w-full font-bold py-2.5 px-6 rounded-xl transition
                       disabled:opacity-40 disabled:cursor-not-allowed text-sm
              ${isSelected
                ? 'bg-brand-dark text-white cursor-default'
                : 'bg-brand-green text-brand-dark hover:bg-green-400'}`}
          >
            {dispo === 0 ? 'Complet' : isSelected ? '✓ Sélectionné' : (buttonLabel || 'Réserver →')}
          </button>
        </div>
      </div>
    </div>
  )
}

function TripsContent() {
  const searchParams = useSearchParams()
  const router       = useRouter()

  const from      = searchParams.get('from')       || ''
  const to        = searchParams.get('to')         || ''
  const date      = searchParams.get('date')       || ''
  const initType  = searchParams.get('type') === 'round-trip' ? 'round-trip' : 'one-way'
  const initRetDt = searchParams.get('returnDate') || ''
  const today     = new Date().toISOString().split('T')[0]

  const initPassengers = normalizePassengers(searchParams)

  const [trips,            setTrips]            = useState([])
  const [loading,          setLoading]          = useState(true)
  const [search,           setSearch]           = useState({ from, to, date: date || today, type: initType, returnDate: initRetDt })
  const [pagination,       setPagination]       = useState({ page: 1, totalPages: 1, total: 0 })
  const [cities,           setCities]           = useState([])
  const [passengers,       setPassengers]       = useState(initPassengers)
  const [tripSort,         setTripSort]         = useState('dep')
  const [onlyAvail,        setOnlyAvail]        = useState(false)
  const [selectedOutbound, setSelectedOutbound] = useState(null)
  const [returnTrips,      setReturnTrips]      = useState([])
  const [returnLoading,    setReturnLoading]    = useState(false)
  const returnRef = useRef(null)

  useEffect(() => {
    getCities().then(({ data }) => setCities(data)).catch(() => {})
  }, [])

  useEffect(() => {
    fetchTrips(1)
    setSelectedOutbound(null)
    setReturnTrips([])
  }, [from, to, date])

  const fetchTrips = async (page = 1) => {
    setLoading(true)
    try {
      const params = { page }
      if (from) params.departureCity = from
      if (to)   params.arrivalCity   = to
      if (date) params.date          = date
      const { data } = await getTrips(params)
      setTrips(data.trajets || [])
      setPagination(data.pagination || { page: 1, totalPages: 1, total: data.trajets?.length || 0 })
    } catch {
      toast.error('Impossible de charger les trajets')
      setTrips([])
    } finally {
      setLoading(false)
    }
  }

  const fetchReturnTrips = async () => {
    setReturnLoading(true)
    try {
      const params = {}
      if (to)                params.departureCity = to
      if (from)              params.arrivalCity   = from
      if (search.returnDate) params.date          = search.returnDate
      const { data } = await getTrips(params)
      setReturnTrips(data.trajets || [])
    } catch {
      toast.error('Impossible de charger les trajets retour')
      setReturnTrips([])
    } finally {
      setReturnLoading(false)
    }
  }

  const handleSearch = (e) => {
    e.preventDefault()
    if (search.type === 'round-trip' && !search.returnDate) {
      toast.error('Sélectionnez une date de retour')
      return
    }
    saveRecentSearch({ from: search.from, to: search.to, date: search.date })
    const { adults, children, babies } = passengers
    let url = `/trips?from=${encodeURIComponent(search.from)}&to=${encodeURIComponent(search.to)}&date=${search.date}`
      + `&adults=${adults}&children=${children}&babies=${babies}`
    if (search.type === 'round-trip' && search.returnDate) {
      url += `&type=round-trip&returnDate=${search.returnDate}`
    }
    router.push(url)
  }

  const SORTS = [
    { key: 'dep',   label: 'Heure départ' },
    { key: 'price', label: 'Prix croissant' },
    { key: 'dur',   label: 'Durée' },
    { key: 'avail', label: 'Disponibilité' },
  ]

  const tripSorters = {
    dep:   (a, b) => new Date(a.heureDepart) - new Date(b.heureDepart),
    price: (a, b) => (parseFloat(a.prixBase) || 0) - (parseFloat(b.prixBase) || 0),
    dur:   (a, b) => (new Date(a.heureArrivee) - new Date(a.heureDepart)) - (new Date(b.heureArrivee) - new Date(b.heureDepart)),
    avail: (a, b) => {
      const da = a.placesDisponibles !== undefined ? a.placesDisponibles : (a.capaciteBus || 0)
      const db = b.placesDisponibles !== undefined ? b.placesDisponibles : (b.capaciteBus || 0)
      return db - da
    },
  }

  let sortedTrips = trips.slice()
  if (onlyAvail) {
    sortedTrips = sortedTrips.filter((t) => {
      const d = t.placesDisponibles !== undefined ? t.placesDisponibles : (t.capaciteBus || 0)
      return d > 0
    })
  }
  sortedTrips.sort(tripSorters[tripSort] || tripSorters.dep)

  const goToPage = (page) => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
    fetchTrips(page)
  }

  const handleBook = (trip) => {
    if (typeof window !== 'undefined' && !localStorage.getItem('token')) {
      toast.error('Connectez-vous pour réserver')
      router.push('/auth/login')
      return
    }
    if (search.type === 'round-trip') {
      setSelectedOutbound(trip)
      fetchReturnTrips()
      setTimeout(() => returnRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150)
      return
    }
    const { adults, children, babies } = passengers
    router.push(`/booking/${trip.id}?adults=${adults}&children=${children}&babies=${babies}`)
  }

  const handleBookReturn = (returnTrip) => {
    const { adults, children, babies } = passengers
    router.push(
      `/booking/${selectedOutbound.id}?adults=${adults}&children=${children}&babies=${babies}` +
      `&returnTripId=${returnTrip.id}&returnDate=${search.returnDate}`
    )
  }

  const isRoundTrip = search.type === 'round-trip'

  return (
    <div className="min-h-screen">
      <Navbar />

      <div className="bg-brand-dark py-6">
        <div className="max-w-5xl mx-auto px-4">

          {/* Type toggle */}
          <div className="flex gap-2 mb-3">
            <button
              type="button"
              onClick={() => setSearch({ ...search, type: 'one-way' })}
              className={`px-4 py-1.5 rounded-full text-sm font-bold transition
                ${!isRoundTrip
                  ? 'bg-white text-brand-dark'
                  : 'bg-white/10 text-white hover:bg-white/20'}`}
            >
              Aller simple
            </button>
            <button
              type="button"
              onClick={() => setSearch({ ...search, type: 'round-trip' })}
              className={`flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-bold transition
                ${isRoundTrip
                  ? 'bg-white text-brand-dark'
                  : 'bg-white/10 text-white hover:bg-white/20'}`}
            >
              ↔ Aller-retour
            </button>
          </div>

          <form onSubmit={handleSearch}
            className="flex flex-col md:flex-row gap-3 bg-white rounded-2xl p-4 shadow-xl">
            <div className="relative flex-1">
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xl pointer-events-none">🟢</span>
              <input
                type="text"
                list="trips-cities-from"
                placeholder="Départ (ex: Casablanca)"
                value={search.from}
                onChange={(e) => setSearch({ ...search, from: e.target.value })}
                className="input-field-lg pl-12"
                autoComplete="off"
              />
            </div>
            <datalist id="trips-cities-from">
              {cities.map((c) => <option key={c.id} value={c.name} />)}
            </datalist>
            <div className="relative flex-1">
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xl pointer-events-none">🔴</span>
              <input
                type="text"
                list="trips-cities-to"
                placeholder="Arrivée (ex: Marrakech)"
                value={search.to}
                onChange={(e) => setSearch({ ...search, to: e.target.value })}
                className="input-field-lg pl-12"
                autoComplete="off"
              />
            </div>
            <datalist id="trips-cities-to">
              {cities.map((c) => <option key={c.id} value={c.name} />)}
            </datalist>
            <div className="flex gap-3">
              <input type="date" value={search.date} min={today}
                onChange={(e) => setSearch({ ...search, date: e.target.value })}
                className="input-field-lg md:w-44"
              />
              {isRoundTrip && (
                <input type="date" value={search.returnDate}
                  min={search.date || today}
                  onChange={(e) => setSearch({ ...search, returnDate: e.target.value })}
                  className="input-field-lg md:w-44"
                  placeholder="Date retour"
                />
              )}
            </div>
            <PassengerSelector
              value={passengers}
              onChange={setPassengers}
              className="md:w-56"
              fieldClassName="input-field-lg"
            />
            <button type="submit"
              className="h-[60px] bg-brand-dark text-white font-bold text-base px-8 rounded-xl
                         hover:bg-brand-blue transition whitespace-nowrap flex items-center justify-center gap-2">
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
              </svg>
              Rechercher
            </button>
          </form>
        </div>
      </div>

      <div className="max-w-5xl mx-auto px-4 py-10">

        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-xl font-bold text-gray-900">
              {loading ? 'Recherche en cours...' : (
                from && to
                  ? `${from} → ${to}${date ? ` · ${new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}` : ''}`
                  : 'Tous les trajets disponibles'
              )}
            </h1>
            {isRoundTrip && search.returnDate && from && to && (
              <p className="text-sm text-gray-500 mt-0.5">
                Retour : {to} → {from} · {new Date(search.returnDate + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}
              </p>
            )}
          </div>
          {!loading && (
            <span className="text-sm text-gray-500 font-medium">
              {trips.length} trajet{trips.length !== 1 ? 's' : ''} trouvé{trips.length !== 1 ? 's' : ''}
            </span>
          )}
        </div>

        {/* Stepper aller-retour */}
        {isRoundTrip && (
          <div className="flex items-center gap-3 mb-6 bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div className={`flex items-center gap-2 ${selectedOutbound ? 'text-brand-green' : 'text-brand-dark'}`}>
              <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-black flex-shrink-0
                ${selectedOutbound ? 'bg-brand-green text-white' : 'bg-brand-dark text-white'}`}>
                {selectedOutbound ? '✓' : '1'}
              </div>
              <div>
                <span className="text-sm font-bold">Aller</span>
                {selectedOutbound && (
                  <span className="ml-2 text-xs text-gray-500">
                    {new Date(selectedOutbound.heureDepart).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                  </span>
                )}
              </div>
            </div>
            <div className="flex-1 h-0.5 bg-gray-200 relative overflow-hidden rounded-full">
              <div className={`absolute inset-y-0 left-0 bg-brand-green transition-all duration-500 ${selectedOutbound ? 'right-0' : 'right-full'}`} />
            </div>
            <div className={`flex items-center gap-2 ${selectedOutbound ? 'text-brand-dark' : 'text-gray-400'}`}>
              <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-black flex-shrink-0
                ${selectedOutbound ? 'bg-brand-dark text-white' : 'bg-gray-200 text-gray-400'}`}>
                2
              </div>
              <span className="text-sm font-bold">Retour</span>
            </div>
          </div>
        )}

        {!loading && trips.length > 0 && (
          <div className="flex flex-wrap items-center gap-2 mb-5">
            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider mr-1">
              {isRoundTrip ? 'Étape 1 — Trier par' : 'Trier par'}
            </span>
            {SORTS.map(({ key, label }) => (
              <button
                key={key}
                onClick={() => setTripSort(key)}
                className={`px-3.5 py-1.5 rounded-full text-xs font-bold border-2 transition
                  ${tripSort === key
                    ? 'bg-brand-dark text-white border-brand-dark'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-brand-green hover:text-brand-green'
                  }`}
              >
                {label}
              </button>
            ))}
            <div className="ml-auto">
              <button
                onClick={() => setOnlyAvail((v) => !v)}
                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold
                            border-2 transition
                  ${onlyAvail
                    ? 'bg-brand-green text-brand-dark border-brand-green'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-brand-green hover:text-brand-green'
                  }`}
              >
                <span className={`w-3 h-3 rounded-full border-2 flex-shrink-0 transition
                  ${onlyAvail ? 'bg-brand-dark border-brand-dark' : 'border-gray-400'}`} />
                Disponibles seulement
              </button>
            </div>
          </div>
        )}

        {loading ? (
          <TripsListSkeleton />
        ) : trips.length === 0 ? (
          <NoTripsFound date={search.date} from={search.from} to={search.to} router={router} />
        ) : sortedTrips.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-5xl mb-3"></div>
            <p className="text-gray-500 font-medium">Aucun trajet disponible avec ces filtres.</p>
            <button onClick={() => setOnlyAvail(false)}
              className="mt-4 text-sm text-brand-green font-bold hover:underline">
              Afficher tous les trajets
            </button>
          </div>
        ) : (
          <>
            <div className="space-y-4">
              {sortedTrips.map((trip) => (
                <TripCard
                  key={trip.id}
                  trip={trip}
                  onBook={handleBook}
                  passengers={passengers}
                  isSelected={selectedOutbound?.id === trip.id}
                  buttonLabel={isRoundTrip ? 'Sélectionner aller →' : undefined}
                />
              ))}
            </div>

            {pagination.totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 mt-8">
                <button
                  onClick={() => goToPage(pagination.page - 1)}
                  disabled={pagination.page <= 1}
                  className="px-4 py-2 rounded-xl border-2 border-gray-200 text-sm font-semibold
                             text-gray-600 hover:border-gray-300 transition disabled:opacity-30
                             disabled:cursor-not-allowed">
                  ← Précédent
                </button>
                {Array.from({ length: pagination.totalPages }, (_, i) => i + 1).map((p) => (
                  <button key={p} onClick={() => goToPage(p)}
                    className={`w-9 h-9 rounded-xl text-sm font-bold transition border-2
                      ${p === pagination.page
                        ? 'bg-brand-dark text-white border-brand-dark'
                        : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}>
                    {p}
                  </button>
                ))}
                <button
                  onClick={() => goToPage(pagination.page + 1)}
                  disabled={pagination.page >= pagination.totalPages}
                  className="px-4 py-2 rounded-xl border-2 border-gray-200 text-sm font-semibold
                             text-gray-600 hover:border-gray-300 transition disabled:opacity-30
                             disabled:cursor-not-allowed">
                  Suivant →
                </button>
              </div>
            )}

            <p className="text-center text-xs text-gray-400 mt-3">
              {pagination.total} trajet{pagination.total !== 1 ? 's' : ''} · page {pagination.page}/{pagination.totalPages}
            </p>
          </>
        )}

        {/* ── Section retour (aller-retour uniquement) ── */}
        {isRoundTrip && selectedOutbound && (
          <div ref={returnRef} className="mt-12 pt-8 border-t-2 border-dashed border-gray-200">

            {/* Résumé aller sélectionné */}
            <div className="flex items-center gap-4 mb-8 bg-green-50 border-2 border-brand-green rounded-2xl p-4">
              <div className="w-10 h-10 bg-brand-green rounded-full flex items-center justify-center
                              text-white font-black text-base flex-shrink-0">
                ✓
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-xs font-bold text-brand-green uppercase tracking-wider mb-0.5">
                  Trajet aller sélectionné
                </div>
                <div className="font-black text-gray-900">
                  {from} → {to} · {new Date(selectedOutbound.heureDepart).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                </div>
                <div className="text-sm text-gray-500">
                  {new Date(selectedOutbound.heureDepart).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long' })}
                  {' · '}{getBasePrice(selectedOutbound)} DH / pers.
                </div>
              </div>
              <button
                onClick={() => { setSelectedOutbound(null); setReturnTrips([]) }}
                className="text-sm text-gray-400 hover:text-gray-700 font-semibold flex-shrink-0 transition">
                Changer
              </button>
            </div>

            <h2 className="text-xl font-bold text-gray-900 mb-1">Étape 2 — Choisissez votre trajet retour</h2>
            <p className="text-gray-500 text-sm mb-6">
              {to} → {from}
              {search.returnDate
                ? ` · ${new Date(search.returnDate + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}`
                : ''}
            </p>

            {returnLoading ? (
              <TripsListSkeleton />
            ) : returnTrips.length === 0 ? (
              <div className="text-center py-10 bg-white rounded-2xl border border-gray-100">
                <div className="text-5xl mb-3">🚌</div>
                <p className="text-gray-600 font-semibold mb-1">Aucun trajet retour disponible</p>
                <p className="text-sm text-gray-400">
                  {!search.returnDate
                    ? 'Indiquez une date de retour dans la recherche ci-dessus'
                    : `Pas de trajet ${to} → ${from} pour cette date`}
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {returnTrips.map((rt) => (
                  <TripCard
                    key={rt.id}
                    trip={rt}
                    onBook={handleBookReturn}
                    passengers={passengers}
                    buttonLabel="Sélectionner retour →"
                  />
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      <Footer />
    </div>
  )
}

export default function TripsPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center">Chargement...</div>}>
      <TripsContent />
    </Suspense>
  )
}
