# Gestione Scarto Librario - Upgrade to v8.7.x

## Security Update Summary

Version 8.7.0 addresses critical security vulnerabilities identified in the security audit.
Version 8.7.1 adds backward compatibility fixes for the existing React frontend.

1. **GDPR endpoints now require email verification** - Users must verify email ownership before exporting or deleting their data
2. **Orders with PII removed from public `/init` endpoint** - Personal data is now only accessible to authenticated admins
3. **Rate limiting added to admin authentication** - Prevents brute-force attacks on admin password
4. **Debug logging removed** - No more PII in server logs
5. **SQL injection fix in uninstall.php** - Proper escaping of table names

---

## v8.7.1 Bugfix Release

Version 8.7.1 adds session-based authentication for backward compatibility with the existing compiled React frontend:

### Changes in v8.7.1:

1. **Session marker on login**: After successful `/login`, a session marker is stored (30-minute TTL)
2. **Automatic authentication for `/init`**: If valid nonce + session marker exists, orders are returned without re-passing password
3. **Full settings for authenticated sessions**: `GET /settings` returns all fields (including email) when admin is logged in
4. **No frontend changes required**: The existing React app works without modifications

### How it works:

```
1. User loads page → GET /init → Books only (no orders)
2. User logs in → POST /login with password → Session marker set
3. User refreshes or app calls /init again → Orders now included (session detected)
4. User fetches settings → Full settings returned (session detected)
```

Session markers expire after 30 minutes. Users can also pass the password directly for immediate authentication.

---

## Installation Instructions

### For New Installations
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Note the initial passwords displayed in the admin notice (visible for 24 hours)
4. Access the application via the page with the `[scarto_librario]` shortcode

### For Upgrades from v8.6.x
1. **Backup your database** (recommended before any upgrade)
2. Replace the plugin folder with the new version
3. The database will automatically migrate (adds `scarto_gdpr_tokens` table)
4. **Frontend changes may be required** (see below)

---

## API Changes (Breaking Changes for Custom Integrations)

### 1. `/init` Endpoint Requires Authentication for Orders

**Before (v8.6.x):**
```json
{
  "books": [...],
  "orders": [{"code": "ABC123", "userData": {"nome": "...", "email": "..."}, ...}],
  ...
}
```

**After (v8.7.1 - unauthenticated):**
```json
{
  "books": [...],
  "orders": [],  // Empty when not authenticated
  "apiVersion": "8.7.1",
  "authenticated": false,
  ...
}
```

**After (v8.7.1 - authenticated via session or password):**
```json
{
  "books": [...],
  "orders": [{"code": "ABC123", ...}],  // Included when authenticated
  "apiVersion": "8.7.1",
  "authenticated": true,
  ...
}
```

**Authentication methods:**
1. **Session-based (automatic)**: After successful `/login`, the session marker allows subsequent `/init` calls to return orders
2. **Direct password**: Pass `password` in POST body or query param

**Alternative:** Call `POST /wp-json/scarto/v1/orders` with admin password in body:
```javascript
fetch('/wp-json/scarto/v1/orders', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': window.scartoSettings.nonce
  },
  body: JSON.stringify({ password: adminPassword })
})
```

### 2. GDPR Export/Delete Now Require Email Verification

**Before (v8.6.x):**
```javascript
// Anyone could export/delete any user's data with just their email!
POST /gdpr/export { email: "victim@example.com" }
```

**After (v8.7.0) - Two-Step Process:**

**Step 1: Request verification email**
```javascript
POST /wp-json/scarto/v1/gdpr/request
{
  "email": "user@example.com",
  "action": "export"  // or "delete"
}
// Response: "Email di verifica inviata"
```

**Step 2: Verify with token from email**
```javascript
POST /wp-json/scarto/v1/gdpr/verify
{
  "email": "user@example.com",
  "token": "64-char-token-from-email"
}
// Response: User's data (for export) or deletion confirmation
```

**Admin Override:** For staff dashboard, the original endpoints still work but now require admin password:
```javascript
POST /wp-json/scarto/v1/gdpr/export
{
  "email": "user@example.com",
  "password": "admin-password"
}
```

### 3. Settings Endpoint Requires Authentication for Email Fields

**Before (v8.6.x):**
```json
{
  "reservation_days": 7,
  "email_from": "noreply@library.it",
  "email_to": "staff@library.it",
  ...
}
```

**After (v8.7.1 - unauthenticated):**
```json
{
  "reservation_days": 7,
  "library_name": "...",
  "library_address": "...",
  "library_phone": "...",
  "max_books_per_reservation": 20,
  "homepage_url": "..."
  // email_from, email_to, email_from_name, email_subject_prefix NOT included
}
```

**After (v8.7.1 - authenticated via session or password):**
```json
{
  "reservation_days": 7,
  "email_from": "noreply@library.it",
  "email_to": "staff@library.it",
  "email_from_name": "...",
  "email_subject_prefix": "...",
  "library_name": "...",
  // ... all fields included
}
```

**Migration:** Full settings are automatically returned after successful `/login`. Also available via `POST /settings` save response.

---

## Frontend Compatibility

### v8.7.1: No Frontend Changes Required

The v8.7.1 release adds session-based authentication that makes the existing compiled React frontend work without modifications:

1. **Orders**: After successful login, the session marker allows `/init` to return orders automatically
2. **Settings**: After login, `GET /settings` returns full settings including email fields
3. **Reserved books**: The `_reserved` flag is always included for all books (no auth required)

The existing frontend flow continues to work:
```
1. Page loads → /init called → Books displayed (orders empty before login)
2. Staff logs in → /login called → Session marker set
3. App may call /init again → Orders now included
4. Settings page loads → Full settings visible
```

### For Custom Integrations

If building a custom frontend or integration, you can also:

#### Detect API Version
```javascript
// Check if new API
if (window.scartoSettings.apiVersion >= '8.7.0' || window.scartoSettings.ordersRequireAuth) {
  // Orders require authentication
}
```

#### Fetch Orders Separately (Alternative)
```javascript
async function loadOrders(password) {
  const response = await fetch(`${scartoSettings.root}scarto/v1/orders`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': scartoSettings.nonce
    },
    body: JSON.stringify({ password })
  });
  const data = await response.json();
  return data.orders;
}
```

#### GDPR Components (New Flow)
Implement two-step verification flow:
1. Show form to enter email
2. Call `/gdpr/request` with email and action
3. Show verification code input
4. Call `/gdpr/verify` with email and token

---

## New Database Table

A new table `wp_scarto_gdpr_tokens` is created automatically on upgrade:
- Stores email verification tokens for GDPR requests
- Tokens expire after 30 minutes
- Cleaned up automatically by the daily cron job

---

## Rate Limits

| Action | Limit |
|--------|-------|
| Admin login attempts | 5 per 15 minutes per IP |
| Admin API auth attempts | 5 per 15 minutes per IP |
| GDPR verification requests | 3 per hour per email |
| GDPR export (after verification) | Unlimited (verified user) |
| GDPR delete (after verification) | Unlimited (verified user) |

---

## Rollback Instructions

If you need to rollback to v8.6.x:
1. Restore the old plugin files
2. The `scarto_gdpr_tokens` table will remain but be unused
3. Orders will be visible to unauthenticated users again (security risk!)

**Warning:** Rolling back re-exposes the security vulnerabilities fixed in v8.7.0.

---

## Support

For issues or questions about this update, check:
- Plugin documentation
- GitHub issues: https://github.com/[repository]/issues
