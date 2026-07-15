import './globals.css'
import { Toaster } from 'react-hot-toast'

export const metadata = {
  title: 'BusGo Réservation de bus inter-villes',
  description: 'Réservez vos billets de bus entre villes au Maroc',
}

export default function RootLayout({ children }) {
  return (
    <html lang="fr">
      <body className="bg-gray-50 text-gray-900 min-h-screen">
        <Toaster position="top-right" toastOptions={{
          style: { borderRadius: '12px', fontFamily: 'Inter' }
        }} />
        {children}
      </body>
    </html>
  )
}
