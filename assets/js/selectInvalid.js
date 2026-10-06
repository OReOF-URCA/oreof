/**
 * Marque un <select> en erreur. Avec Tom Select le <select> est masqué : la classe va sur le wrapper.
 * @param {string} id
 * @param {boolean} invalid
 */
export default function selectInvalid (id, invalid) {
  const select = document.getElementById(id)
  const target = select?.tomselect?.wrapper ?? select
  target?.classList.toggle('is-invalid', invalid)
}
