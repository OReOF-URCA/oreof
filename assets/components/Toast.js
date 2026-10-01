/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file assets/components/Toast.js
 * @project oreof
 */

// Toasts Tailwind : même rendu que templates/_ui/_toast.stream.html.twig (toasts Turbo), dans #flash_toasts.
// Le contrôleur Stimulus `toast` (assets/controllers/toast_controller.js) gère l'apparition, la fermeture et le délai.
// Les classes sont écrites en entier pour être détectées par Tailwind (@source "../**/*.js").

const CONTAINER_ID = 'flash_toasts'
const CONTAINER_CLASS = 'fixed top-20 right-4 z-[9999] w-[min(24rem,calc(100vw-2rem))] flex flex-col gap-3'
const DEFAULT_TIMEOUT = 3500

const SVG_OPEN = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">'

const STYLES = {
  info: {
    box: 'border-info-100 dark:border-info-900/50 border-l-info-500 dark:border-l-info-400 bg-info-50 dark:bg-info-950',
    icon: 'text-info-600 dark:text-info-400',
    text: 'text-info-900 dark:text-info-100',
    progress: 'bg-info-500 dark:bg-info-400',
    path: 'M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z',
  },
  warning: {
    box: 'border-warning-100 dark:border-warning-900/50 border-l-warning-500 dark:border-l-warning-400 bg-warning-50 dark:bg-warning-950',
    icon: 'text-warning-600 dark:text-warning-400',
    text: 'text-warning-900 dark:text-warning-100',
    progress: 'bg-warning-500 dark:bg-warning-400',
    path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
  },
  danger: {
    box: 'border-danger-100 dark:border-danger-900/50 border-l-danger-500 dark:border-l-danger-400 bg-danger-50 dark:bg-danger-950',
    icon: 'text-danger-600 dark:text-danger-400',
    text: 'text-danger-900 dark:text-danger-100',
    progress: 'bg-danger-500 dark:bg-danger-400',
    path: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
  },
  success: {
    box: 'border-success-100 dark:border-success-900/50 border-l-success-500 dark:border-l-success-400 bg-success-50 dark:bg-success-950',
    icon: 'text-success-600 dark:text-success-400',
    text: 'text-success-900 dark:text-success-100',
    progress: 'bg-success-500 dark:bg-success-400',
    path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  },
}

function getContainer () {
  let container = document.getElementById(CONTAINER_ID)
  if (!container) {
    container = document.createElement('div')
    container.id = CONTAINER_ID
    container.className = CONTAINER_CLASS
    document.body.append(container)
  }

  return container
}

class Toast {
  createAndShow (type, text, title = null, options = {}) {
    const style = STYLES[type] || STYLES.info
    const timeout = options.timeout ?? DEFAULT_TIMEOUT

    const toast = document.createElement('div')
    toast.setAttribute('role', 'status')
    toast.setAttribute('aria-live', 'polite')
    toast.setAttribute('data-controller', 'toast')
    toast.setAttribute('data-toast-timeout-value', String(timeout))
    toast.className = `max-w-md w-full rounded-xl border-y border-r border-l-4 ${style.box} shadow-lg p-4 flex items-start gap-3.5 transform transition-all duration-300 ease-out translate-x-full opacity-0 backdrop-blur-md`

    toast.innerHTML = `
      <div class="flex-shrink-0 mt-0.5 ${style.icon}">${SVG_OPEN}<path stroke-linecap="round" stroke-linejoin="round" d="${style.path}"/></svg></div>
      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-4">
          <div class="text-sm ${style.text} break-words">
            <p data-role="title" class="font-semibold"></p>
            <p data-role="message" class="text-sm font-semibold"></p>
          </div>
          <button type="button" class="p-1 text-secondary-400 hover:text-secondary-700 dark:hover:text-secondary-200 rounded-full transition cursor-pointer" data-action="toast#close" aria-label="Fermer">
            <span class="sr-only">Fermer</span>
            ${SVG_OPEN}<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>`

    // Texte inséré via textContent : aucun HTML interprété
    const titleEl = toast.querySelector('[data-role="title"]')
    if (title) {
      titleEl.textContent = title
    } else {
      titleEl.remove()
    }
    toast.querySelector('[data-role="message"]').textContent = text

    getContainer().append(toast)
  }

  error (text, title = null, options = {}) {
    this.createAndShow('danger', text, title, options)
  }

  warning (text, title = null, options = {}) {
    // Un avertissement reste un peu plus longtemps à l'écran : il demande souvent une action de l'utilisateur
    this.createAndShow('warning', text, title, { timeout: 6000, ...options })
  }

  success (text, title = null, options = {}) {
    this.createAndShow('success', text, title, options)
  }

  info (text, title = null, options = {}) {
    this.createAndShow('info', text, title, options)
  }
}

export default new Toast()
