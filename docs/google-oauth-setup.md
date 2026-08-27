# Google OAuth setup

Primary-only Google sign-in for the Filament login page. Family Members continue to use WhatsApp OTP only.

## Filament configuration

1. Open **Integrations → Google → Google OAuth**.
2. Click **Start Configure**.
3. Complete the Google Cloud steps and paste the Web client credentials.
4. Enable **Show Continue with Google on the login page**.
5. Save, then sign in once with the Primary Google account to link `google_id`.

The redirect URI shown on the integration page is:

```text
{GOOGLE_REDIRECT_URI or APP_URL}/admin/auth/google/callback
```

Google Cloud only accepts `localhost` or `127.0.0.1` for local redirect URIs—not custom hosts like `tido.local`. Keep `APP_URL=http://tido.local` for normal panel use and set:

```env
GOOGLE_REDIRECT_URI=http://localhost/admin/auth/google/callback
```

Register that exact URI in Google Cloud. **Continue with Google** starts OAuth on `localhost`; after Google returns, tido hands the session back to `APP_URL` automatically.

## Google Cloud Console

1. Open [Google Cloud Console → APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials).
2. Configure the [OAuth consent screen](https://console.cloud.google.com/apis/credentials/consent).
3. Create an OAuth client ID with application type **Web application**.
4. Add the redirect URI from the integration page to **Authorized redirect URIs**.
5. Copy the Client ID and Client Secret into tido.

### Testing mode pitfall

While the consent screen is in **Testing** mode, only Google accounts listed as test users can sign in. Add the Primary account email as a test user, or publish the app when ready for production.

### `org_internal` (Access blocked: can only be used within its organisation)

**Error 403: org_internal** means the consent screen **User type** is **Internal**. Only Google Workspace accounts in the same organisation as the Cloud project can sign in—a personal `@gmail.com` account is blocked.

For tido (single Primary, often a personal Gmail):

1. Open [OAuth consent screen](https://console.cloud.google.com/apis/credentials/consent).
2. Click **Edit app** (or **Get started** if not configured).
3. Set **User type** to **External** (not Internal). Save and continue through the wizard.
4. While **Publishing status** is **Testing**, open **Audience** → **Test users** → **Add users** → add the Primary Gmail (e.g. `amirul96shafiq.harun@gmail.com`).
5. Retry **Continue with Google** on the login page.

**Internal** is only appropriate when the Primary account is a Workspace user in the same org as the Cloud project. You cannot switch Internal → External on an existing consent screen in some cases; Google may require creating a new Cloud project with External from the start.

Ensure the Google account email matches the Primary user email in tido (`admin@tido.local` is the login email in the app—the Google account must match that Primary email, or link on first sign-in by email match).

## Environment variables

Optional fallbacks when the Filament settings table is empty:

| Variable               | Type   | Default | Description                                                                              |
| ---------------------- | ------ | ------- | ---------------------------------------------------------------------------------------- |
| `GOOGLE_CLIENT_ID`     | string | —       | OAuth Web client ID                                                                      |
| `GOOGLE_CLIENT_SECRET` | string | —       | OAuth Web client secret                                                                  |
| `GOOGLE_OAUTH_ENABLED` | bool   | `false` | Show **Continue with Google** on the login page                                          |
| `GOOGLE_REDIRECT_URI`  | string | —       | Full callback URL when it must differ from `APP_URL` (local: use `http://localhost/...`) |

Saved Filament settings override `.env` values.

## Security notes

- Google sign-in matches **Primary** users only (same rule as email/password login).
- No new users are created from Google profiles.
- Google access tokens are not stored.
- Unlink or reset credentials from the Google OAuth integration page when rotating secrets.

## Windows SSL (local dev)

If **Test connection** reports `Cannot reach Google token endpoint` or an SSL certificate error, PHP cannot verify HTTPS to Google. tido ships `bootstrap/cacert.pem` for local Windows hosts; it is used automatically when `curl.cainfo` is unset in `php.ini`. Override with:

```env
OUTBOUND_HTTP_CAINFO=G:\dev\php82\extras\ssl\cacert.pem
```

(`CURRENCY_API_CAINFO` is also accepted.)
