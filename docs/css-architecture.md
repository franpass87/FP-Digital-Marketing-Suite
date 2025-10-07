# Architettura CSS/SCSS

## Obiettivi
- Centralizzare colori, spaziature e raggi in token riutilizzabili
- Ridurre duplicazioni (card, badge, sezioni)
- Rendere più semplice mantenere coerenza visiva

## Struttura
```
assets/
  scss/
    _tokens.scss      # design tokens (colori, spaziature, radius)
    _mixins.scss      # mixins riutilizzabili (card, badge)
    _components.scss  # componenti base (card, badge...)
    main.scss         # entrypoint SCSS
```

## Utilizzo
- Importare `main.scss` nel processo di build (PostCSS/Sass) per generare il CSS finale.
- Esempio: `.fpdms-overview-section` usa il mixin `card`.

## Prossimi passi (opzionali)
- Integrare Sass (dart-sass) o PostCSS con `postcss-scss`.
- Portare gradualmente gli stili dei file CSS esistenti in partials SCSS.
- Aggiungere varianti tema e dark mode tramite token.
