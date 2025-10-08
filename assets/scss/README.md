# SCSS Design System

## 📁 Struttura

```
scss/
├── main.scss                    # Entry point
├── _tokens.scss                 # Design tokens (colori, spacing, radius)
├── _mixins.scss                 # Mixins riutilizzabili
├── _components.scss             # Componenti base (badge, card, grid)
├── _dashboard.scss              # Stili specifici Dashboard
├── _overview.scss               # Stili specifici Overview
└── _connection-validator.scss   # Stili specifici Connection Validator
```

## 🎨 Tokens

### Colori
```scss
@use 'tokens' as *;

.my-element {
  color: color(primary);        // #2271b1
  background: color(success);   // #116149
  border-color: color(danger);  // #9b1c1c
}
```

Colori disponibili:
- `primary`, `primary-dark`
- `success`, `warning`, `danger`, `info`
- `neutral-900`, `neutral-700`, `neutral-600`, `neutral-500`, `neutral-200`, `neutral-100`
- `white`

### Spacing
```scss
.my-element {
  padding: space(lg);    // 16px
  margin: space(xl);     // 24px
  gap: space(sm);        // 8px
}
```

Spacing disponibili:
- `xs` (4px), `sm` (8px), `md` (12px), `lg` (16px), `xl` (24px), `xxl` (32px)

### Radius
```scss
.my-element {
  border-radius: radius(lg);  // 6px
}
```

Radius disponibili:
- `sm` (3px), `md` (4px), `lg` (6px), `xl` (8px)

## 🔧 Mixins

### Card
```scss
@use 'mixins' as *;

.my-card {
  @include card();        // Padding default 16px
  @include card(20px);    // Padding custom
}
```

Output:
```css
.my-card {
  background: #ffffff;
  border: 1px solid #d5d8dc;
  border-radius: 6px;
  padding: 20px;
}
```

### Badge
```scss
.my-badge {
  @include badge(#e6f4ea, color(success));
}
```

Output:
```css
.my-badge {
  display: inline-flex;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.4;
  background: #e6f4ea;
  color: #116149;
}
```

## 🚀 Compilazione

### Build
```bash
npm run build:css
```
Compila `main.scss` → `assets/css/main.css`

### Watch Mode
```bash
npm run watch:css
```
Ricompila automaticamente ad ogni modifica

## 📝 Convenzioni

### Naming
- File parziali iniziano con `_`
- Classi CSS usano prefisso `fpdms-`
- BEM per componenti complessi: `.fpdms-component__element--modifier`

### Organizzazione
```scss
// 1. Imports
@use 'mixins' as *;
@use 'tokens' as *;

// 2. Layout/Container
.fpdms-section { ... }

// 3. Componenti
.fpdms-card { ... }

// 4. Stati/Varianti
.fpdms-card--highlight { ... }

// 5. Responsive
@media (max-width: 768px) { ... }
```

## ✨ Esempio Completo

```scss
@use 'mixins' as *;
@use 'tokens' as *;

.fpdms-my-component {
  @include card(space(lg));
  color: color(neutral-900);
  
  &__header {
    padding: space(md);
    border-bottom: 1px solid color(neutral-200);
  }
  
  &__badge {
    @include badge(#e6f4ea, color(success));
  }
  
  &--highlighted {
    border-color: color(primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
  
  @media (max-width: 768px) {
    padding: space(md);
  }
}
```

## 🎯 Best Practices

1. **Usa sempre i tokens** invece di valori hardcoded
2. **Riutilizza i mixins** per componenti comuni
3. **Organizza per componenti** non per proprietà
4. **Mobile-first** quando possibile
5. **Evita nesting eccessivo** (max 3 livelli)
6. **Documenta** mixins e token custom

## 🔄 Aggiungere Nuovi Token

```scss
// _tokens.scss
$colors: (
  // ... esistenti
  my-new-color: #123456
);

// Uso
@use 'tokens' as *;
.element { color: color(my-new-color); }
```

## 🔄 Aggiungere Nuovi Mixins

```scss
// _mixins.scss
@mixin my-mixin($param) {
  // ... stili
}

// Uso
@use 'mixins' as *;
.element { @include my-mixin(value); }
```

## 📚 Riferimenti

- [Sass Documentation](https://sass-lang.com/documentation)
- [BEM Methodology](http://getbem.com/)
- [Design Tokens](https://www.designtokens.org/)