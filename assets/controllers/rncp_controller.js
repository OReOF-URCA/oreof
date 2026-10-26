import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'input',
    'badge',
    'card',
    'title',
    'meta',
    'link',
    'loader',
    'radioOui',
    'radioNon',
    'container',
  ]

  static values = {
    verifyUrl: String,
    searchUrl: String,
    parcoursId: String,
    formationId: String,
    etablissementId: String,
  }

  connect () {
    this.debounceTimer = null
    this.latestQuery = ''

    // Init de l'état InRncp si présent
    this.syncInRncpState()

    // Vérification initiale si un code existe déjà
    if (this.hasInputTarget && this.inputTarget.value.trim() !== '') {
      this.verify(this.inputTarget.value.trim())
    }
  }

  /**
   * Normalise le code (ex: 34436 -> RNCP34436)
   */
  normalizeCode (val) {
    if (!val) return ''
    let cleaned = val.replace(/\s+/g, '').toUpperCase()
    if (/^\d{3,5}$/.test(cleaned)) {
      cleaned = 'RNCP' + cleaned
    }
    return cleaned
  }

  onInput (event) {
    clearTimeout(this.debounceTimer)
    const rawVal = event.target.value.trim()

    if (rawVal === '') {
      this.hideCard()
      this.hideBadge()
      return
    }

    this.debounceTimer = setTimeout(() => {
      this.verify(rawVal)
    }, 400)
  }

  onChange (event) {
    const rawVal = event.target.value.trim()
    const normalized = this.normalizeCode(rawVal)
    if (normalized !== rawVal) {
      this.inputTarget.value = normalized
    }

    if (normalized !== '') {
      this.verify(normalized)
    } else {
      this.hideCard()
      this.hideBadge()
    }
  }

  async verify (code) {
    const normalized = this.normalizeCode(code)
    if (!normalized || !this.hasVerifyUrlValue) {
      return
    }

    this.latestQuery = normalized
    this.showLoader(true)

    let url = `${this.verifyUrlValue}?code=${encodeURIComponent(normalized)}`
    if (this.hasParcoursIdValue && this.parcoursIdValue) {
      url += `&parcours_id=${encodeURIComponent(this.parcoursIdValue)}`
    }
    if (this.hasFormationIdValue && this.formationIdValue) {
      url += `&formation_id=${encodeURIComponent(this.formationIdValue)}`
    }
    if (this.hasEtablissementIdValue && this.etablissementIdValue) {
      url += `&etablissement_id=${encodeURIComponent(this.etablissementIdValue)}`
    }

    try {
      const resp = await fetch(url, {
        headers: { Accept: 'application/json' },
      })

      if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}`)
      }

      const data = await resp.json()

      // Si l'utilisateur a tapé autre chose entre-temps
      if (this.latestQuery !== normalized) {
        return
      }

      this.renderVerification(data)
    } catch (err) {
      console.warn('[RNCP] Erreur vérification :', err)
      this.renderFallback(normalized)
    } finally {
      this.showLoader(false)
    }
  }

  renderVerification (data) {
    // 1. Badge d'état
    if (this.hasBadgeTarget) {
      this.badgeTarget.classList.remove('hidden')
      if (data.actif === true) {
        this.badgeTarget.className = 'inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300'
        this.badgeTarget.innerHTML = `
          <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
          <span>Actif</span>
        `
      } else if (data.actif === false) {
        this.badgeTarget.className = 'inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300'
        this.badgeTarget.innerHTML = `
          <svg class="w-3 h-3 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
          <span>Échu</span>
        `
      } else if (data.valid) {
        this.badgeTarget.className = 'inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/40 dark:text-primary-300'
        const badgeLabel = data.connected === false ? 'Format valide (hors-ligne)' : 'Format valide'
        this.badgeTarget.innerHTML = `
          <svg class="w-3 h-3 text-primary-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.5a.75.75 0 001.5 0v-4.5zm0 7.5a.75.75 0 10-1.5 0 .75.75 0 001.5 0z" clip-rule="evenodd"/></svg>
          <span>${badgeLabel}</span>
        `
      } else {
        this.badgeTarget.className = 'inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300'
        this.badgeTarget.innerHTML = `
          <svg class="w-3 h-3 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
          <span>Non reconnu</span>
        `
      }
    }

    // 2. Fiche d'information
    if (this.hasCardTarget) {
      if (data.intitule || data.valid) {
        this.cardTarget.classList.remove('hidden')
        if (this.hasTitleTarget) {
          this.titleTarget.textContent = data.intitule || `Certification ${data.code}`
        }

        if (this.hasMetaTarget) {
          const parts = []
          if (data.niveau) parts.push(`Niveau : ${data.niveau}`)
          if (data.certificateur) parts.push(`Certificateur : ${data.certificateur}`)
          if (data.dateFinValidite) parts.push(`Fin de validité : ${data.dateFinValidite}`)

          if (parts.length > 0) {
            this.metaTarget.textContent = parts.join(' • ')
          } else if (data.message) {
            this.metaTarget.textContent = data.message
          } else {
            this.metaTarget.textContent = ''
          }
        }

        if (this.hasLinkTarget && data.url) {
          this.linkTarget.href = data.url
          this.linkTarget.classList.remove('hidden')
        }
      } else {
        this.hideCard()
      }
    }
  }

  renderFallback (code) {
    const num = code.replace(/^RNCP/i, '')
    const url = `https://www.francecompetences.fr/recherche/rncp/${num}`

    if (this.hasBadgeTarget) {
      this.badgeTarget.classList.remove('hidden')
      this.badgeTarget.className = 'inline-flex items-center gap-1 rounded-full bg-secondary-100 px-2 py-0.5 text-[11px] font-semibold text-secondary-700 dark:bg-secondary-800 dark:text-secondary-300'
      this.badgeTarget.textContent = 'Code saisi'
    }

    if (this.hasCardTarget) {
      this.cardTarget.classList.remove('hidden')
      if (this.hasTitleTarget) this.titleTarget.textContent = `Fiche ${code}`
      if (this.hasMetaTarget) this.metaTarget.textContent = 'Vérification auprès de France Compétences'
      if (this.hasLinkTarget) {
        this.linkTarget.href = url
        this.linkTarget.classList.remove('hidden')
      }
    }
  }

  showLoader (show) {
    if (this.hasLoaderTarget) {
      if (show) {
        this.loaderTarget.classList.remove('hidden')
      } else {
        this.loaderTarget.classList.add('hidden')
      }
    }
  }

  hideCard () {
    if (this.hasCardTarget) {
      this.cardTarget.classList.add('hidden')
    }
  }

  hideBadge () {
    if (this.hasBadgeTarget) {
      this.badgeTarget.classList.add('hidden')
    }
  }

  onInRncpChange (event) {
    const isOui = event.target.value === '1' || event.target.value === 'true'
    this.setRncpEnabled(isOui)
  }

  syncInRncpState () {
    const radioOui = this.element.querySelector('input[type="radio"][value="1"]') || this.element.querySelector('input[type="radio"][value="true"]')
    const radioNon = this.element.querySelector('input[type="radio"][value="0"]') || this.element.querySelector('input[type="radio"][value="false"]')

    if (radioNon && radioNon.checked) {
      this.setRncpEnabled(false)
    } else if (radioOui && radioOui.checked) {
      this.setRncpEnabled(true)
    }
  }

  setRncpEnabled (enabled) {
    if (this.hasContainerTarget) {
      if (enabled) {
        this.containerTarget.classList.remove('opacity-40', 'pointer-events-none')
      } else {
        this.containerTarget.classList.add('opacity-40', 'pointer-events-none')
      }
    }
    if (this.hasInputTarget) {
      this.inputTarget.disabled = !enabled
      if (!enabled) {
        this.hideCard()
        this.hideBadge()
      } else if (this.inputTarget.value.trim() !== '') {
        this.verify(this.inputTarget.value.trim())
      }
    }
  }
}
