import { Controller } from '@hotwired/stimulus'
import { renderStreamMessage } from '@hotwired/turbo'

export default class extends Controller {
  static targets = ['sigle', 'loader']
  static values = {
    url: String
  }

  async refresh () {
    const sigle = this.hasSigleTarget ? this.sigleTarget.value : ''
    const anneeInput = this.element.querySelector('input[name="annee"]:checked')
    const annee = anneeInput ? anneeInput.value : '2021'

    if (this.hasLoaderTarget) {
      this.loaderTarget.classList.remove('hidden')
    }

    const url = new URL(this.urlValue, window.location.origin)
    if (sigle) {
      url.searchParams.set('sigle', sigle)
    }
    url.searchParams.set('annee', annee)

    try {
      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'text/vnd.turbo-stream.html',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      if (response.ok) {
        renderStreamMessage(await response.text())
      }
    } catch (e) {
      console.error('Erreur rafraîchissement modal ORéBUT:', e)
    } finally {
      if (this.hasLoaderTarget) {
        this.loaderTarget.classList.add('hidden')
      }
    }
  }

  toggleAllParcours (event) {
    const checked = event.currentTarget.checked
    const checkboxes = this.element.querySelectorAll('input[name="parcours_codes[]"]')
    checkboxes.forEach(cb => {
      cb.checked = checked
    })
  }
}
