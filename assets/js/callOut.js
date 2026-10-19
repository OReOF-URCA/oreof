/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/js/callOut.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/02/2023 09:18
 */

import { renderStreamMessage } from '@hotwired/turbo'
import Toast from '../components/Toast'

function normalizeToastType (label = 'info') {
  switch (label) {
    case 'error':
      return 'danger'
    case 'success':
    case 'danger':
    case 'warning':
    case 'info':
      return label
    default:
      return 'info'
  }
}

export default function callOut (message, label = 'info') {
  const type = normalizeToastType(label)

  switch (type) {
    case 'success':
      Toast.success(message)
      break
    case 'danger':
      Toast.error(message)
      break
    case 'warning':
      Toast.warning(message)
      break
    case 'info':
    default:
      Toast.info(message)
      break
  }
}

/**
 * Gère de manière unifiée une réponse fetch:
 * - Turbo Stream => rendu via Turbo
 * - JSON => affichage via callOut
 */
export async function handleToastResponse (response, options = {}) {
  const {
    successFallbackMessage = '',
    errorFallbackMessage = 'Une erreur est survenue',
  } = options

  const contentType = (response.headers.get('content-type') || '').toLowerCase()
  const text = await response.text()

  if (contentType.includes('text/vnd.turbo-stream.html')) {
    renderStreamMessage(text)

    return { handled: true, format: 'turbo-stream', success: response.ok && !text.includes('data-form-error-summary') && !/data-toast-type=["']danger/.test(text) }
  }

  const trimmed = text.trim()
  if (trimmed.startsWith('{')) {
    try {
      const payload = JSON.parse(trimmed)
      const success = response.ok && payload.success !== false
      const message = payload.message || (success ? successFallbackMessage : errorFallbackMessage)
      const level = success ? normalizeToastType(payload.toast_type || payload.type || 'success') : 'danger'

      if (message) {
        callOut(message, level)
      }

      return { handled: true, format: 'json', payload, success }
    } catch {
      // Le body n'est pas un JSON exploitable; on applique les fallbacks ci-dessous.
    }
  }

  if (response.ok && successFallbackMessage) {
    callOut(successFallbackMessage, 'success')
    return { handled: true, format: 'fallback-success', success: true }
  }

  if (!response.ok && errorFallbackMessage) {
    callOut(errorFallbackMessage, 'danger')
    return { handled: true, format: 'fallback-error', success: false }
  }

  return { handled: false, format: 'unknown', success: false }
}
