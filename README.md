# ixCaptcha — GDPR-compliant reCAPTCHA v3 for Mautic

A privacy-first Google reCAPTCHA v3 plugin for Mautic forms. No Google scripts are loaded until the visitor gives explicit consent. Multilingual support (DE / EN / FR) is built in and new languages can be added with a single `.ini` file.

---

## Features

| | |
|---|---|
| ✅ **GDPR / DSGVO compliant** | Google script loaded only after explicit user consent |
| ✅ **reCAPTCHA v3** | Invisible, score-based bot detection — no challenges for real users |
| ✅ **Configurable score threshold** | Set the minimum score in the Mautic admin UI (default: 0.5) |
| ✅ **Configurable button colour** | Set the consent button colour in the Mautic admin UI (default: #f49e00) |
| ✅ **Remote IP forwarding** | Visitor IP sent to Google for better accuracy |
| ✅ **Token validation** | Server-side format check before the Google API call |
| ✅ **Submit-button protection** | Button is disabled until the reCAPTCHA token is ready |
| ✅ **Accessible** | WCAG 2.1 — ARIA live regions, screen-reader support, keyboard accessible |
| ✅ **Multilingual** | German, English, French; add more with one `.ini` file |
| ✅ **Modern code** | PHP 8.1+, strict types, Symfony HttpClient, no direct Guzzle dependency |
| ✅ **Mautic 5 / 6 / 7** | Tested on Mautic 5 and 7; forward-compatible with 6.x |

---

## Requirements

- **Mautic** 5.0 or higher (compatible with 6.x and 7.x)
- **PHP** 8.1 or higher
- **Google reCAPTCHA v3** API keys ([get them here](https://www.google.com/recaptcha/admin))

---

## Installation

### Via Git

```bash
cd /path/to/mautic/plugins/
git clone https://github.com/tigropm/MauticIxCaptchaBundle MauticIxCaptchaBundle
php /path/to/mautic/bin/console cache:clear
php /path/to/mautic/bin/console mautic:plugins:reload
```

### Manual

1. Download the repository as a ZIP
2. Extract to `plugins/MauticIxCaptchaBundle/` (folder name must match exactly)
3. Run `php bin/console cache:clear && php bin/console mautic:plugins:reload`

### Update

```bash
cd /path/to/mautic/plugins/MauticIxCaptchaBundle
git pull
php /path/to/mautic/bin/console cache:clear
```

---

## Configuration

### 1. Enable the plugin

**Mautic Admin → Settings → Plugins → ixCaptcha (reCAPTCHA v3)** → toggle to **Yes** → enter your keys.

| Setting | Description |
|---------|-------------|
| **Site Key** | Public key from Google reCAPTCHA admin |
| **Secret Key** | Private key — never exposed to users |
| **Minimum Score** | `0.0` – `1.0`. Requests below this score are rejected. Default: `0.5` |
| **Button Colour** | Background colour of the consent button (hex, e.g. `#f49e00`). Default: orange |

> **Minimum Score guidelines:**
> `0.3` = permissive · `0.5` = balanced (recommended) · `0.7` = strict

### 2. Add the field to a form

1. Open a form in the Mautic form builder
2. Add a field of type **reCAPTCHA v3**
3. Configure per-field options:

| Option | Description |
|--------|-------------|
| **Require explicit consent** | Show a consent box before loading Google (GDPR default: on) |
| **'More information' link** | URL to your privacy policy — **required** |
| **Field language** | Language for the consent texts (de_DE / en_US / fr_FR) |

---

## Consent banner

When explicit consent mode is enabled (default), the following banner is shown before the Google script is loaded:

> *Dieses Formular nutzt Google reCAPTCHA.*
> [Datenschutzerklärung]
> `[Akzeptieren]`

All texts are customisable in the form field settings. The button colour is set globally in the plugin settings.

---

## How it works

### User flow (explicit consent mode)

```
Page loads
  → submit button disabled, hint text shown
  → consent box visible (notice + privacy link + button)
User clicks "Accept"
  → Google reCAPTCHA API script dynamically injected
  → token generated in background
  → submit button enabled
User submits form
  → token sent with form data
  → server validates token with Google API (+ remote IP)
  → score checked against configured threshold
  → form accepted or rejected
```

### Security measures

- **Token format validation** — malformed tokens are rejected before the API call
- **Remote IP forwarding** — Google uses the visitor's IP for improved bot scoring
- **Protocol validation** — `privacyUrl` must start with `https://` or `http://`, blocking `javascript:` injection
- **No secret key exposure** — key is never logged or sent to the browser
- **Symfony HttpClient** — no direct Guzzle dependency; uses Mautic's bundled HTTP client

---

## Adding a new language

1. Create `Translations/{locale}/messages.ini` (e.g. `es_ES/messages.ini`)
2. Copy any existing `.ini` file as a template and translate the values
3. The new locale appears automatically in the field's language dropdown
4. Clear the Mautic cache: `php bin/console cache:clear`

---

## Google reCAPTCHA test keys

For local development and testing:

| | |
|---|---|
| **Site Key** | `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI` |
| **Secret Key** | `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe` |

> ⚠️ These keys always return `success: true` — **never use them in production**.

---

## Troubleshooting

**Field not visible in the form builder**
→ Check that the plugin is published. Clear Mautic cache.

**Submit button stays disabled after consent**
→ Open browser dev tools → Console tab. Check for script errors.
→ Verify your Site Key is correct and the domain is registered in Google reCAPTCHA admin.

**Form submission rejected / validation fails**
→ Check `var/logs/mautic.log` for `ixCaptcha` entries.
→ Lower the Minimum Score in the plugin settings.
→ Verify your Secret Key is correct.

**"More information" link not showing**
→ The Privacy URL field is required. Enter a valid `https://` URL in the field settings.

**Button colour not changing**
→ Clear the Mautic cache after saving: `php bin/console cache:clear`

---

## Mautic version compatibility

| Version | Symfony | PHP | Status |
|---------|---------|-----|--------|
| Mautic 5.x | 5.4 | 8.1+ | ✅ Tested |
| Mautic 6.x | 6.x | 8.1+ | ✅ Compatible |
| Mautic 7.x | 7.x | 8.2+ | ✅ Tested |

---

## License

[MIT](LICENSE)

---

## Credits

Developed by [TGR Digital](https://tgr-digital.de)
