'use client'
import Link from 'next/link'

export default function NotFound() {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="text-center">
        <div className="text-8xl mb-6">🚌</div>
        <h1 className="text-6xl font-black text-brand-dark mb-4">404</h1>
        <h2 className="text-2xl font-bold text-gray-700 mb-3">Page introuvable</h2>
        <p className="text-gray-400 mb-8 max-w-sm mx-auto">
          Ce bus ne dessert pas cette destination. La page que vous cherchez n'existe pas.
        </p>
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <Link href="/"
            className="bg-brand-dark text-white font-bold px-8 py-4 rounded-xl hover:bg-brand-blue transition">
            Retour à l'accueil
          </Link>
          <Link href="/trips"
            className="border-2 border-gray-200 text-gray-700 font-semibold px-8 py-4 rounded-xl hover:border-gray-300 transition">
            Voir les trajets
          </Link>
        </div>
      </div>
    </div>
  )
}
