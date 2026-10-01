/*
 * Tab controller for client-side tab switching with .app-tabs styling
 */
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['tab', 'panel']
  static values = {
    activeTab: String,
    activeTabClass: { type: String, default: 'app-tab-active active' },
    rememberHash: { type: Boolean, default: true }
  }

  connect() {
    let initialTab = null

    if (this.rememberHashValue && window.location.hash) {
      const hashTab = window.location.hash.replace('#', '')
      if (this.hasTab(hashTab)) {
        initialTab = hashTab
      }
    }

    if (!initialTab && this.tabTargets.length > 0) {
      initialTab = this.tabTargets[0].dataset.tabId
    }

    if (initialTab) {
      this.activate(initialTab, false)
    }
  }

  hasTab(tabId) {
    return this.tabTargets.some(t => t.dataset.tabId === tabId)
  }

  change(event) {
    event.preventDefault()
    const tabId = event.currentTarget.dataset.tabId
    if (tabId) {
      this.activate(tabId, true)
    }
  }

  activate(tabId, updateHash = true) {
    this.activeTabValue = tabId

    const activeClasses = this.activeTabClassValue.split(' ').filter(Boolean)

    this.tabTargets.forEach(tab => {
      const isActive = tab.dataset.tabId === tabId
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive) {
        activeClasses.forEach(cls => tab.classList.add(cls))
      } else {
        activeClasses.forEach(cls => tab.classList.remove(cls))
      }
    })

    this.panelTargets.forEach(panel => {
      const isMatch = panel.dataset.panelId === tabId
      if (isMatch) {
        panel.classList.remove('hidden')
      } else {
        panel.classList.add('hidden')
      }
    })

    if (updateHash && this.rememberHashValue) {
      if (window.history.replaceState) {
        window.history.replaceState(null, '', `#${tabId}`)
      } else {
        window.location.hash = tabId
      }
    }
  }

  toggleDetails(event) {
    // Find either the active panel or all panels in this tabs container
    const activePanel = this.panelTargets.find(p => !p.classList.contains('hidden'))
    const targetElement = activePanel || this.element

    const detailsElements = targetElement.querySelectorAll('details')
    if (detailsElements.length === 0) return

    // Check if at least one is closed
    const hasClosed = Array.from(detailsElements).some(d => !d.open)
    const shouldOpen = hasClosed

    detailsElements.forEach(d => {
      d.open = shouldOpen
    })

    const labelSpan = event.currentTarget.querySelector('[data-toggle-label]')
    if (labelSpan) {
      labelSpan.textContent = shouldOpen ? 'Tout replier' : 'Tout déplier'
    }
    const icon = event.currentTarget.querySelector('[data-toggle-icon]')
    if (icon) {
      icon.style.transform = shouldOpen ? 'rotate(180deg)' : 'rotate(0deg)'
    }
  }
}
