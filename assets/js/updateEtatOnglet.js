/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/js/updateEtatOnglet.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 10:11
 */

export const updateEtatOnglet = async (url, onglet, prefix) => {
  const body = {
    method: 'POST',
    body: JSON.stringify({
      action: 'stateOnglet',
      onglet,
    }),
  }

  await fetch(url, body).then((response) => response.json()).then((data) => {
    // La pastille d'état peut être absente (suivi de complétude désactivé pour les
    // formations/parcours non classiques) : on ne met à jour le DOM que si elle existe.
    const pastille = document.getElementById(`${prefix}_${onglet}`)
    if (pastille === null) {
      return
    }
    pastille.classList.remove('state-complete')
    pastille.classList.remove('state-en-cours')
    pastille.classList.remove('state-vide')
    pastille.classList.add(`state-${data}`)
    if (data === 'en-cours' || data === 'vide') {
      // remise à 0 de l'état
      document.getElementById('etatStructure').checked = false
      document.getElementById('alertEtatStructure').classList.remove('alert-success')
      document.getElementById('alertEtatStructure').classList.add('alert-warning')
    }
  })
}
