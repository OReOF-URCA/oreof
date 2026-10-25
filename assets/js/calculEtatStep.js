/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/js/calculEtatStep.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/03/2023 22:38
 */

import { saveData } from './saveData'
import { updateEtatOnglet } from './updateEtatOnglet'

/** @deprecated */
export const calculEtatStep = async (url, step, event, prefix) => {
  // await saveData(url, {
  //   action: 'etatStep',
  //   value: step,
  //   isChecked: event.target.checked,
  // }).then(async (data) => {
  //   if (document.getElementById('alert-error')) {
  //     document.getElementById('alert-error').remove()
  //   }
  //   if (data === true) {
  //     const parent = event.target.closest('.alert')
  //     if (event.target.checked) {
  //       parent.classList.remove('alert-warning')
  //       parent.classList.add('alert-success')
  //     } else {
  //       parent.classList.remove('alert-success')
  //       parent.classList.add('alert-warning')
  //     }
  //   } else {
  //     // event.target.checked = false
  //     document.getElementById('etatStructure').checked = false
  //     let liste = '<ul>'
  //     data.error.forEach((error) => {
  //       liste += `<li>${error}</li>`
  //     })
  //     liste += '</ul>'

  //     const zone = document.getElementById('alertEtatStructure')

  //     zone.innerHTML += `
  //           <div class="alert alert-danger border-2 d-flex align-items-center mt-2" role="alert" id="alert-error">
  //         <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-3"></span></div>
  //         <p class="mb-0 flex-1">${liste}</p>
  //     </div>`
  //   }
  //   await updateEtatOnglet(url, `onglet${step}`, prefix)// todo: mettre à la fin
  // })
}
