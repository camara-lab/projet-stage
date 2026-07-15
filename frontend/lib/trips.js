/**
 * Retourne le prix de base d'un trajet.
 * Accepte un objet trip qui expose soit prixBase (API Symfony directe)
 * soit route?.basePrice (schéma imbriqué via booking.trip).
 */
export function getBasePrice(trip) {
  return parseFloat(trip?.prixBase ?? trip?.route?.basePrice ?? 0) || 0
}
