import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['row', 'section', 'filterBtn', 'emptyState', 'countInfo', 'tableContainer']
  static values = {
    current: { type: String, default: 'all' },
  }

  connect () {
    this.updateView()
  }

  filter (event) {
    const type = event.currentTarget.dataset.filterType || 'all'
    this.currentValue = type
    this.updateView()
  }

  updateView () {
    const isIssuesOnly = this.currentValue === 'issues'
    let visibleIssuesCount = 0

    // Filter rows
    this.rowTargets.forEach(row => {
      const state = row.dataset.state || ''
      const isIssue = state !== 'complet' && state !== 'non_concerne'

      if (isIssuesOnly) {
        if (isIssue) {
          row.classList.remove('hidden')
          visibleIssuesCount++
        } else {
          row.classList.add('hidden')
        }
      } else {
        row.classList.remove('hidden')
      }
    })

    // Handle section visibility
    this.sectionTargets.forEach(section => {
      if (!isIssuesOnly) {
        section.classList.remove('hidden')
        return
      }

      // Check whether this section has any visible sibling rows before the next section
      let hasVisibleChild = false
      let sibling = section.nextElementSibling

      while (sibling && !sibling.matches('[data-parcours-state-filter-target="section"]')) {
        if (!sibling.classList.contains('hidden')) {
          hasVisibleChild = true
          break
        }
        sibling = sibling.nextElementSibling
      }

      if (hasVisibleChild) {
        section.classList.remove('hidden')
      } else {
        section.classList.add('hidden')
      }
    })

    // Update empty state
    if (this.hasEmptyStateTarget) {
      if (isIssuesOnly && visibleIssuesCount === 0) {
        this.emptyStateTarget.classList.remove('hidden')
        if (this.hasTableContainerTarget) {
          this.tableContainerTarget.classList.add('hidden')
        }
      } else {
        this.emptyStateTarget.classList.add('hidden')
        if (this.hasTableContainerTarget) {
          this.tableContainerTarget.classList.remove('hidden')
        }
      }
    }

    // Update filter buttons styling
    this.filterBtnTargets.forEach(btn => {
      const type = btn.dataset.filterType || 'all'
      const isActive = type === this.currentValue

      if (isActive) {
        btn.classList.add('bg-primary-50', 'text-primary-700', 'dark:bg-primary-900/40', 'dark:text-primary-300', 'shadow-xs')
        btn.classList.remove('text-secondary-600', 'hover:text-secondary-900', 'dark:text-secondary-400', 'dark:hover:text-secondary-100')
      } else {
        btn.classList.remove('bg-primary-50', 'text-primary-700', 'dark:bg-primary-900/40', 'dark:text-primary-300', 'shadow-xs')
        btn.classList.add('text-secondary-600', 'hover:text-secondary-900', 'dark:text-secondary-400', 'dark:hover:text-secondary-100')
      }
    })

    // Update count info
    if (this.hasCountInfoTarget) {
      if (isIssuesOnly) {
        this.countInfoTarget.textContent = visibleIssuesCount === 0
          ? 'Aucun point d\'attention trouvé'
          : `${visibleIssuesCount} point${visibleIssuesCount > 1 ? 's' : ''} d'attention affiché${visibleIssuesCount > 1 ? 's' : ''}`
      } else {
        this.countInfoTarget.textContent = 'Affichage de tous les critères'
      }
    }
  }
}
