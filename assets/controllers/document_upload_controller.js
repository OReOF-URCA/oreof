import { Controller } from '@hotwired/stimulus'

// Bouton « Téléverser » : ouvre le sélecteur de fichier puis envoie le formulaire dès le choix.
export default class extends Controller {
  static targets = ['input']

  pick (event) {
    event.preventDefault()
    this.inputTarget.click()
  }

  submit () {
    if (this.inputTarget.files.length > 0) this.element.requestSubmit()
  }
}
