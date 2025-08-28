# Cliente Custom Post Type Implementation

## Overview

This implementation extends the FP Digital Marketing Suite with a Custom Post Type called "Cliente" (Client) that includes advanced metadata fields for comprehensive client management.

## Features

### Custom Post Type: Cliente
- **Post Type Slug**: `cliente`
- **Admin Interface**: Integrated with WordPress admin
- **Menu Position**: High priority in admin menu
- **Icon**: Business person dashicon
- **Capabilities**: Standard post capabilities

### Advanced Metadata Fields

1. **Settore (Sector)**
   - Field Type: Text input
   - Purpose: Industry/sector classification
   - Validation: Sanitized text
   - Meta Key: `_cliente_settore`

2. **Budget Mensile (Monthly Budget)**
   - Field Type: Number input (with decimals)
   - Purpose: Monthly marketing budget in euros
   - Validation: Numeric validation, minimum 0
   - Meta Key: `_cliente_budget_mensile`

3. **Email di Riferimento (Reference Email)**
   - Field Type: Email input
   - Purpose: Primary contact email
   - Validation: WordPress email validation
   - Meta Key: `_cliente_email_riferimento`

4. **Stato Attivo/Inattivo (Active/Inactive Status)**
   - Field Type: Checkbox toggle
   - Purpose: Client activity status
   - Values: '1' for active, '0' for inactive
   - Meta Key: `_cliente_stato_attivo`

## Security Features

- **Nonce Protection**: All forms protected with WordPress nonces
- **Capability Checks**: Only users with `edit_post` capability can modify
- **Input Sanitization**: All inputs properly sanitized
- **Validation**: Email format and numeric validations
- **Autosave Prevention**: Meta saving disabled during autosaves

## File Structure

```
src/
├── DigitalMarketingSuite.php     # Main application class
├── PostTypes/
│   └── ClientePostType.php      # Custom post type registration
└── Admin/
    └── ClienteMeta.php          # Meta fields and admin interface
```

## Usage

### Installation
1. Copy the plugin files to your WordPress plugins directory
2. Activate the "FP Digital Marketing Suite" plugin
3. The "Clienti" menu will appear in your WordPress admin

### Adding Clients
1. Navigate to "Clienti" > "Aggiungi Nuovo" in WordPress admin
2. Fill in the client title and description
3. Complete the "Informazioni Cliente" meta box with:
   - Settore (Industry sector)
   - Budget Mensile (Monthly budget in euros)
   - Email di Riferimento (Primary contact email)
   - Check "Cliente Attivo" if the client is currently active

### Managing Clients
- View all clients: "Clienti" > "Tutti i Clienti"
- Edit existing clients: Click on any client in the list
- All metadata is automatically saved and validated

## Technical Implementation

### WordPress Hooks Used
- `init`: Register custom post type
- `add_meta_boxes`: Add admin meta boxes
- `save_post`: Save metadata with validation
- `plugins_loaded`: Initialize the plugin

### WordPress Functions Used
- `register_post_type()`: Post type registration
- `add_meta_box()`: Admin interface
- `wp_nonce_field()` / `wp_verify_nonce()`: Security
- `current_user_can()`: Permission checks
- `sanitize_text_field()`, `sanitize_email()`: Input sanitization
- `is_email()`, `is_numeric()`: Validation

### Coding Standards
- Follows WordPress Coding Standards
- PSR-4 autoloading compatible
- Strict type declarations
- Proper namespace usage
- Comprehensive documentation

## Acceptance Criteria Compliance

✅ **Campi visibili e modificabili in schermata admin**
- Meta box with all required fields in the WordPress admin edit screen

✅ **Dati salvati correttamente**
- Proper WordPress meta API usage with validation and sanitization

✅ **Solo utenti autorizzati possono modificare**
- Permission checks using `current_user_can('edit_post', $post_id)`

✅ **Output atteso: clienti con meta esteso, testato UI**
- Complete client management with extended metadata and admin UI