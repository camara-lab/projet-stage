import LegalPage from '@/components/LegalPage'

export const metadata = { title: 'Conditions Générales de Vente BusGo' }

export default function CgvPage() {
  return (
    <LegalPage
      title="Conditions Générales de Vente"
      sections={[
        {
          title: 'Objet',
          body: "Les présentes Conditions Générales de Vente régissent la vente de billets de bus inter-villes sur la plateforme BusGo. Toute réservation implique l'acceptation pleine et entière de ces conditions.",
        },
        {
          title: 'Réservation et billets',
          body: "La réservation est confirmée après validation du paiement. Un billet électronique est émis pour chaque passager et reste disponible dans l'espace « Mes réservations ». Le billet est nominatif : les informations saisies pour chaque passager doivent correspondre à une pièce d'identité valide présentée à l'embarquement.",
        },
        {
          title: 'Prix et paiement',
          body: "Les prix sont exprimés en dirhams (DH), toutes taxes comprises. Le tarif enfant (moins de 12 ans) bénéficie d'une réduction de 25 %. Les bébés de moins de 3 ans voyagent gratuitement sur les genoux d'un adulte. Le paiement s'effectue par carte bancaire, espèces (agences partenaires), portefeuille mobile ou virement.",
        },
        {
          title: 'Annulation et modification',
          body: "L'annulation est possible jusqu'à 2 heures avant le départ, selon les conditions de la Politique de remboursement. Les trajets annulés par BusGo sont intégralement remboursés.",
        },
        {
          title: 'Bagages',
          body: "Chaque passager dispose d'un bagage en soute et d'un bagage cabine inclus. Les bagages supplémentaires ou volumineux font l'objet d'un supplément indiqué lors de la réservation.",
        },
        {
          title: 'Responsabilité',
          body: "BusGo agit en qualité de plateforme de réservation. Les horaires sont donnés à titre indicatif et peuvent varier selon les conditions de circulation.",
        },
      ]}
    />
  )
}
