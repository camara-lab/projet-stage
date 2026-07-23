import LegalPage from '@/components/LegalPage'

export const metadata = { title: 'Politique de confidentialité BusGo' }

export default function ConfidentialitePage() {
  return (
    <LegalPage
      title="Politique de confidentialité"
      sections={[
        {
          title: 'Données collectées',
          body: "Dans le cadre de votre réservation, nous collectons : nom, prénom, adresse e-mail, date de naissance, numéro de pièce d'identité des passagers et informations de contact. Ces données sont indispensables à l'émission des billets et au contrôle à l'embarquement.",
        },
        {
          title: 'Finalité du traitement',
          body: "Vos données personnelles sont utilisées uniquement pour gérer votre réservation : émission des billets, notifications liées au voyage, assistance client et obligations légales de transport. Elles ne sont jamais vendues à des tiers.",
        },
        {
          title: 'Conservation',
          body: "Les données de réservation sont conservées pendant la durée nécessaire à la gestion du voyage et aux obligations comptables légales. Les recherches récentes sont stockées uniquement sur votre appareil et peuvent être supprimées à tout moment.",
        },
        {
          title: 'Sécurité',
          body: "Les échanges avec la plateforme sont chiffrés (SSL). Les mots de passe sont stockés de manière sécurisée (hachage) et les données bancaires ne sont jamais conservées par BusGo.",
        },
        {
          title: 'Vos droits',
          body: "Conformément à la loi 09-08 relative à la protection des données personnelles au Maroc, vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Pour l'exercer, contactez-nous à contact@busgo.ma.",
        },
      ]}
    />
  )
}
