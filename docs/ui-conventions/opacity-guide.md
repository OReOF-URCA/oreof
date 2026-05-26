<!-- Opacity & Color Variables Guide -->
# Guide d'opacité avec variables CSS personnalisées

## ⚠️ Problème : La syntaxe `/opacity` ne fonctionne PAS avec les couleurs sémantiques

Tailwind CSS supporte naturellement `color-50/opacity` **uniquement** pour les couleurs natives Tailwind. Nos variables CSS personnalisées utilisent `color-mix()`, ce qui empêche la syntaxe slash d'appliquer correctement l'opacité.

### ❌ Incorrect (ne fonctionne pas)
```html
<!-- Cela ne réduira PAS l'opacité -->
<div class="bg-primary-600/45"></div>
<div class="bg-secondary-950/50"></div>
<div class="text-info-400/80"></div>
```

**Résultat** : Tailwind génère la classe mais l'opacité n'est pas appliquée car `--color-primary-600` n'est pas au format RGB.

### ✅ Correct : utiliser `opacity-*` séparé
```html
<!-- Utiliser una classe opacity séparée -->
<div class="bg-primary-600 opacity-45"></div>
<div class="bg-secondary-950 opacity-50 dark:bg-secondary-900 dark:opacity-60"></div>
<div class="text-info-400 opacity-80"></div>
```

## Utilisation recommandée

### Pour les fonds/borders/texte semi-transparents
```html
<!-- Demi-transparent -->
<div class="bg-surface opacity-80"></div>
<div class="border border-secondary-200 opacity-50"></div>

<!-- Très transparent (effects visuels) -->
<div class="bg-primary-900 opacity-35 pointer-events-none"></div>
```

### Pour les dégradés avec opacité
```html
<!-- Éviter les styles inline ; préférer une enveloppe avec opacity -->
<div class="opacity-35" style="background: linear-gradient(to bottom right, var(--color-primary-900), var(--color-info-900), var(--color-primary-950))"></div>

<!-- Ou utiliser des overlays séparées -->
<div class="absolute inset-0 bg-secondary-950 opacity-45"></div>
<div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-info-900 to-primary-950"></div>
```

## Valeurs d'opacité courantes

| Cas d'utilisation | Classe |
|-----------|--------|
| Overlay fort | `opacity-45`, `opacity-50` |
| Overlay moyen | `opacity-35`, `opacity-40` |
| Overlay faible | `opacity-20`, `opacity-25` |
| Effet très discret | `opacity-10`, `opacity-15` |

## Checklist

- [ ] ✅ Utiliser `class="bg-primary-600 opacity-45"` plutôt que `class="bg-primary-600/45"`
- [ ] ✅ Ajouter des variantes dark mode via `dark:opacity-*`
- [ ] ⚠️ Tester sur les deux thèmes (light et dark)
- [ ] 📝 Documenter si opacité > 50 pour accessibilité (contraste)

