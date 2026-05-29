/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/js/updateUrl.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 29/01/2025 11:47
 */

export default function updateUrl(params, action = 'add') {
  if (Object.keys(params).length === 0) {
    return;
  }

  const url = new URL(window.location);
  const searchParams = new URLSearchParams(url.search);

  Object.keys(params).forEach((key) => {
    if (params[key]) {
      searchParams.set(key, params[key]);
    } else {
      searchParams.delete(key);
    }
  });

  if (action === 'remove') {
    searchParams.forEach((value, key) => {
      if (params[key]) {
        searchParams.delete(key);
      }
    });
  }

  url.search = searchParams.toString();
  window.history.replaceState({}, '', url);
}
