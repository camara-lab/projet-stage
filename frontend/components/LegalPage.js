import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

export default function LegalPage({ title, updated = 'juillet 2026', sections }) {
  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <div className="max-w-3xl mx-auto px-4 py-12">
        <h1 className="text-3xl font-black text-gray-900 mb-2">{title}</h1>
        <p className="text-xs text-gray-400 mb-8">Dernière mise à jour : {updated}</p>

        <div className="card space-y-6">
          {sections.map((s, i) => (
            <section key={i}>
              <h2 className="font-black text-gray-900 text-base mb-2">{i + 1}. {s.title}</h2>
              <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{s.body}</p>
            </section>
          ))}
        </div>
      </div>
      <Footer />
    </div>
  )
}
