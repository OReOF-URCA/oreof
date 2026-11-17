import { Controller } from '@hotwired/stimulus'

// Aperçu d'un document dans une modale (composant Twig DocumentPreview).
// Le média n'est créé qu'à l'ouverture et détruit à la fermeture (arrête la lecture vidéo/audio).
export default class extends Controller {
  static targets = ['modal', 'body']
  static values = { url: String, type: String, previewable: Boolean }

  open (event) {
    event.preventDefault()
    this.render()
    this.modalTarget.classList.remove('hidden')
    document.documentElement.classList.add('overflow-hidden')
  }

  close (event) {
    if (event) event.preventDefault()
    this.bodyTarget.replaceChildren()
    this.modalTarget.classList.add('hidden')
    document.documentElement.classList.remove('overflow-hidden')
  }

  disconnect () {
    document.documentElement.classList.remove('overflow-hidden')
  }

  render () {
    if (this.typeValue === 'word' && this.isDocx()) {
      this.renderDocx()
      return
    }
    if (this.typeValue === 'excel' && this.isXlsx()) {
      this.renderXlsx()
      return
    }
    this.bodyTarget.replaceChildren(this.build())
  }

  isXlsx () {
    return /\.xlsx(\?|#|$)/i.test(this.urlValue)
  }

  message (text) {
    const p = document.createElement('p')
    p.className = 'p-8 text-center text-sm text-secondary-600 dark:text-secondary-300'
    p.textContent = text
    return p
  }

  // Rendu Excel (.xlsx) côté navigateur : onglets par feuille + tableau (lib chargée à la demande).
  async renderXlsx () {
    this.bodyTarget.replaceChildren(this.message('Chargement de l\'aperçu…'))

    try {
      const [{ default: readExcelFile }, response] = await Promise.all([import('read-excel-file/browser'), fetch(this.urlValue)])
      if (!response.ok) throw new Error(response.statusText)
      const sheets = await readExcelFile(await response.blob())
      // La modale a pu être fermée pendant le chargement.
      if (this.modalTarget.classList.contains('hidden')) return
      if (!sheets.length) throw new Error('empty')

      const wrapper = document.createElement('div')
      const tabs = document.createElement('div')
      tabs.className = 'flex gap-1 overflow-x-auto border-b border-secondary-200 bg-surface px-4 pt-2 dark:border-secondary-700'
      const table = document.createElement('div')
      table.className = 'overflow-auto p-4'
      wrapper.append(tabs, table)

      const buttons = sheets.map((sheet, index) => {
        const btn = document.createElement('button')
        btn.type = 'button'
        btn.textContent = sheet.sheet
        btn.addEventListener('click', () => select(index))
        if (sheets.length > 1) tabs.append(btn)
        return btn
      })

      const select = (index) => {
        buttons.forEach((btn, i) => {
          btn.className = 'whitespace-nowrap rounded-t-md px-3 py-1.5 text-sm font-medium ' + (i === index
            ? 'border border-b-0 border-secondary-200 bg-secondary-50 text-secondary-900 dark:border-secondary-700 dark:bg-secondary-900 dark:text-secondary-100'
            : 'text-secondary-500 hover:text-secondary-900 dark:text-secondary-400 dark:hover:text-secondary-100')
        })
        table.replaceChildren(this.buildTable(sheets[index].data))
      }

      select(0)
      this.bodyTarget.replaceChildren(wrapper)
    } catch {
      if (!this.modalTarget.classList.contains('hidden')) this.bodyTarget.replaceChildren(this.fallback())
    }
  }

  buildTable (rows) {
    const maxRows = 500
    const container = document.createElement('div')
    if (!rows.length) {
      container.append(this.message('Cette feuille est vide.'))
      return container
    }

    const table = document.createElement('table')
    table.className = 'min-w-full border-collapse text-sm'
    const tbody = document.createElement('tbody')
    rows.slice(0, maxRows).forEach((row) => {
      const tr = document.createElement('tr')
      row.forEach((value) => {
        const td = document.createElement('td')
        td.className = 'border border-secondary-200 px-2 py-1 align-top text-secondary-800 dark:border-secondary-700 dark:text-secondary-200'
        td.textContent = value instanceof Date ? value.toLocaleDateString('fr-FR') : (value ?? '')
        tr.append(td)
      })
      tbody.append(tr)
    })
    table.append(tbody)
    container.append(table)

    if (rows.length > maxRows) {
      const note = this.message(`Aperçu limité aux ${maxRows} premières lignes sur ${rows.length}. Téléchargez le fichier pour tout voir.`)
      note.className = 'pt-4 text-center text-xs text-secondary-500'
      container.append(note)
    }
    return container
  }

  isDocx () {
    return /\.docx(\?|#|$)/i.test(this.urlValue)
  }

  // Rendu Word côté navigateur (lib chargée à la demande, uniquement pour .docx).
  async renderDocx () {
    this.bodyTarget.replaceChildren(this.message('Chargement de l\'aperçu…'))

    try {
      const [{ renderAsync }, response] = await Promise.all([import('docx-preview'), fetch(this.urlValue)])
      if (!response.ok) throw new Error(response.statusText)
      const blob = await response.blob()
      const container = document.createElement('div')
      container.className = 'docx-preview-host p-4'
      // La modale a pu être fermée pendant le chargement.
      if (this.modalTarget.classList.contains('hidden')) return
      this.bodyTarget.replaceChildren(container)
      await renderAsync(blob, container, undefined, { inWrapper: true, ignoreWidth: false })
    } catch {
      if (!this.modalTarget.classList.contains('hidden')) this.bodyTarget.replaceChildren(this.fallback())
    }
  }

  build () {
    const url = this.urlValue
    if (!this.previewableValue) return this.fallback()

    let el
    switch (this.typeValue) {
      case 'pdf':
        el = document.createElement('iframe')
        el.src = url
        el.title = 'Aperçu du document'
        el.className = 'h-[75vh] w-full border-0'
        return el
      case 'video':
        el = document.createElement('video')
        el.src = url
        el.controls = true
        el.className = 'mx-auto max-h-[75vh] w-full'
        return el
      case 'audio':
        el = document.createElement('audio')
        el.src = url
        el.controls = true
        el.className = 'my-8 w-full px-6'
        return el
      case 'image':
        el = document.createElement('img')
        el.src = url
        el.alt = ''
        el.className = 'mx-auto max-h-[75vh] object-contain'
        return el
      default:
        return this.fallback()
    }
  }

  fallback () {
    return this.message('Ce format ne peut pas être affiché dans le navigateur. Utilisez « Télécharger » pour l\'ouvrir.')
  }
}
