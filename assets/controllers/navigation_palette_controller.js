import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['modal', 'input', 'results']

  connect () {
    // this.shortcutLabel = navigator.platform.toUpperCase().includes('MAC')
    //   ? '⌘K'
    //   : 'Ctrl K'
    this.selectedIndex = 0
    this.results = []
    this.handleGlobalKeydown = this.handleGlobalKeydown.bind(this)
    document.addEventListener('keydown', this.handleGlobalKeydown)
  }

  disconnect () {
    document.removeEventListener('keydown', this.handleGlobalKeydown)
  }

  handleGlobalKeydown (event) {
    const target = event.target
    const isTyping = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target.isContentEditable

    const shortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'

    if (shortcut) {
      event.preventDefault()
      this.open()
      return
    }

    if (this.modalTarget.classList.contains('hidden')) {
      return
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      this.close()
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      this.moveSelection(1)
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      this.moveSelection(-1)
    }

    if (event.key === 'Enter') {
      event.preventDefault()
      this.goToSelected()
    }
  }

  open () {
    this.modalTarget.classList.remove('hidden')
    this.inputTarget.focus()
    this.inputTarget.select()
  }

  close () {
    this.modalTarget.classList.add('hidden')
    this.inputTarget.value = ''
    this.resultsTarget.innerHTML = ''
    this.results = []
    this.selectedIndex = 0
  }

  async search () {
    const query = this.inputTarget.value.trim()

    if (query.length < 2) {
      this.results = []
      this.resultsTarget.innerHTML = this.emptyState()
      return
    }

    const response = await fetch(`/navigation/search?q=${encodeURIComponent(query)}`)
    this.results = await response.json()
    this.selectedIndex = 0
    this.renderResults()
  }

  moveSelection (direction) {
    if (this.results.length === 0) {
      return
    }

    this.selectedIndex += direction

    if (this.selectedIndex < 0) {
      this.selectedIndex = this.results.length - 1
    }

    if (this.selectedIndex >= this.results.length) {
      this.selectedIndex = 0
    }

    this.renderResults()
  }

  goToSelected () {
    const item = this.results[this.selectedIndex]

    if (item?.url) {
      window.location.href = item.url
    }
  }

  renderResults () {
    if (this.results.length === 0) {
      this.resultsTarget.innerHTML = this.emptyState()
      return
    }

    this.resultsTarget.innerHTML = this.results.map((item, index) => {
      const active = index === this.selectedIndex

      return `
        <a href="${this.escapeHtml(item.url)}"
           class="flex items-start gap-3 px-4 py-3 border-b border-secondary-100 last:border-b-0
                  ${active ? 'bg-primary-50 dark:bg-primary-900/30' : 'hover:bg-secondary-50 dark:hover:bg-secondary-800'}">
          <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary-100 dark:bg-secondary-800">
            <span class="text-sm">⌘</span>
          </div>

          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-semibold text-secondary-900 dark:text-secondary-100">
              ${this.escapeHtml(item.label)}
            </div>
            <div class="truncate text-xs text-secondary-500 dark:text-secondary-400">
              ${this.escapeHtml(item.path)}
            </div>
          </div>

          ${active ? '<div class="text-xs text-secondary-400">Entrée</div>' : ''}
        </a>
      `
    }).join('')
  }

  emptyState () {
    return `
      <div class="px-4 py-8 text-center text-sm text-secondary-500">
        Tapez au moins 2 caractères pour rechercher une page.
      </div>
    `
  }

  escapeHtml (value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll('\'', '&#039;')
  }
}
