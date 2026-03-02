# Document Module — Setup & Migration

## Migration Commands

```bash
# Generate new migrations (if schema changes)
php bin/console make:migration

# Execute migrations
php bin/console doctrine:migrations:migrate

# Validate schema sync
php bin/console doctrine:schema:validate
```

## Environment Variables (.env)

```env
### Medical Document Module ###
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_PHONE=+1234567890
BREVO_API_KEY=your_brevo_api_key
```

## Routes

| Route | Purpose | Role |
|-------|---------|------|
| `/documents` | Front office — user documents | ROLE_USER |
| `/admin-ea` | EasyAdmin dashboard | ROLE_ADMIN |
| `/admin-ea/document` | EasyAdmin documents CRUD | ROLE_ADMIN |
| `/admin-ea/categorie-document` | EasyAdmin categories CRUD | ROLE_ADMIN |
| `/admin/documents/dashboard` | Custom document dashboard | ROLE_ADMIN |
| `/admin/documents/crud` | Custom document list | ROLE_ADMIN |

## Ensure insurance_reference Column

The migration `Version20260222200000` alters `insurance_reference` to VARCHAR(255).
Run `php bin/console doctrine:migrations:migrate` if not already applied.
