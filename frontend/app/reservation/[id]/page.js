'use client'
import { useState, useEffect } from 'react'
import { useRouter, useParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getBooking, cancelBooking, downloadTicket } from '@/lib/api'
import toast from 'react-hot-toast'

const STATUS = {
  PAID:      { label: 'Payée',      cls: 'badge-green'  },
  PENDING:   { label: 'En attente', cls: 'badge-yellow' },
  CANCELLED: { label: 'Annulée',    cls: 'badge-red'    },
  REFUNDED:  { label: 'Remboursée', cls: 'badge-purple' },
}

export default function ReservationDetailPage() {
  const { id }   = useParams()
  const router   = useRouter()
  const [booking,    setBooking]    = useState(null)
  const [loading,    setLoading]    = useState(true)
  const [cancelling, setCancelling] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('token')) { router.push('/auth/login'); return }
    getBooking(id)
      .then(({ data }) => setBooking(data))
      .catch(() => { toast.error('Réservation introuvable'); router.push('/dashboard') })
      .finally(() => setLoading(false))
  }, [id])

  const handleCancel = async () => {
    if (!confirm('Confirmer l\'annulation de cette réservation ?')) return
    setCancelling(true)
    try {
      await cancelBooking(id)
      toast.success('Réservation annulée')
      setBooking(b => ({ ...b, status: 'CANCELLED' }))
    } catch (err) {
      toast.error(err.response?.data?.message || 'Impossible d\'annuler')
    } finally {
      setCancelling(false)
    }
  }

  const handleDownload = async () => {
    try {
      const res = await downloadTicket(id)
      const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      const a   = document.createElement('a')
      a.href    = url
      a.download = `billet-${id}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.error('Impossible de télécharger le billet')
    }
  }

  if (loading) return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="text-5xl mb-4 animate-pulse"></div>
        <p className="text-gray-500">Chargement...</p>
      </div>
    </div>
  )

  const dep    = booking?.trip?.departureTime ? new Date(booking.trip.departureTime) : null
  const arr    = booking?.trip?.arrivalTime   ? new Date(booking.trip.arrivalTime)   : null
  const status = STATUS[booking?.status] || { label: booking?.status, cls: 'badge-yellow' }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <div className="max-w-3xl mx-auto px-4 py-10">
        <button onClick={() => router.push('/dashboard')}
          className="flex items-center gap-2 text-gray-500 hover:text-gray-800 mb-6 text-sm font-medium">
          ← Mes réservations
        </button>

        <div className="flex items-center justify-between mb-8">
          <h1 className="text-2xl font-black text-gray-900">Détail de la réservation</h1>
          <span className={status.cls}>{status.label}</span>
        </div>

        {/* Trajet */}
        <div className="card mb-5">
          <h2 className="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider text-gray-400">Trajet</h2>
          <div className="flex items-center justify-between mb-5">
            <div className="text-center">
              <div className="text-3xl font-black text-gray-900">
                {dep ? dep.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
              </div>
              <div className="font-bold text-gray-600 text-sm mt-1">
                {booking?.trip?.route?.departureCity?.name}
              </div>
            </div>
            <div className="flex-1 flex flex-col items-center gap-1 mx-6">
              <div className="w-full flex items-center gap-1">
                <div className="w-2 h-2 rounded-full bg-brand-green"></div>
                <div className="flex-1 h-0.5 bg-gray-200"></div>
                <div className="w-2 h-2 rounded-full bg-red-400"></div>
              </div>
              <div className="text-xs text-gray-400">Direct</div>
            </div>
            <div className="text-center">
              <div className="text-3xl font-black text-gray-900">
                {arr ? arr.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'}
              </div>
              <div className="font-bold text-gray-600 text-sm mt-1">
                {booking?.trip?.route?.arrivalCity?.name}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm border-t border-gray-100 pt-4">
            <div>
              <div className="text-xs text-gray-400 mb-1">Date</div>
              <div className="font-semibold">
                {dep ? dep.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : '—'}
              </div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Siège</div>
              <div className="font-black text-xl text-brand-dark">#{booking?.seatNumber}</div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Bus</div>
              <div className="font-semibold">{booking?.trip?.bus?.plateNumber || '—'}</div>
            </div>
            <div>
              <div className="text-xs text-gray-400 mb-1">Réservation #</div>
              <div className="font-mono font-semibold">{booking?.id}</div>
            </div>
          </div>
        </div>

        {/* Paiement */}
        {booking?.payment && (
          <div className="card mb-5">
            <h2 className="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider text-gray-400">Paiement</h2>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
              <div>
                <div className="text-xs text-gray-400 mb-1">Montant</div>
                <div className="font-black text-xl text-brand-dark">{booking.payment.amount} DH</div>
              </div>
              <div>
                <div className="text-xs text-gray-400 mb-1">Méthode</div>
                <div className="font-semibold">{booking.payment.paymentMethod}</div>
              </div>
              <div>
                <div className="text-xs text-gray-400 mb-1">Opérateur</div>
                <div className="font-semibold">{booking.payment.paymentProvider}</div>
              </div>
              <div className="col-span-2 md:col-span-3">
                <div className="text-xs text-gray-400 mb-1">Transaction</div>
                <div className="font-mono text-xs bg-gray-50 rounded-lg px-3 py-2">{booking.payment.transactionId}</div>
              </div>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3">
          {booking?.status === 'PAID' && (
            <button onClick={handleDownload}
              className="flex-1 bg-brand-dark text-white font-bold py-4 rounded-xl hover:bg-brand-blue transition">
              Télécharger le billet PDF
            </button>
          )}
          {booking?.status === 'PENDING' && (
            <button onClick={() => router.push(`/payment/${id}`)}
              className="flex-1 bg-brand-green text-brand-dark font-bold py-4 rounded-xl hover:bg-green-400 transition">
               Procéder au paiement
            </button>
          )}
          {['PENDING', 'PAID'].includes(booking?.status) && (
            <button onClick={handleCancel} disabled={cancelling}
              className="flex-1 border-2 border-red-200 text-red-500 font-semibold py-4 rounded-xl
                         hover:bg-red-50 transition disabled:opacity-50">
              {cancelling ? 'Annulation...' : 'Annuler la réservation'}
            </button>
          )}
        </div>
      </div>

      <Footer />
    </div>
  )
}
