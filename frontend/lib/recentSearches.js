const KEY = 'busgo_recent_searches'
const MAX = 5

export function getRecentSearches() {
  if (typeof window === 'undefined') return []
  try {
    return JSON.parse(localStorage.getItem(KEY)) || []
  } catch {
    return []
  }
}

export function saveRecentSearch({ from, to, date }) {
  if (!from || !to) return
  const list = getRecentSearches().filter(
    (s) => !(s.from === from && s.to === to)
  )
  list.unshift({ from, to, date })
  localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)))
}

export function removeRecentSearch(index) {
  const list = getRecentSearches()
  list.splice(index, 1)
  localStorage.setItem(KEY, JSON.stringify(list))
  return list
}
