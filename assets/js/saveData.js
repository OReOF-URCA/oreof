import callOut from './callOut'

// Keep the legacy return contract: parsed data on success, false on transport/application failure.
export const saveData = async (url, options) => {
  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(options),
    })
    const data = response.status === 204 ? true : await response.json()
    if (!response.ok || data === false || data?.success === false) {
      callOut(data?.message || 'Enregistrement refusé. Vérifiez les données et vos droits.', 'danger')
      return false
    }
    // Some existing endpoints return a validation report rather than a boolean.
    if (Array.isArray(data?.error) && data.error.length > 0) {
      callOut(data.message || 'Des points restent à corriger avant de terminer cette étape.', 'danger')
      return data
    }
    callOut('Sauvegarde effectuée', 'success')
    return data
  } catch {
    callOut('Impossible de confirmer la sauvegarde. Vérifiez la connexion et les données avant de réessayer.', 'danger')
    return false
  }
}
