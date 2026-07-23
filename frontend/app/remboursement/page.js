import LegalPage from '@/components/LegalPage'

export const metadata = { title: 'Politique de remboursement BusGo' }

export default function RemboursementPage() {
  return (
    <LegalPage
      title="Politique de remboursement"
      sections={[
        {
          title: 'Annulation par le voyageur',
          body: "Annulation plus de 24 h avant le départ : remboursement à 100 %.\nEntre 24 h et 2 h avant le départ : remboursement à 50 %.\nMoins de 2 h avant le départ : billet non remboursable.\nAvec l'assurance annulation : remboursement à 100 % jusqu'à 2 h avant le départ.",
        },
        {
          title: 'Annulation par BusGo',
          body: "En cas d'annulation du trajet par BusGo ou le transporteur, le billet est intégralement remboursé, extras compris, sans démarche de votre part.",
        },
        {
          title: 'Délais de remboursement',
          body: "Le remboursement est effectué sur le moyen de paiement d'origine sous 5 à 10 jours ouvrés selon votre banque.",
        },
        {
          title: 'Comment demander un remboursement',
          body: "Depuis « Mes réservations », sélectionnez le billet concerné puis « Annuler ». Pour toute difficulté, contactez le support via la page Aide.",
        },
      ]}
    />
  )
}
