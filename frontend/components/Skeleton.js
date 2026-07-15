// Composant de base pour les skeletons
const Pulse = ({ className }) => (
  <div className={`animate-pulse bg-gray-200 rounded-lg ${className}`} />
)

// carte de trajet (page /trips)
export function TripCardSkeleton() {
  return (
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 animate-pulse">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <Pulse className="w-10 h-10 rounded-full" />
          <div className="space-y-2">
            <Pulse className="h-4 w-36" />
            <Pulse className="h-3 w-24" />
          </div>
        </div>
        <Pulse className="h-7 w-20 rounded-full" />
      </div>

      <div className="flex items-center gap-4 mb-4">
        <div className="flex-1 space-y-1">
          <Pulse className="h-5 w-28" />
          <Pulse className="h-3 w-16" />
        </div>
        <div className="flex flex-col items-center gap-1 px-4">
          <Pulse className="h-3 w-12" />
          <Pulse className="h-px w-16" />
          <Pulse className="h-3 w-8" />
        </div>
        <div className="flex-1 space-y-1 items-end flex flex-col">
          <Pulse className="h-5 w-28" />
          <Pulse className="h-3 w-16" />
        </div>
      </div>

      <div className="flex items-center justify-between pt-3 border-t border-gray-100">
        <div className="space-y-1">
          <Pulse className="h-6 w-20" />
          <Pulse className="h-3 w-24" />
        </div>
        <Pulse className="h-10 w-28 rounded-xl" />
      </div>
    </div>
  )
}

// Skeleton liste trajets (3 cartes)
export function TripsListSkeleton() {
  return (
    <div className="space-y-4">
      {[1, 2, 3].map(i => <TripCardSkeleton key={i} />)}
    </div>
  )
}

// Skeleton carte réservation (page /dashboard)
export function BookingCardSkeleton() {
  return (
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 animate-pulse">
      <div className="flex items-start justify-between mb-4">
        <div className="space-y-2">
          <Pulse className="h-5 w-48" />
          <Pulse className="h-3 w-32" />
        </div>
        <Pulse className="h-6 w-20 rounded-full" />
      </div>

      <div className="grid grid-cols-3 gap-4 mb-4 py-3 border-y border-gray-100">
        {[1, 2, 3].map(i => (
          <div key={i} className="space-y-1">
            <Pulse className="h-3 w-14" />
            <Pulse className="h-4 w-20" />
          </div>
        ))}
      </div>

      <div className="flex gap-2 justify-end">
        <Pulse className="h-9 w-28 rounded-xl" />
        <Pulse className="h-9 w-28 rounded-xl" />
      </div>
    </div>
  )
}

// Skeleton liste réservations (3 cartes)
export function BookingsListSkeleton() {
  return (
    <div className="space-y-4">
      {[1, 2, 3].map(i => <BookingCardSkeleton key={i} />)}
    </div>
  )
}

// Skeleton profil utilisateur
export function ProfileSkeleton() {
  return (
    <div className="max-w-2xl mx-auto px-4 py-10 animate-pulse">
      {/* Avatar */}
      <div className="flex items-center gap-4 mb-8">
        <Pulse className="w-20 h-20 rounded-full" />
        <div className="space-y-2">
          <Pulse className="h-6 w-40" />
          <Pulse className="h-4 w-28" />
        </div>
      </div>

      {/* Champs formulaire */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-6">
        {[1, 2, 3, 4].map(i => (
          <div key={i} className="space-y-2">
            <Pulse className="h-3 w-24" />
            <Pulse className="h-11 w-full rounded-xl" />
          </div>
        ))}
        <Pulse className="h-11 w-full rounded-xl mt-4" />
      </div>
    </div>
  )
}

// Skeleton page paiement
export function PaymentSkeleton() {
  return (
    <div className="max-w-lg mx-auto px-4 py-10 animate-pulse space-y-6">
      {/* Résumé réservation */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
        <Pulse className="h-5 w-40 mb-4" />
        <div className="flex justify-between">
          <Pulse className="h-4 w-32" />
          <Pulse className="h-4 w-20" />
        </div>
        <div className="flex justify-between">
          <Pulse className="h-4 w-24" />
          <Pulse className="h-4 w-16" />
        </div>
        <div className="border-t border-gray-100 pt-3 flex justify-between">
          <Pulse className="h-6 w-16" />
          <Pulse className="h-6 w-24" />
        </div>
      </div>

      {/* Méthodes paiement */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
        <Pulse className="h-5 w-48 mb-4" />
        {[1, 2, 3].map(i => (
          <Pulse key={i} className="h-14 w-full rounded-xl" />
        ))}
      </div>

      <Pulse className="h-12 w-full rounded-xl" />
    </div>
  )
}

// Skeleton générique page entière (stats dashboard admin)
export function PageSkeleton({ lines = 5 }) {
  return (
    <div className="space-y-4 animate-pulse">
      <Pulse className="h-8 w-56 mb-6" />
      {Array.from({ length: lines }).map((_, i) => (
        <Pulse key={i} className={`h-4 ${i % 3 === 0 ? 'w-3/4' : i % 3 === 1 ? 'w-full' : 'w-1/2'}`} />
      ))}
    </div>
  )
}
