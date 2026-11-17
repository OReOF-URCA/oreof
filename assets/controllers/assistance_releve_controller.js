import { Controller } from '@hotwired/stimulus'
import { visit } from '@hotwired/turbo'

/*
 * Relève la boîte d'assistance en arrière-plan à l'ouverture de la liste des demandes.
 * Posé sur le formulaire « Relever maintenant » (réutilise son jeton CSRF).
 * La page s'affiche sans attendre ; si de nouveaux mails arrivent, la liste est rechargée.
 * Le serveur espace les relèves (une par minute au plus), recharger la page ne relance donc pas IMAP.
 */
export default class extends Controller {
  static targets = ['statut']
  static values = { url: String }

  connect () {
    // un rendu d'aperçu Turbo (cache) ne doit pas déclencher de relève
    if (document.documentElement.hasAttribute('data-turbo-preview')) return

    this.abort = new AbortController()
    this.relever()
  }

  disconnect () {
    this.abort?.abort()
  }

  async relever () {
    this.afficher('Relève des mails…')

    try {
      const reponse = await fetch(this.urlValue, {
        method: 'POST',
        body: new FormData(this.element),
        headers: { Accept: 'application/json' },
        signal: this.abort.signal
      })
      const { nouveaux } = await reponse.json()

      if (nouveaux > 0) {
        this.afficher(`${nouveaux} nouveau(x) mail(s)`)
        visit(window.location.href, { action: 'replace' })
        return
      }
    } catch (e) {
      if (e.name === 'AbortError') return
    }

    this.afficher('')
  }

  afficher (texte) {
    if (!this.hasStatutTarget) return
    this.statutTarget.textContent = texte
    this.statutTarget.hidden = texte === ''
  }
}
