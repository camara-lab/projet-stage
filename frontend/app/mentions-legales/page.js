import LegalPage from '@/components/LegalPage'

export const metadata = { title: 'Mentions légales BusGo' }

export default function MentionsLegalesPage() {
  return (
    <LegalPage
      title="Mentions légales"
      sections={[
        {
          title: 'Éditeur du site',
          body: "BusGo, plateforme de réservation de billets de bus inter-villes.\nProjet développé dans le cadre d'un stage de fin d'études (PFE).\nContact : contact@busgo.ma",
        },
        {
          title: 'Hébergement',
          body: "Application hébergée sur une infrastructure Docker (Nginx, PHP-FPM, MySQL).",
        },
        {
          title: 'Propriété intellectuelle',
          body: "L'ensemble des éléments du site (textes, logos, interface) est protégé. Toute reproduction sans autorisation est interdite.",
        },
        {
          title: 'Données personnelles',
          body: "Le traitement des données personnelles est décrit dans la Politique de confidentialité, conformément à la loi 09-08.",
        },
      ]}
    />
  )
}
