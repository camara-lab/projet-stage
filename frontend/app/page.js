'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import { getCities } from '@/lib/api'
import PassengerSelector from '@/components/PassengerSelector'

const POPULAR = [
  {
    from: 'Casablanca', to: 'Marrakech', price: '80', duration: '3h30',
    img: 'https://images.unsplash.com/photo-1597211684565-dca64d72bdfe?w=600&q=80',
    desc: 'Ville Ocre & Palmeraie',
  },
  {
    from: 'Rabat', to: 'Fès', price: '60', duration: '2h45',
    img: 'https://images.unsplash.com/photo-1553603227-2358aabe821e?w=600&q=80',
    desc: 'Médina & Tanneries',
  },
  {
    from: 'Tanger', to: 'Casablanca', price: '100', duration: '5h',
    img: 'https://images.unsplash.com/photo-1548013146-72479768bada?w=600&q=80',
    desc: 'Détroit & Art de Vivre',
  },
  {
    from: 'Agadir', to: 'Marrakech', price: '70', duration: '3h',
    img: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600&q=80',
    desc: 'Corniche & Souss',
  },
]

export default function Home() {
  const router = useRouter()
  const today  = new Date().toISOString().split('T')[0]

  const [form,       setForm]       = useState({ from: '', to: '', date: today })
  const [cities,     setCities]     = useState([])
  const [fromId,     setFromId]     = useState(null)
  const [toId,       setToId]       = useState(null)
  const [passengers, setPassengers] = useState({ adults: 1, children: 0, babies: 0 })

  useEffect(() => {
    getCities().then(({ data }) => setCities(data)).catch(() => {})
  }, [])

  const handleSearch = (e) => {
    e.preventDefault()
    if (!form.from || !form.to || !form.date) return
    const fId = fromId ?? form.from
    const tId = toId   ?? form.to
    const { adults, children, babies } = passengers
    router.push(
      `/trips?from=${encodeURIComponent(fId)}&to=${encodeURIComponent(tId)}&date=${form.date}` +
      `&adults=${adults}&children=${children}&babies=${babies}`
    )
  }

  const swapCities = () => setForm((f) => ({ ...f, from: f.to, to: f.from }))

  const goPopular = (d) => {
    router.push(`/trips?from=${encodeURIComponent(d.from)}&to=${encodeURIComponent(d.to)}&date=${today}`)
  }

  return (
    <div className="min-h-screen">
      <Navbar />

      <section className="relative bg-gradient-to-br from-brand-dark via-brand-navy to-brand-blue
                          min-h-[620px] flex items-center">
        {/* Cercles décoratifs — clip isolé pour ne pas couper les dropdowns */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-brand-green opacity-[0.04]
                          rounded-full -translate-y-1/2 translate-x-1/3"></div>
          <div className="absolute bottom-0 left-0 w-72 h-72 bg-sky-500 opacity-[0.04]
                          rounded-full translate-y-1/2 -translate-x-1/3"></div>
        </div>

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 py-16 w-full">
          <div className="text-center mb-10">
            <div className="inline-flex items-center gap-2 bg-brand-green/10 border border-brand-green/30
                            text-brand-green text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
              🚌 Réservation rapide &amp; 100% marocaine
            </div>
            <h1 className="text-4xl md:text-6xl font-black text-white mb-4 leading-tight">
              Voyagez à travers
              <br />
              <span className="text-brand-green">le Maroc</span> en bus
            </h1>
            <p className="text-gray-300 text-lg md:text-xl max-w-xl mx-auto leading-relaxed">
              Comparez et réservez vos billets de bus inter-villes en quelques clics.
              Simple, rapide, sans stress.
            </p>
          </div>

          <div className="bg-white rounded-2xl shadow-2xl max-w-5xl mx-auto overflow-visible">
            <form onSubmit={handleSearch}>
              <div className="flex flex-col md:flex-row divide-y md:divide-y-0 md:divide-x divide-gray-100">

                <div className="flex-1 px-5 py-4 min-w-0">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">
                    🟢 Départ
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      list="cities-from"
                      placeholder="Ville de départ"
                      value={form.from}
                      onChange={(e) => {
                        const val = e.target.value
                        setForm({ ...form, from: val })
                        const match = cities.find((c) => c.name === val)
                        setFromId(match ? match.id : null)
                      }}
                      className="w-full text-base font-bold text-gray-900 placeholder:text-gray-300
                                 outline-none border-none bg-transparent"
                      autoComplete="off"
                      required
                    />
                    <datalist id="cities-from">
                      {cities.map((c) => <option key={c.id} value={c.name} />)}
                    </datalist>
                        <button type="button" onClick={swapCities}
                      title="Inverser"
                      className="flex-shrink-0 w-8 h-8 rounded-full border-2 border-gray-200
                                 flex items-center justify-center text-gray-400 font-bold
                                 hover:border-brand-green hover:text-brand-green
                                 hover:bg-green-50 transition-all active:scale-90 text-sm">
                      ⇆
                    </button>
                  </div>
                </div>

                <div className="flex-1 px-5 py-4 min-w-0">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">
                    🔴 Arrivée
                  </label>
                  <input
                    type="text"
                    list="cities-to"
                    placeholder="Ville d'arrivée"
                    value={form.to}
                    onChange={(e) => {
                      const val = e.target.value
                      setForm({ ...form, to: val })
                      const match = cities.find((c) => c.name === val)
                      setToId(match ? match.id : null)
                    }}
                    className="w-full text-base font-bold text-gray-900 placeholder:text-gray-300
                               outline-none border-none bg-transparent"
                    autoComplete="off"
                    required
                  />
                  <datalist id="cities-to">
                    {cities.map((c) => <option key={c.id} value={c.name} />)}
                  </datalist>
                </div>

                <div className="flex-shrink-0 px-5 py-4 w-full md:w-44">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">
                    📅 Date
                  </label>
                  <input type="date" value={form.date} min={today}
                    onChange={(e) => setForm({ ...form, date: e.target.value })}
                    className="w-full text-base font-bold text-gray-900 outline-none
                               border-none bg-transparent"
                    required />
                </div>

                <div className="flex-shrink-0 px-5 py-4 w-full md:w-52">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">
                    👥 Passagers
                  </label>
                  <PassengerSelector value={passengers} onChange={setPassengers} className="[&>button]:border-none [&>button]:p-0 [&>button]:shadow-none [&>button]:bg-transparent" />
                </div>

              </div>

              <button type="submit"
                className="w-full bg-brand-green text-brand-dark font-black text-base py-4
                           flex items-center justify-center gap-2
                           hover:bg-green-400 transition-colors rounded-b-2xl">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round"
                    d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
                </svg>
                Rechercher des trajets
              </button>

            </form>
          </div>
        </div>
      </section>

      {/* ── Stats — contraste WCAG AA corrigé ── */}
      <section className="bg-brand-green py-8">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            {[
              { n: '50+',  label: 'Destinations' },
              { n: '200+', label: 'Trajets / semaine' },
              { n: '10k+', label: 'Voyageurs satisfaits' },
              { n: '24/7', label: 'Support client' },
            ].map((s) => (
              <div key={s.label}>
                {/* Texte en bleu nuit foncé — ratio ≥ 7:1 sur fond vert fluo */}
                <div className="text-3xl font-black text-brand-dark">{s.n}</div>
                <div className="text-sm font-semibold text-brand-dark/80">{s.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="max-w-7xl mx-auto px-4 py-20">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-black text-gray-900 mb-3">Destinations populaires</h2>
          <p className="text-gray-500">Les trajets les plus empruntés par nos voyageurs</p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {POPULAR.map((d) => (
            <button key={d.from + d.to} onClick={() => goPopular(d)}
              className="group text-left rounded-2xl overflow-hidden shadow-md
                         hover:shadow-xl transition-all duration-300 hover:-translate-y-1
                         border-2 border-transparent hover:border-brand-green focus:outline-none
                         focus:ring-2 focus:ring-brand-green">

              <div className="relative h-36 overflow-hidden">
                <img
                  src={d.img}
                  alt={`${d.from} → ${d.to}`}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                <div className="absolute bottom-3 left-3">
                  <span className="text-white text-xs font-semibold bg-black/40 px-2 py-0.5
                                   rounded-full backdrop-blur-sm">
                    {d.desc}
                  </span>
                </div>
              </div>

              <div className="bg-white p-4">
                <div className="flex items-center gap-2 mb-3">
                  <div>
                    <div className="text-xs text-gray-400 font-medium">Départ</div>
                    <div className="font-black text-gray-900 text-sm">{d.from}</div>
                  </div>
                  <div className="flex-1 flex items-center gap-1 mx-1">
                    <div className="w-2 h-2 rounded-full bg-brand-green flex-shrink-0"></div>
                    <div className="flex-1 h-px bg-gradient-to-r from-brand-green to-gray-300"></div>
                    <svg className="w-3 h-3 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <div className="text-right">
                    <div className="text-xs text-gray-400 font-medium">Arrivée</div>
                    <div className="font-black text-gray-900 text-sm">{d.to}</div>
                  </div>
                </div>

                <div className="flex items-center justify-between">
                  <span className="text-brand-green font-black text-base">à partir de {d.price} DH</span>
                  <span className="text-gray-400 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-full">
                    ⏱ {d.duration}
                  </span>
                </div>
                <div className="mt-2 text-xs text-sky-600 font-semibold
                                group-hover:text-brand-green transition-colors">
                  Voir les trajets →
                </div>
              </div>
            </button>
          ))}
        </div>
      </section>

      <section className="bg-gray-50 py-20">
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-black text-gray-900 mb-3">Pourquoi choisir BusGo ?</h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {[
              { icon: '⚡', title: 'Réservation rapide',  desc: 'Réservez votre billet en moins de 2 minutes sans file d\'attente.' },
              { icon: '🔒', title: 'Paiement 100% marocain', desc: 'CMI, Cash Plus, Maroc Pay, virement bancaire — vos méthodes habituelles.' },
              { icon: '📱', title: 'E-billet mobile',     desc: 'Téléchargez votre billet PDF avec QR Code et montrez-le à l\'embarquement.' },
            ].map((f) => (
              <div key={f.title} className="card text-center hover:shadow-md transition">
                <div className="text-5xl mb-4">{f.icon}</div>
                <h3 className="font-bold text-gray-900 text-lg mb-2">{f.title}</h3>
                <p className="text-gray-500 text-sm leading-relaxed">{f.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-white py-20">
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-10">
            <h2 className="text-3xl font-black text-gray-900 mb-3">
              Profitez de nos services à bord
            </h2>
            <p className="text-gray-500">
              Voyagez confortablement avec tout ce qu'il faut pour un trajet agréable
            </p>
          </div>

          <div className="rounded-2xl overflow-hidden mb-12 shadow-lg max-w-4xl mx-auto">
            <img
              src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=1200&q=80"
              alt="Bus BusGo sur la route"
              className="w-full h-64 md:h-80 object-cover"
            />
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {[
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 9h6M9 13h4" />
                  </svg>
                ),
                color: 'bg-green-50 text-brand-green',
                title: 'Confort',
                desc: 'Siège confortable et inclinable avec un large espace pour les jambes.',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                  </svg>
                ),
                color: 'bg-sky-50 text-sky-500',
                title: 'Wifi gratuit',
                desc: 'Wifi gratuit à bord pour rester connecté durant tout le voyage.',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                ),
                color: 'bg-amber-50 text-amber-500',
                title: 'Port USB',
                desc: 'Port de recharge USB à chaque siège, adapté à tous vos appareils.',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round"
                      d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-5.66l-.71.71M6.34 17.66l-.71.71m12.02 0l-.71-.71M6.34 6.34l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z" />
                  </svg>
                ),
                color: 'bg-blue-50 text-blue-400',
                title: 'Climatisation',
                desc: 'Air conditionné réglable pour une température agréable en toute saison.',
              },
            ].map((s) => (
              <div key={s.title} className="card text-center hover:shadow-md transition group">
                <div className={`w-14 h-14 rounded-2xl ${s.color} flex items-center justify-center
                                mx-auto mb-4 group-hover:scale-110 transition-transform`}>
                  {s.icon}
                </div>
                <h3 className="font-bold text-gray-900 text-base mb-2">{s.title}</h3>
                <p className="text-gray-500 text-sm leading-relaxed">{s.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-brand-dark py-20 text-center">
        <div className="max-w-2xl mx-auto px-4">
          <h2 className="text-3xl font-black text-white mb-4">Prêt à voyager ?</h2>
          <p className="text-gray-300 mb-8">
            Créez votre compte gratuitement et réservez votre premier trajet en quelques secondes.
          </p>
          <a href="/auth/register"
            className="inline-block bg-brand-green text-brand-dark font-black px-10 py-4
                       rounded-xl text-lg hover:bg-green-400 transition">
            Commencer maintenant →
          </a>
        </div>
      </section>

      <Footer />
    </div>
  )
}
