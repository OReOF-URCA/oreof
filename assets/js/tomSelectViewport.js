/**
 * Menus Tom Select : tiennent toujours dans la fenêtre.
 * Sur un petit écran (ou dans une modale), le menu est réduit à la place disponible et s'ouvre vers le haut
 * si la place manque en dessous. À brancher une fois sur l'évènement `autocomplete:connect` (symfony/ux-autocomplete).
 */
const MARGE = 12 // px laissés entre le menu et le bord de la fenêtre
const HAUTEUR_MAX = 208 // 13rem, cf. `.ts-dropdown-content` dans app.css

function ajuster (ts) {
  const { dropdown, dropdown_content: contenu, control } = ts
  if (!dropdown || !contenu || !control) return

  const rect = control.getBoundingClientRect()
  const dessous = window.innerHeight - rect.bottom - MARGE
  const dessus = rect.top - MARGE
  const voulu = Math.min(contenu.scrollHeight, HAUTEUR_MAX)
  const versLeHaut = dessous < voulu && dessus > dessous
  const place = Math.max(versLeHaut ? dessus : dessous, 0)

  contenu.style.maxHeight = `${Math.min(HAUTEUR_MAX, place)}px`

  const dansBody = dropdown.parentElement === document.body
  if (dansBody) {
    // positionDropdown() de Tom Select a placé le menu sous le champ : on le remonte si besoin
    if (versLeHaut) {
      const haut = rect.top + window.scrollY - dropdown.getBoundingClientRect().height - 4
      dropdown.style.top = `${Math.max(haut, window.scrollY)}px`
    }
    return
  }
  dropdown.style.top = versLeHaut ? 'auto' : ''
  dropdown.style.bottom = versLeHaut ? '100%' : ''
  dropdown.style.marginTop = versLeHaut ? '0' : ''
  dropdown.style.marginBottom = versLeHaut ? '0.25rem' : ''
}

// Dans une modale (corps défilant), le menu est rattaché au <body> pour ne pas être coupé
document.addEventListener('autocomplete:pre-connect', (event) => {
  if (event.target.closest?.('.app-modal-body, .modal')) {
    event.detail.options.dropdownParent = 'body'
  }
})

document.addEventListener('autocomplete:connect', (event) => {
  const ts = event.detail?.tomSelect
  if (!ts || ts.viewportFit) return
  ts.viewportFit = true

  // positionDropdown() est appelé à l'ouverture, au filtrage et au redimensionnement
  const positionner = ts.positionDropdown.bind(ts)
  ts.positionDropdown = () => {
    positionner()
    ajuster(ts)
  }
})
