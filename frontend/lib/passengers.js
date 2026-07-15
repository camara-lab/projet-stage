/**
 * Lit adults/children/babies depuis les URL searchParams et retourne des entiers normalisés.
 * adults >= 1, children >= 0, babies >= 0.
 */
export function normalizePassengers(searchParams) {
  return {
    adults:   Math.max(1, parseInt(searchParams.get('adults')   || '1', 10)),
    children: Math.max(0, parseInt(searchParams.get('children') || '0', 10)),
    babies:   Math.max(0, parseInt(searchParams.get('babies')   || '0', 10)),
  }
}
