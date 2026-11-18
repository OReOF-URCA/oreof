import { Controller } from '@hotwired/stimulus'

/*
 * Assistant IA d'une demande d'assistance : résumé de l'échange et brouillon de réponse.
 * Le brouillon remplit l'éditeur Trix du formulaire « Répondre » : rien n'est envoyé sans l'admin.
 * Un second clic régénère le résultat (paramètre `forcer`), sinon le serveur répond depuis son cache.
 */
export default class extends Controller {
  static targets = ['statut', 'resume']
  static values = { resumeUrl: String, brouillonUrl: String, jeton: String }

  disconnect () {
    this.abort?.abort()
  }

  async resumer () {
    const resultat = await this.demander(this.resumeUrlValue, 'Résumé en cours…', this.dejaResume)
    if (!resultat) return

    this.dejaResume = true
    this.resumeTarget.textContent = resultat.texte
    this.resumeTarget.classList.remove('hidden')
  }

  async brouillon () {
    const editeur = document.querySelector('#assistance-reponse trix-editor')
    if (!editeur) return
    if (editeur.editor.getDocument().toString().trim() !== '' &&
      !window.confirm('Remplacer le message en cours de rédaction par la proposition de l\'IA ?')) return

    const resultat = await this.demander(this.brouillonUrlValue, 'Rédaction de la proposition…', this.dejaBrouillon)
    if (!resultat) return

    this.dejaBrouillon = true
    editeur.editor.loadHTML(resultat.html)
    editeur.scrollIntoView({ behavior: 'smooth', block: 'center' })
    editeur.focus()
  }

  async demander (url, attente, forcer) {
    this.abort?.abort()
    this.abort = new AbortController()
    this.afficher(attente)
    this.element.querySelectorAll('button').forEach(b => { b.disabled = true })

    const donnees = new FormData()
    donnees.append('_token', this.jetonValue)
    if (forcer) donnees.append('forcer', '1')

    try {
      const reponse = await fetch(url, {
        method: 'POST',
        body: donnees,
        headers: { Accept: 'application/json' },
        signal: this.abort.signal
      })
      const resultat = await reponse.json()
      if (!reponse.ok) {
        this.afficher(resultat.erreur ?? 'IA indisponible.')
        return null
      }
      this.afficher('')

      return resultat
    } catch (e) {
      if (e.name !== 'AbortError') this.afficher('IA indisponible.')

      return null
    } finally {
      this.element.querySelectorAll('button').forEach(b => { b.disabled = false })
    }
  }

  afficher (texte) {
    this.statutTarget.textContent = texte
    this.statutTarget.hidden = texte === ''
  }
}
