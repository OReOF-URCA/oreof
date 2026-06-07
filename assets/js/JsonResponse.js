/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/js/reponse.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/06/2023 09:11
 */

import { handleToastResponse } from './callOut'

export default async function JsonResponse(reponse) {
  return handleToastResponse(reponse, {
    successFallbackMessage: 'Sauvegarde effectuee',
    errorFallbackMessage: 'Erreur lors de la sauvegarde',
  })
}
