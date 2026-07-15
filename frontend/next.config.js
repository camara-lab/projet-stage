/** @type {import('next').NextConfig} */
const nextConfig = {
  /**
   * En dev : API_URL pointe vers php -S localhost:8000
   * En prod : API_URL pointe vers le service interne Docker (http://symfony)
   * La variable est lue côté serveur — pas de NEXT_PUBLIC_ nécessaire.
   */
  async rewrites() {
    const apiUrl = process.env.API_URL ?? 'http://localhost:8000'
    return [
      {
        source: '/api/:path*',
        destination: `${apiUrl}/api/:path*`,
      },
    ]
  },

  // Standalone output pour Docker (copie uniquement les fichiers nécessaires)
  output: 'standalone',

  // En production, désactiver les en-têtes X-Powered-By
  poweredByHeader: false,

  // Compression des assets
  compress: true,
}

module.exports = nextConfig
