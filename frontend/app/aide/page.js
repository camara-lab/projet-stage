'use client'
import { useState, useEffect } from 'react'
import { useSearchParams } from 'next/navigation'
import { Suspense } from 'react'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

const FAQ_ITEMS = [
  {
    q: 'Comment réserver un billet de bus ?',
    a: "Recherchez votre trajet depuis la page d'accueil (ville de départ, arrivée et date), choisissez l'horaire qui vous convient, sélectionnez votre siège puis procédez au paiement. Votre e-billet est émis immédiatement.",
  },
  {
    q: 'Quels moyens de paiement acceptez-vous ?',
    a: 'Nous acceptons les paiements par carte bancaire (CMI), en espèces (Cash Plus / Maroc Pay) et par virement bancaire. Toutes les transactions sont sécurisées.',
  },
  {
    q: 'Puis-je annuler ou modifier ma réservation ?',
    a: "Vous pouvez annuler votre réservation depuis votre espace « Mes réservations ». Le remboursement dépend du délai avant le départ : remboursement intégral si annulation > 48h, partiel entre 24h et 48h, aucun remboursement en dessous de 24h.",
  },
  {
    q: 'Comment récupérer mon billet ?',
    a: "Votre billet PDF est disponible dans votre espace « Mes réservations » dès que le paiement est confirmé. Vous pouvez le télécharger et le présenter à l'embarquement (version numérique ou imprimée).",
  },
  {
    q: 'Que faire si je paie en espèces ?',
    a: "Si vous choisissez le paiement en espèces, votre réservation reste en statut « En attente » jusqu'à confirmation du paiement auprès d'un point de vente partenaire. Munissez-vous de votre numéro de réservation.",
  },
  {
    q: 'Le paiement en ligne est-il sécurisé ?',
    a: "Oui, tous les paiements en ligne sont traités via des protocoles sécurisés (HTTPS/TLS). Vos données bancaires ne sont jamais stockées sur nos serveurs.",
  },
]

function FaqItem({ item }) {
  const [open, setOpen] = useState(false)
  return (
    <div className="border border-gray-100 rounded-xl overflow-hidden">
      <button
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center justify-between px-5 py-4 text-left
                   hover:bg-gray-50 transition-colors"
      >
        <span className="font-semibold text-gray-800 text-sm">{item.q}</span>
        <svg
          className={`w-4 h-4 text-gray-400 flex-shrink-0 ml-4 transition-transform ${open ? 'rotate-180' : ''}`}
          fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      {open && (
        <div className="px-5 pb-4 text-sm text-sky-600 leading-relaxed border-t border-gray-50">
          <p className="pt-3">{item.a}</p>
        </div>
      )}
    </div>
  )
}

function AideContent() {
  const searchParams = useSearchParams()
  const [tab, setTab] = useState(searchParams.get('tab') === 'contact' ? 'contact' : 'faq')

  useEffect(() => {
    if (searchParams.get('tab') === 'contact') setTab('contact')
  }, [searchParams])

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      {/* Hero */}
      <div className="bg-brand-dark py-14 text-center px-4">
        <h1 className="text-3xl font-black text-white mb-2">Centre d'aide</h1>
        <p className="text-gray-300 text-sm mb-8">
          Trouvez une réponse ou contactez notre équipe support.
        </p>
        <div className="inline-flex items-center gap-3">
          <button
            onClick={() => setTab('faq')}
            className={`px-6 py-2.5 rounded-xl text-sm font-bold transition
              ${tab === 'faq'
                ? 'bg-brand-green text-brand-dark'
                : 'bg-white/10 text-white hover:bg-white/20'}`}
          >
            FAQ
          </button>
          <button
            onClick={() => setTab('contact')}
            className={`px-6 py-2.5 rounded-xl text-sm font-bold transition
              ${tab === 'contact'
                ? 'bg-brand-green text-brand-dark'
                : 'bg-white/10 text-white hover:bg-white/20'}`}
          >
            Nous contacter
          </button>
        </div>
      </div>

      <div className="max-w-2xl mx-auto px-4 py-12">

        {/* ── FAQ ── */}
        {tab === 'faq' && (
          <div className="space-y-3">
            {FAQ_ITEMS.map((item) => (
              <FaqItem key={item.q} item={item} />
            ))}

            {/* Encart contact */}
            <div className="mt-8 bg-white rounded-2xl border border-gray-100 p-6
                            flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
              <div>
                <p className="font-bold text-gray-900 text-sm">Vous ne trouvez pas votre réponse ?</p>
                <p className="text-gray-400 text-xs mt-0.5">Notre équipe vous répond en moins de 24h.</p>
              </div>
              <button
                onClick={() => setTab('contact')}
                className="bg-brand-dark text-white text-sm font-bold px-5 py-2.5
                           rounded-xl hover:bg-brand-blue transition whitespace-nowrap"
              >
                Nous contacter
              </button>
            </div>
          </div>
        )}

        {/* ── Nous contacter ── */}
        {tab === 'contact' && (
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
            <h2 className="text-xl font-black text-gray-900 mb-1">Contactez-nous</h2>
            <p className="text-gray-400 text-sm mb-6">Réponse garantie en moins de 24h.</p>

            <form
              onSubmit={(e) => {
                e.preventDefault()
                alert('Message envoyé ! Notre équipe vous répondra sous 24h.')
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nom complet</label>
                <input type="text" placeholder="Youssef Alami" required
                  className="input-field" />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Adresse email</label>
                <input type="email" placeholder="vous@exemple.ma" required
                  className="input-field" />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Sujet</label>
                <select className="input-field" required>
                  <option value="">Choisir un sujet</option>
                  <option>Problème de réservation</option>
                  <option>Remboursement</option>
                  <option>Billet introuvable</option>
                  <option>Question sur les trajets</option>
                  <option>Autre</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">Message</label>
                <textarea rows={5} placeholder="Décrivez votre problème ou question…" required
                  className="input-field resize-none" />
              </div>
              <button type="submit"
                className="w-full bg-brand-dark text-white font-bold py-3.5 rounded-xl
                           hover:bg-brand-blue transition text-sm">
                Envoyer le message
              </button>
            </form>

            {/* Canaux alternatifs */}
            <div className="mt-8 pt-6 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
              {[
                { icon: '📧', label: 'Email', val: 'support@busgo.ma' },
                { icon: '📞', label: 'Téléphone', val: '0800 123 456' },
                { icon: '⏰', label: 'Disponible', val: 'Lun–Sam 8h–20h' },
              ].map((c) => (
                <div key={c.label} className="bg-gray-50 rounded-xl p-4">
                  <div className="text-2xl mb-1">{c.icon}</div>
                  <div className="text-xs font-bold text-gray-500 uppercase tracking-wider">{c.label}</div>
                  <div className="text-sm font-semibold text-gray-800 mt-0.5">{c.val}</div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      <Footer />
    </div>
  )
}

export default function AidePage() {
  return (
    <Suspense fallback={<div className="min-h-screen" />}>
      <AideContent />
    </Suspense>
  )
}
