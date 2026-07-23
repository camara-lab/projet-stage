import Link from 'next/link'

export default function Footer() {
  return (
    <footer className="bg-brand-dark text-gray-400 mt-20">
      <div className="max-w-7xl mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div>
            <div className="flex items-center gap-2 mb-4">
              <span className="text-2xl">🚌</span>
              <span className="font-black text-xl text-white tracking-tight">
                Bus<span className="text-brand-green">Go</span>
              </span>
            </div>
            <p className="text-sm leading-relaxed">
              La solution moderne pour réserver vos billets de bus inter-villes au Maroc.
            </p>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-4">Voyageurs</h4>
            <ul className="space-y-2 text-sm">
              <li><Link href="/" className="hover:text-white transition">Rechercher un trajet</Link></li>
              <li><Link href="/dashboard" className="hover:text-white transition">Mes réservations</Link></li>
              <li><Link href="/auth/register" className="hover:text-white transition">Créer un compte</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-4">Destinations populaires</h4>
            <ul className="space-y-2 text-sm">
              <li className="hover:text-white transition cursor-pointer">Casablanca → Marrakech</li>
              <li className="hover:text-white transition cursor-pointer">Rabat → Fès</li>
              <li className="hover:text-white transition cursor-pointer">Tanger → Agadir</li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-4">Contact</h4>
            <ul className="space-y-2 text-sm">
              <li>📧 contact@busgo.ma</li>
              <li>📞 +212 5XX-XXXXXX</li>
              <li>🕐 24h/24, 7j/7</li>
            </ul>
          </div>
        </div>
        <div className="border-t border-gray-700 mt-10 pt-6 text-center text-xs">
          <div className="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 mb-4">
            <Link href="/cgv" className="hover:text-white transition">Conditions Générales de Vente</Link>
            <Link href="/confidentialite" className="hover:text-white transition">Politique de confidentialité</Link>
            <Link href="/mentions-legales" className="hover:text-white transition">Mentions légales</Link>
            <Link href="/remboursement" className="hover:text-white transition">Politique de remboursement</Link>
            <Link href="/aide?tab=contact" className="hover:text-white transition">Contact</Link>
          </div>
          © {new Date().getFullYear()} BusGo Tous droits réservés
        </div>
      </div>
    </footer>
  )
}
