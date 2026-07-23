'use client'
import { useState, useEffect, Suspense } from 'react'
import { useRouter, useParams, useSearchParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { getBooking, payBooking } from '@/lib/api'
import { PaymentSkeleton } from '@/components/Skeleton'
import { calcTotalPrice } from '@/components/PassengerSelector'
import { normalizePassengers } from '@/lib/passengers'
import { getBasePrice } from '@/lib/trips'
import PassengerInfoForm, { emptyPassenger, isPassengerComplete } from '@/components/PassengerInfoForm'
import ExtrasSelector, { calcExtrasTotal, selectedExtrasList } from '@/components/ExtrasSelector'
import { getUser } from '@/lib/auth'
import toast from 'react-hot-toast'

const METHODS = [
  { id: 'CARD',     label: 'Carte bancaire',      provider: 'CMI',      desc: 'Visa · Mastercard · CMI',             logos: ['VISA','MC','CMI'],         icon: '💳' },
  { id: 'CASH',     label: 'Paiement en espèces', provider: 'CASHPLUS', desc: 'Cash Plus · Wafacash · Al Barid Bank', logos: ['CASH+','WAFA','ABB'],      icon: '💵' },
  { id: 'WALLET',   label: 'M-Wallet / Maroc Pay',provider: 'MAROCPAY', desc: 'Paiement mobile instantané',           logos: ['MAROCPAY'],                icon: '📱' },
  { id: 'TRANSFER', label: 'Virement bancaire',   provider: 'CIH',      desc: 'CIH Bank · Attijariwafa · BCP',        logos: ['CIH','ATW','BCP'],         icon: '🏦' },
]

const RIB_BUSGO = 'CIH 230 790 4001234567890126 77'

function generateCashCode() {
  return Array.from({ length: 12 }, () => Math.floor(Math.random() * 10)).join('')
    .replace(/(\d{4})(\d{4})(\d{4})/, '$1 $2 $3')
}

function LogoBadge({ text }) {
  const colors = { VISA:'bg-blue-700 text-white', MC:'bg-red-600 text-white', CMI:'bg-brand-dark text-brand-green', 'CASH+':'bg-orange-500 text-white', WAFA:'bg-amber-600 text-white', ABB:'bg-yellow-600 text-white', MAROCPAY:'bg-emerald-600 text-white', CIH:'bg-blue-900 text-white', ATW:'bg-red-800 text-white', BCP:'bg-green-800 text-white' }
  return <span className={`text-[10px] font-black px-2 py-0.5 rounded-md tracking-wide ${colors[text] || 'bg-gray-200 text-gray-700'}`}>{text}</span>
}

function CardForm({ onChange }) {
  const [card, setCard] = useState({ number: '', expiry: '', cvv: '', name: '' })
  const set = (field) => (e) => {
    let val = e.target.value
    if (field === 'number') val = val.replace(/\D/g,'').slice(0,16).replace(/(\d{4})/g,'$1 ').trim()
    if (field === 'expiry') val = val.replace(/\D/g,'').slice(0,4).replace(/(\d{2})(\d)/,'$1/$2')
    if (field === 'cvv')    val = val.replace(/\D/g,'').slice(0,3)
    const updated = { ...card, [field]: val }
    setCard(updated); onChange(updated)
  }
  return (
    <div className="space-y-3 mt-4">
      <div><label className="block text-xs font-bold text-gray-600 mb-1">Numéro de carte</label>
        <input value={card.number} onChange={set('number')} placeholder="0000 0000 0000 0000" className="input-field font-mono tracking-widest text-sm" inputMode="numeric" /></div>
      <div><label className="block text-xs font-bold text-gray-600 mb-1">Nom sur la carte</label>
        <input value={card.name} onChange={set('name')} placeholder="YOUSSEF ALAMI" className="input-field uppercase text-sm" /></div>
      <div className="grid grid-cols-2 gap-3">
        <div><label className="block text-xs font-bold text-gray-600 mb-1">Expiration</label>
          <input value={card.expiry} onChange={set('expiry')} placeholder="MM/AA" className="input-field text-sm" inputMode="numeric" /></div>
        <div><label className="block text-xs font-bold text-gray-600 mb-1">CVV</label>
          <input value={card.cvv} onChange={set('cvv')} placeholder="•••" type="password" className="input-field text-sm" inputMode="numeric" /></div>
      </div>
      <div className="flex items-center gap-2 text-xs text-gray-400 bg-gray-50 rounded-xl p-2.5">
        <span></span><span>Paiement 100% sécurisé certifié par le <strong className="text-gray-600">CMI</strong></span>
      </div>
    </div>
  )
}

function CashBlock({ amount }) {
  const [code] = useState(generateCashCode)
  const [copied, setCopied] = useState(false)
  const copy = () => { navigator.clipboard.writeText(code.replace(/\s/g,'')); setCopied(true); setTimeout(()=>setCopied(false),2000) }
  return (
    <div className="mt-4 space-y-4">
      <div className="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center">
        <p className="text-xs text-orange-700 font-semibold mb-2">Votre code de paiement unique</p>
        <div className="text-3xl font-black tracking-[0.25em] text-orange-700 font-mono mb-2">{code}</div>
        <button onClick={copy} className="text-xs font-bold text-orange-600 hover:text-orange-800 transition border border-orange-300 rounded-lg px-3 py-1 hover:bg-orange-100">
          {copied ? '✓ Copié !' : 'Copier le code'}
        </button>
      </div>
      <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800 space-y-1.5">
        <p className="font-bold"> Comment payer ?</p>
        <p>• Rendez-vous dans une agence <strong>Cash Plus</strong>, <strong>Wafacash</strong> ou <strong>Al Barid Bank</strong></p>
        <p>• Communiquez ce code et réglez <strong>{amount} DH</strong></p>
        <p>• Vous avez <strong>24h</strong> pour valider votre billet</p>
      </div>
    </div>
  )
}

function WalletForm({ onChange }) {
  const [phone, setPhone] = useState('')
  return (
    <div className="mt-4">
      <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-2 mb-3 text-xs text-emerald-800 font-semibold">
        <span>🇲🇦</span> Paiement via votre portefeuille Maroc Pay
      </div>
      <label className="block text-xs font-bold text-gray-600 mb-1">Numéro de téléphone lié au wallet</label>
      <div className="flex gap-2">
        <span className="input-field w-16 text-center text-sm font-bold text-gray-500 !cursor-default">+212</span>
        <input value={phone} inputMode="tel" placeholder="6XXXXXXXX"
          onChange={(e) => { const v=e.target.value.replace(/\D/g,'').slice(0,9); setPhone(v); onChange(v) }}
          className="input-field flex-1 text-sm" />
      </div>
    </div>
  )
}

function TransferBlock() {
  const [copied, setCopied] = useState(false)
  const copy = () => { navigator.clipboard.writeText(RIB_BUSGO.replace(/\s/g,'')); setCopied(true); setTimeout(()=>setCopied(false),2000) }
  return (
    <div className="mt-4 space-y-3">
      <div className="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <p className="text-xs font-bold text-gray-500 mb-1">RIB BusGo</p>
        <div className="flex items-center justify-between gap-3">
          <span className="font-mono text-sm font-bold text-gray-800 break-all">{RIB_BUSGO}</span>
          <button onClick={copy} className="flex-shrink-0 text-xs font-bold text-brand-dark border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-100 transition whitespace-nowrap">
            {copied ? '✓ Copié' : '📋 Copier'}
          </button>
        </div>
      </div>
      <div className="bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800 space-y-1">
        <p className="font-bold">Instructions</p>
        <p>• Effectuez le virement depuis votre banque (CIH, Attijariwafa, BCP…)</p>
        <p>• Indiquez la <strong>référence de réservation</strong> dans le libellé</p>
        <p>• Votre billet sera activé sous <strong>2h ouvrées</strong></p>
      </div>
    </div>
  )
}

function PaymentContent() {
  const { bookingId } = useParams()
  const router        = useRouter()
  const searchParams  = useSearchParams()

  const allIds                        = (searchParams.get('bookings') || bookingId).split(',').map(Number).filter(Boolean)
  const { adults, children, babies }  = normalizePassengers(searchParams)

  const [bookings,   setBookings]   = useState([])
  const [loading,    setLoading]    = useState(true)
  const [method,     setMethod]     = useState('CARD')
  const [paying,     setPaying]     = useState(false)
  const [step,       setStep]       = useState(1)
  const [extraData,  setExtraData]  = useState({})

  // Informations des passagers, conservées en sessionStorage pendant la réservation
  const paxKey = `busgo_pax_${allIds.join('-')}`
  const [paxList, setPaxList] = useState([])

  // Extras, conservés en sessionStorage pendant la réservation
  const extrasKey = `busgo_extras_${allIds.join('-')}`
  const [extras, setExtras] = useState({})

  // Acceptation des CGV / politique de confidentialité
  const [termsAccepted, setTermsAccepted] = useState(false)

  const selectedMethod = METHODS.find((m) => m.id === method)
  const allPaxComplete = paxList.length > 0 && paxList.every(isPassengerComplete)

  useEffect(() => {
    if (!localStorage.getItem('token')) { router.push('/auth/login'); return }

    // Restaurer les infos passagers si l'utilisateur revient sur la page
    try {
      const savedExtras = JSON.parse(sessionStorage.getItem(extrasKey))
      if (savedExtras) setExtras(savedExtras)
    } catch {}

    let saved = null
    try { saved = JSON.parse(sessionStorage.getItem(paxKey)) } catch {}
    if (saved?.length) {
      setPaxList(saved)
    } else {
      const list = [
        ...Array.from({ length: adults },   () => emptyPassenger('adult')),
        ...Array.from({ length: children }, () => emptyPassenger('child')),
        ...Array.from({ length: babies },   () => emptyPassenger('baby')),
      ]
      // Préremplir le 1er adulte avec le compte connecté
      const user = getUser()
      if (list[0] && user?.fullName) {
        const parts = user.fullName.trim().split(' ')
        list[0] = { ...list[0], prenom: parts[0] || '', nom: parts.slice(1).join(' ') || '' }
      }
      setPaxList(list)
    }

    Promise.all(allIds.map((id) => getBooking(id).then(({ data }) => data)))
      .then(setBookings)
      .catch(() => { toast.error('Réservation introuvable'); router.push('/dashboard') })
      .finally(() => setLoading(false))
  }, [bookingId])

  const updatePaxList = (next) => {
    setPaxList(next)
    try { sessionStorage.setItem(paxKey, JSON.stringify(next)) } catch {}
  }

  const updateExtras = (next) => {
    setExtras(next)
    try { sessionStorage.setItem(extrasKey, JSON.stringify(next)) } catch {}
  }

  const firstBooking = bookings[0]
  const basePrice    = getBasePrice(firstBooking?.trip)
  const childPrice   = +(basePrice * 0.75).toFixed(2)
  const total        = calcTotalPrice(basePrice, { adults, children, babies })
  const extrasTotal  = calcExtrasTotal(extras)
  const extrasChosen = selectedExtrasList(extras)
  const grandTotal   = total + extrasTotal
  const multiPax     = (adults + children + babies) > 1
  const seats        = bookings.map((b) => b.seatNumber).filter(Boolean)

  const handlePay = async () => {
    if (!termsAccepted) {
      toast.error('Veuillez accepter les Conditions Générales de Vente pour continuer')
      return
    }
    // Trace de l'acceptation, conservée avec la réservation côté client
    try {
      localStorage.setItem(`busgo_terms_${allIds.join('-')}`, JSON.stringify({ acceptedAt: new Date().toISOString() }))
    } catch {}
    setPaying(true)
    try {
      for (const id of allIds) {
        await payBooking(id, {
          paymentMethod:   method,
          paymentProvider: selectedMethod?.provider ?? method,
          transactionId:   `TXN-${Date.now()}-${id}`,
          amount:          method === 'CARD' ? total : undefined,
        })
      }
      try { sessionStorage.removeItem(paxKey); sessionStorage.removeItem(extrasKey) } catch {}
      toast.success(`${allIds.length} billet${allIds.length > 1 ? 's' : ''} confirmé${allIds.length > 1 ? 's' : ''} ! 🎉`)
      router.push('/dashboard?paid=1')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Erreur lors du paiement')
    } finally {
      setPaying(false)
    }
  }

  if (loading) return <div className="min-h-screen bg-gray-50"><Navbar /><PaymentSkeleton /></div>

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <div className="max-w-3xl mx-auto px-4 py-10">

        <button onClick={() => step === 1 ? router.back() : setStep(step - 1)}
          className="flex items-center gap-2 text-gray-500 hover:text-gray-800 mb-6 text-sm font-medium">
          ← {step === 1 ? 'Retour' : 'Étape précédente'}
        </button>

        <h1 className="text-2xl font-black text-gray-900 mb-6">
          {step === 1 ? 'Informations des passagers' : 'Paiement sécurisé'}
        </h1>

        <div className="flex items-center gap-3 mb-8">
          {['Passagers', 'Méthode de paiement', 'Confirmation'].map((s, i) => (
            <div key={s} className="flex items-center gap-2">
              <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-black
                ${step > i+1 ? 'bg-brand-green text-brand-dark' : step === i+1 ? 'bg-brand-dark text-white' : 'bg-gray-200 text-gray-400'}`}>
                {step > i+1 ? '✓' : i+1}
              </div>
              <span className={`text-sm font-semibold hidden sm:inline ${step === i+1 ? 'text-gray-900' : 'text-gray-400'}`}>{s}</span>
              {i < 2 && <div className="w-6 sm:w-12 h-0.5 bg-gray-200" />}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div className="md:col-span-3 space-y-4">

            {step === 1 && (
              <>
                <PassengerInfoForm passengers={paxList} onChange={updatePaxList} />
                <ExtrasSelector value={extras} onChange={updateExtras} />
                <button
                  onClick={() => allPaxComplete
                    ? setStep(2)
                    : toast.error('Complétez les informations de tous les passagers')}
                  disabled={!allPaxComplete}
                  className="w-full bg-brand-green text-brand-dark font-black py-4 rounded-xl
                             hover:bg-green-400 transition text-base
                             disabled:opacity-40 disabled:cursor-not-allowed">
                  {allPaxComplete ? 'Continuer vers le paiement →' : 'Complétez les informations des passagers'}
                </button>
              </>
            )}

            {step === 2 && (
              <>
                <div className="card">
                  <h2 className="font-black text-gray-900 mb-4 text-base">Choisissez votre méthode de paiement</h2>
                  <div className="space-y-3">
                    {METHODS.map((m) => {
                      const active = method === m.id
                      return (
                        <button key={m.id} onClick={() => setMethod(m.id)}
                          className={`w-full text-left rounded-xl border-2 p-3.5 transition-all
                            ${active ? 'border-brand-green bg-green-50 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                          <div className="flex items-start gap-3">
                            <span className="text-2xl">{m.icon}</span>
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center gap-2 mb-1 flex-wrap">
                                <span className="font-bold text-gray-900 text-sm">{m.label}</span>
                                {m.logos.map((l) => <LogoBadge key={l} text={l} />)}
                              </div>
                              <div className="text-xs text-gray-400">{m.desc}</div>
                            </div>
                            <div className={`w-5 h-5 rounded-full border-2 flex-shrink-0 mt-0.5 flex items-center justify-center transition-all ${active ? 'bg-brand-green border-brand-green' : 'border-gray-300'}`}>
                              {active && <svg className="w-3 h-3 text-brand-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>}
                            </div>
                          </div>
                        </button>
                      )
                    })}
                  </div>
                </div>
                <div className="card">
                  {method === 'CARD'     && <CardForm onChange={(d) => setExtraData(d)} />}
                  {method === 'CASH'     && <CashBlock amount={total.toFixed(2)} />}
                  {method === 'WALLET'   && <WalletForm onChange={(p) => setExtraData({ phone: p })} />}
                  {method === 'TRANSFER' && <TransferBlock />}
                </div>
                <button onClick={() => setStep(3)}
                  className="w-full bg-brand-green text-brand-dark font-black py-4 rounded-xl hover:bg-green-400 transition text-base">
                  Continuer →
                </button>
              </>
            )}

            {step === 3 && (
              <div className="card">
                <h2 className="font-black text-gray-900 mb-5 text-base">Confirmez votre paiement</h2>
                <div className="flex items-center gap-3 p-3.5 bg-gray-50 rounded-xl mb-5">
                  <span className="text-2xl">{selectedMethod?.icon}</span>
                  <div>
                    <div className="font-bold text-gray-900 text-sm">{selectedMethod?.label}</div>
                    <div className="flex gap-1.5 mt-1 flex-wrap">{selectedMethod?.logos.map((l) => <LogoBadge key={l} text={l} />)}</div>
                  </div>
                </div>

                <div className="bg-gradient-to-br from-brand-dark to-brand-navy rounded-xl p-5 text-white text-center mb-5">
                  <div className="text-sm text-gray-300 mb-1">Montant total à payer</div>
                  <div className="text-4xl font-black text-brand-green">{grandTotal.toFixed(2)} <span className="text-2xl">DH</span></div>
                  {extrasTotal > 0 && (
                    <div className="text-xs text-gray-400 mt-0.5">dont {extrasTotal} DH d'extras</div>
                  )}
                  <div className="text-xs text-gray-400 mt-1">
                    {allIds.length} billet{allIds.length > 1 ? 's' : ''} · sièges {seats.join(', ')}
                  </div>
                </div>

                <div className="bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-700 mb-5">
                  Cette opération est une simulation pédagogique. Aucun vrai paiement ne sera effectué.
                </div>

                {/* Conditions & confidentialité */}
                <div className="border border-gray-200 rounded-xl p-4 mb-5">
                  <label className="flex items-start gap-3 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={termsAccepted}
                      onChange={(e) => setTermsAccepted(e.target.checked)}
                      className="mt-0.5 w-5 h-5 rounded border-gray-300 accent-brand-green flex-shrink-0 cursor-pointer"
                    />
                    <span className="text-sm text-gray-700 leading-relaxed">
                      J'ai lu et j'accepte les{' '}
                      <a href="/cgv" target="_blank" className="font-semibold text-brand-dark underline underline-offset-2 hover:text-brand-blue">
                        Conditions Générales de Vente
                      </a>{' '}
                      ainsi que la{' '}
                      <a href="/confidentialite" target="_blank" className="font-semibold text-brand-dark underline underline-offset-2 hover:text-brand-blue">
                        Politique de confidentialité
                      </a>. <span className="text-red-500">*</span>
                    </span>
                  </label>
                  <p className="text-xs text-gray-400 mt-3 pl-8">
                    Vos données personnelles sont traitées conformément à notre Politique de
                    confidentialité et sont utilisées uniquement pour gérer votre réservation.
                  </p>
                </div>

                <div className="flex flex-col gap-3">
                  <button onClick={handlePay} disabled={paying || !termsAccepted}
                    className="w-full bg-brand-green text-brand-dark font-black py-4 rounded-xl hover:bg-green-400 transition disabled:opacity-50 text-base flex items-center justify-center gap-2">
                    {paying ? <><span className="animate-spin">⏳</span> Traitement…</> : <> Payer {grandTotal.toFixed(2)} DH maintenant</>}
                  </button>
                  <button onClick={() => setStep(2)} className="text-sm text-gray-500 hover:text-gray-800 transition underline underline-offset-2 text-center">
                    ← Modifier la méthode de paiement
                  </button>
                </div>
              </div>
            )}
          </div>

          <div className="md:col-span-2">
            <div className="card sticky top-24">
              <h3 className="font-bold text-gray-900 mb-4 text-sm border-b border-gray-50 pb-3">
                Votre réservation
              </h3>
              <div className="space-y-2.5 text-sm">
                <div className="flex flex-col gap-1">
                  <div className="font-bold text-gray-900 text-base">
                    {firstBooking?.trip?.villeDepart ?? firstBooking?.trip?.route?.departureCity?.name}
                    {' → '}
                    {firstBooking?.trip?.villeArrivee ?? firstBooking?.trip?.route?.arrivalCity?.name}
                  </div>
                  <div className="text-gray-400 text-xs">
                    {(() => { const dt = firstBooking?.trip?.heureDepart ?? firstBooking?.trip?.departureTime; return dt ? new Date(dt).toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' }) : '—' })()}
                  </div>
                </div>

                <div className="flex flex-wrap gap-1 pt-1">
                  {seats.map((s) => (
                    <span key={s} className="badge-green text-xs">Siège {s}</span>
                  ))}
                </div>

                <div className="border-t border-gray-100 pt-3 space-y-1.5">
                  {adults > 0 && (
                    <div className="flex justify-between text-xs text-gray-500">
                      <span>{adults} adulte{adults>1?'s':''} × {basePrice} DH</span>
                      <span>{(adults * basePrice).toFixed(2)} DH</span>
                    </div>
                  )}
                  {children > 0 && (
                    <div className="flex justify-between text-xs text-sky-600">
                      <span>{children} enfant{children>1?'s':''} × {childPrice} DH <span className="text-sky-400">(−25%)</span></span>
                      <span>{(children * childPrice).toFixed(2)} DH</span>
                    </div>
                  )}
                  {babies > 0 && (
                    <div className="flex justify-between text-xs text-brand-green">
                      <span>{babies} bébé{babies>1?'s':''} · gratuit</span>
                      <span>0 DH</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center mt-1">
                    <span className="text-gray-400 text-xs">Frais de service</span>
                    <span className="text-green-600 text-xs font-semibold">Gratuit</span>
                  </div>

                  {extrasChosen.length > 0 && (
                    <div className="border-t border-gray-100 pt-2 mt-2 space-y-1.5">
                      <div className="text-xs font-bold text-gray-500">Extras</div>
                      {extrasChosen.map((x) => (
                        <div key={x.id} className="flex justify-between text-xs text-gray-500">
                          <span>{x.icon} {x.name}{x.qty > 1 ? ` × ${x.qty}` : ''}</span>
                          <span>{x.price === 0 ? 'Gratuit' : `${(x.price * x.qty).toFixed(2)} DH`}</span>
                        </div>
                      ))}
                    </div>
                  )}

                  <div className="flex justify-between font-black mt-2 pt-2 border-t border-gray-100 text-base">
                    <span>Total</span>
                    <span className="text-brand-dark">{grandTotal.toFixed(2)} DH</span>
                  </div>
                </div>

                <div className="bg-gray-50 rounded-xl p-2.5 text-xs text-gray-400 flex items-start gap-2">
                  <span></span>
                  <span>Connexion SSL 256 bits. Données bancaires jamais stockées.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default function PaymentPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-gray-50"><Navbar /><PaymentSkeleton /></div>}>
      <PaymentContent />
    </Suspense>
  )
}
