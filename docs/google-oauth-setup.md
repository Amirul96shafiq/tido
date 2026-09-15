# Google OAuth setup

One shared Google Cloud OAuth client for the install. Each household Primary links their Gmail (`users.google_id`) while authenticated, or links automatically on first **Continue with Google** when the verified Gmail matches an existing Primary email. **Continue with Google** on `/admin/login` and `/admin/register` appears when platform credentials exist. Family Members continue to use WhatsApp OTP only.

## Filament configuration

### Platform household (#1)

1. Open **Integrations → Google → Google OAuth** as the Primary of household #1.
2. Click **Start Configure** / **Edit Google OAuth**.
3. Complete the Google Cloud steps and paste the Web client credentials.
4. Save. The login page shows **Continue with Google** once Client ID and Secret are present.
5. Use **Link Google account** on this page to attach the Primary’s Gmail (`google_id`) without signing in through Google first.

### Other households

1. Open **Integrations → Google → Google OAuth**.
2. Credentials are platform-managed (read-only).
3. Use **Link Google account** / **Unlink** for this household’s Primary only.
4. Sign-in history is scoped to the current household.

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
3. Create **one** OAuth client ID with application type **Web application** for the whole tido install.
4. Add the redirect URI from the integration page to **Authorized redirect URIs**.
5. Copy the Client ID and Client Secret into household #1’s Google OAuth page.

### Testing mode pitfall

While the consent screen is in **Testing** mode, only Google accounts listed as test users can sign in. Add each Primary Gmail that will link or sign up as a test user, or publish the app when ready for production.

### `org_internal` (Access blocked: can only be used within its organisation)

**Error 403: org_internal** means the consent screen **User type** is **Internal**. Only Google Workspace accounts in the same organisation as the Cloud project can sign in—a personal `@gmail.com` account is blocked.

For tido (Primaries often use personal Gmail):

1. Open [OAuth consent screen](https://console.cloud.google.com/apis/credentials/consent).
2. Click **Edit app** (or **Get started** if not configured).
3. Set **User type** to **External** (not Internal). Save and continue through the wizard.
4. While **Publishing status** is **Testing**, open **Audience** → **Test users** → **Add users** → add each Primary Gmail that will link or sign up.
5. Link while authenticated on the Google OAuth page, or use **Continue with Google** on the login page.

**Internal** is only appropriate when every Primary account is a Workspace user in the same org as the Cloud project. You cannot switch Internal → External on an existing consent screen in some cases; Google may require creating a new Cloud project with External from the start.

Identity is the Google `sub` stored as `users.google_id`. Resolution order on **Continue with Google**:

1. Match an existing Primary by `google_id` (source of truth).
2. Else match a Primary by verified Gmail, link `google_id`, then sign in.
3. On the **Sign Up** tab only: verified Gmail with no account → return to Sign Up with a locked **Verified** email field; complete password + confirm password to create the household (Google linked on registration).
4. **Sign In** tab with an unknown Gmail → fail closed (no account provisioning).

Manual **Link Google account** on the integration page still requires an authenticated Primary session.

## Environment variables

Optional fallbacks when the Filament settings table is empty:

| Variable               | Type   | Default | Description                                                                              |
| ---------------------- | ------ | ------- | ---------------------------------------------------------------------------------------- |
| `GOOGLE_CLIENT_ID`     | string | —       | Shared OAuth Web client ID                                                               |
| `GOOGLE_CLIENT_SECRET` | string | —       | Shared OAuth Web client secret                                                           |
| `GOOGLE_REDIRECT_URI`  | string | —       | Full callback URL when it must differ from `APP_URL` (local: use `http://localhost/...`) |

Saved Filament settings (household #1 platform row) override `.env` values. The login CTA appears when credentials exist (DB or env)—there is no separate “show button” toggle.

## Security notes

- **Primary only.** Family Members cannot sign in or sign up with Google.
- Require Google `email_verified` before login, link, or Sign Up pending state.
- `google_id` takes precedence over OAuth email when both match different rows.
- Verified-email auto-link runs only after Google proves mailbox control; `google_id` + `google_linked_at` are saved in the same request.
- Sign Up pending state lives in cache/session (~15 minutes). No `User` or household is created until password + confirm password succeed.
- No Google access tokens are stored.
- Only household #1 can edit or reset shared credentials. Other households can unlink their own Primary.
- Residual accepted risk: whoever controls the linked Google account can sign in (standard Google SSO).

## Windows SSL (local dev)

If **Test connection** reports `Cannot reach Google token endpoint` or an SSL certificate error, PHP cannot verify HTTPS to Google. tido ships `bootstrap/cacert.pem` for local Windows hosts; it is used automatically when `curl.cainfo` is unset in `php.ini`. Override with:

```env
OUTBOUND_HTTP_CAINFO=G:\dev\php82\extras\ssl\cacert.pem
```

(`CURRENCY_API_CAINFO` is also accepted.)
