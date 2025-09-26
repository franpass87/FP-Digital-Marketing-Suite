# Admin UI Component Library

The reusable components below build on the admin design tokens and base layer to deliver consistent, accessible layouts across plugin screens. Each component can be rendered with the `FP\DigitalMarketing\Admin\UI\Components` helper and is scoped by the `fp-dms-admin` body class automatically added by the optimization helper.

> **Namespace import**
>
> ```php
> use FP\DigitalMarketing\Admin\UI\Components;
> ```

## Page Header

Purpose | Markup | Accessibility
--- | --- | ---
Introduce a screen with title, supporting metadata, and action buttons. | ```php
echo Components::page_header([
    'title'    => __( 'Performance Overview', 'fp-digital-marketing' ),
    'subtitle' => __( 'Monitor speed, stability, and search health at a glance.', 'fp-digital-marketing' ),
    'meta'     => [
        [
            'label' => __( 'Last synced', 'fp-digital-marketing' ),
            'value' => $last_sync,
        ],
        __( 'GA4 property: ' . $current_property ),
    ],
    'actions'  => [
        [
            'label'   => __( 'Add data source', 'fp-digital-marketing' ),
            'url'     => admin_url( 'admin.php?page=fp-dms-integrations' ),
            'variant' => 'primary',
        ],
    ],
]);
``` | `aria-describedby` links the title to subtitle and metadata, while the action group is announced as "Page actions" for assistive tech.

## Card & Panel

Component | Markup | Notes
--- | --- | ---
Card | ```php
echo Components::card([
    'title'       => __( 'Core Web Vitals', 'fp-digital-marketing' ),
    'description' => __( 'Field data collected from Google Chrome users.', 'fp-digital-marketing' ),
    'meta'        => [ sprintf( __( 'Last updated %s', 'fp-digital-marketing' ), $updated_time ) ],
    'content'     => wp_kses_post( $metrics_table ),
    'footer'      => '<a class="button button-secondary" href="' . esc_url( $report_url ) . '">' . esc_html__( 'View full report', 'fp-digital-marketing' ) . '</a>',
]);
``` | Wraps content in a padded surface with optional metadata and footer actions.
Panel | ```php
echo Components::panel([
    'tone'    => 'warning',
    'content' => sprintf(
        '<strong>%s</strong> %s',
        esc_html__( 'Heads up:', 'fp-digital-marketing' ),
        esc_html__( 'Your Google Ads connection expires soon. Renew access.', 'fp-digital-marketing' )
    ),
]);
``` | `tone` accepts `warning`, `danger`, or `success` to match sidebar callouts.

## Form Row

```php
echo Components::form_row([
    'id'       => 'fp-dms-cache-duration',
    'label'    => __( 'Cache retention (minutes)', 'fp-digital-marketing' ),
    'help'     => __( 'Higher values reduce API calls but may delay new metrics.', 'fp-digital-marketing' ),
    'required' => true,
    'control'  => sprintf(
        '<input type="number" name="cache_duration" value="%d" min="5" max="1440" class="regular-text" />',
        absint( $settings['cache_duration'] )
    ),
    'error'    => $has_error ? __( 'Enter a value between 5 and 1440.', 'fp-digital-marketing' ) : '',
    'inline'   => true,
]);
```

* Automatically injects `id`, `required`, and `aria-describedby` attributes when missing.
* Wrap longer helper text in `<p>` elements; errors are announced with `role="alert"`.

## Tab Navigation

```php
echo Components::tab_nav([
    'label' => __( 'Settings sections', 'fp-digital-marketing' ),
    'tabs'  => [
        [
            'id'     => 'fp-dms-tab-general',
            'label'  => __( 'General', 'fp-digital-marketing' ),
            'panel'  => 'fp-dms-panel-general',
            'active' => true,
        ],
        [
            'id'    => 'fp-dms-tab-advanced',
            'label' => __( 'Advanced', 'fp-digital-marketing' ),
            'panel' => 'fp-dms-panel-advanced',
        ],
    ],
]);
```

Associate each `panel` id with a container using `.fp-dms-tab-panel` and toggle the `.is-active` class when switching tabs via JavaScript.

## Notice

```php
echo Components::notice([
    'title'   => __( 'Analytics disconnected', 'fp-digital-marketing' ),
    'message' => __( 'We could not reach Google Analytics in the past 24 hours. Check your OAuth credentials.', 'fp-digital-marketing' ),
    'type'    => 'error',
    'status'  => 'alert',
    'actions' => [
        [
            'label'   => __( 'Reconnect now', 'fp-digital-marketing' ),
            'url'     => admin_url( 'admin.php?page=fp-dms-integrations&service=ga4' ),
            'variant' => 'primary',
        ],
        [
            'label' => __( 'Dismiss', 'fp-digital-marketing' ),
            'type'  => 'button',
            'class' => 'dismiss-notice',
        ],
    ],
]);
```

## Toolbar

```php
echo Components::toolbar([
    'filters' => [
        '<label for="fp-dms-filter-status" class="screen-reader-text">' . esc_html__( 'Filter by status', 'fp-digital-marketing' ) . '</label>' .
        '<select id="fp-dms-filter-status" name="status">' .
            '<option value="all">' . esc_html__( 'All sources', 'fp-digital-marketing' ) . '</option>' .
            '<option value="connected">' . esc_html__( 'Connected', 'fp-digital-marketing' ) . '</option>' .
        '</select>',
    ],
    'actions' => [
        [
            'label'   => __( 'Export', 'fp-digital-marketing' ),
            'type'    => 'submit',
            'variant' => 'secondary',
        ],
        [
            'label'   => __( 'Add source', 'fp-digital-marketing' ),
            'url'     => admin_url( 'admin.php?page=fp-dms-integrations&action=new' ),
            'variant' => 'primary',
        ],
    ],
]);
```

The toolbar groups filter controls and primary actions while exposing an `aria-label` for assistive technology.

---

All components inherit focus states, spacing, and typography tokens. When building custom modules, prefer composing these helpers before adding new bespoke markup.
