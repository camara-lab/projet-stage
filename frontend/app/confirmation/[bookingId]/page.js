'use client'
import { useState, useEffect } from 'react'
import { useRouter, useParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getBooking } from '@/lib/api'

export default function ConfirmationPage() {
  const { bookingId } = useParams()
  const router        = useRouter()
  const [booking, setBooking] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!localStorage.getItem('token')) { router.push('/auth/login'); return }
    getBooking(bookingId)
      .then(({ data }) => setBooking(data))
      .catch(() => router.push('/dashboard'))
      .finally(() => setLoading(false))
  }, [bookingId])

  if (loading) return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="text-5xl mb-4 animate-bounce">🎉</div>
        <p className="text-gray-500">Chargement...</p>
      </div>
    </div>
  )

  const dep = booking?.trip?.departureTime ? new Date(booking.trip.departureTime) : null
  const arr = booking?.trip?.arrivalTime   ? new Date(booking.trip.arrivalTime)   : null

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <div className="max-w-2xl mx-auto px-4 py-16">

        {/* Succès */}
        <div className="text-center mb-10">
          <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <span className="text-4xl">✅</span>
          </div>
          <h1 className="text-3xl font-black text-gray-900 mb-2">Paiement confirmé !</h1>
          <p className="text-gray-500">Votre billet a été émis avec succès. Bon voyage !</p>
        </div>

        {/* Billet */}
        <div className="card mb-6 overflow-hidden">
          {/* En-tête billet */}
          <div className="bg-brand-dark text-white p-6 -mx-6 -mt-6 mb-6">
            <div className="flex items-center justify-between">
              <span className="text-2xl">🚌 BusGo</span>
              <span className="text-sm text-gray-400">Billet électronique</span>
            </div>
          </div>

          {/* Trajet */}
          <div className="flex items-center justify-between mb-6">
            <div className="text-center">
              <div className="text-3xl font-black text-gray-900">
                {dep ? dep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
              </div>
              <div className="font-bold text-gray-700 text-sm mt-1">
                {booking?.trip?.route?.departureCity?.name}
              </div>
            </div>
            <div className="flex-1 flex flex-col items-center gap-1 mx-4">
              <div className="w-full flex items-center gap-1">
                <div className="w-2 h-2 rounded-full bg-brand-green flex-shrink-0"></div>
                <div className="flex-1 h-0.5 bg-gray-300 border-dashed"></div>
                <div className="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></div>
              </div>
              <div className="text-xs text-gray-400">Direct</div>
            </div>
            <div className="text-center">
              <div className="text-3xl font-black text-gray-900">
                {arr ? arr.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
              </div>
              <div className="font-bold text-gray-700 text-sm mt-1">
                {booking?.trip?.route?.arrivalCity?.name}
              </div>
            </div>
          </div>

          {/* Détails */}
          <div className="border-t border-dashed border-gray-200 pt-5 grid grid-cols-2 gap-4">
            <div>
              <div className="text-xs text-gray-400 mb-1">Date</div>
              <div className="font-semibold text-sm">
                {dep ? dep.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '—'}
              </div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Numéro de siège</div>
              <div className="font-black text-2xl text-brand-dark">#{booking?.seatNumber}</div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Réservation #</div>
              <div className="font-semibold text-sm font-mono">{booking?.id}</div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Montant payé</div>
              <div className="font-black text-lg text-brand-dark">
                {booking?.payment?.amount || booking?.trip?.route?.basePrice} DH
              </div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Bus</div>
              <div className="font-semibold text-sm">{booking?.trip?.bus?.plateNumber || '—'}</div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Statut</div>
              <span className="badge-green">Payé</span>
            </div>
          </div>

          {/* Transaction */}
          {booking?.payment?.transactionId && (
            <div className="mt-4 bg-gray-50 rounded-xl p-3 text-xs text-gray-400">
              Transaction : <span className="font-mono">{booking.payment.transactionId}</span>
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3">
          <button
            onClick={() => router.push('/dashboard')}
            className="flex-1 bg-brand-dark text-white font-bold py-4 rounded-xl hover:bg-brand-blue transition text-center">
            Mes réservations
          </button>
          <button
            onClick={() => router.push('/trips')}
            className="flex-1 bg-brand-green text-brand-dark font-bold py-4 rounded-xl hover:bg-green-400 transition text-center">
            Rechercher un voyage
          </button>
          <button
            onClick={() => router.push('/')}
            className="flex-1 border-2 border-gray-200 text-gray-700 font-semibold py-4 rounded-xl hover:border-gray-300 transition text-center">
            Retour à l'accueil
          </button>
        </div>

        <p className="text-center text-xs text-gray-400 mt-6">
           Paiement sécurisé · Billet disponible dans "Mes réservations"
        </p>
      </div>

      <Footer />
    </div>
  )
}
