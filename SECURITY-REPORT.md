# Security Report – MauticIxCaptchaBundle

**Datum:** 02.03.2026
**Getestete URL:** https://wp-devtest.index-dev.de/mautic-formular/
**Mautic-Instanz:** https://mautic7rc.index-dev.de
**Plugin-Version (Commit):** `4a9e44b`
**reCAPTCHA-Keys:** Echte Produktiv-Keys (kein Test-Modus)

---

## 1. Testmethodik

Alle Tests wurden als direkter HTTP-POST ohne Browser und ohne JavaScript ausgeführt (`curl`), um das Verhalten eines automatisierten Bots zu simulieren. Die Formular-JS-Logik (Consent-Button, reCAPTCHA-Laden) wurde dabei vollständig umgangen.

Getestete Formulare:
- **Form 1 – testde** (Deutsch, `formId=1`)
- **Form 2 – testfr** (Französisch, `formId=2`)
- **Form 3 – testen** (Englisch, `formId=3`)

---

## 2. Testergebnisse nach Fix (Commit `4a9e44b`)

### ✅ Alle Angriffsvektoren werden geblockt

| # | Angriffsvektor | Payload | Ergebnis | Server-Meldung |
|---|----------------|---------|----------|----------------|
| 1 | Leerer Token | `pluginixcaptcha=` | ✅ **Geblockt** | *"Please confirm you are not a robot."* |
| 2 | Müll-Token (Sonderzeichen) | `<script>alert(1)</script>` | ✅ **Geblockt** | *"Security check failed. Please try again."* |
| 3 | Fake Token im gültigen Format | 488-Zeichen Base64url-String | ✅ **Geblockt** | *"Security check failed. Please try again."* |
| 4 | Kein Token-Feld | Feld komplett weggelassen | ✅ **Geblockt** | *"Please confirm you are not a robot."* |
| 5 | Token = `null` (String) | `pluginixcaptcha=null` | ✅ **Geblockt** | *"Security check failed. Please try again."* |
| 6 | Form DE – leerer Token | `pluginixcaptcha=` | ✅ **Geblockt** | *"Please confirm you are not a robot."* |
| 7 | Form DE – Fake Token | 488-Zeichen Base64url-String | ✅ **Geblockt** | *"Security check failed. Please try again."* |
| 8 | Form EN – leerer Token | `pluginixcaptcha=` | ✅ **Geblockt** | *"Please confirm you are not a robot."* |
| 9 | Form EN – Fake Token | 488-Zeichen Base64url-String | ✅ **Geblockt** | *"Security check failed. Please try again."* |

**Ergebnis: 9/9 Tests bestanden.**

---

## 3. Kritischer Bug (gefunden & behoben)

### Beschreibung

Vor dem Fix war die **gesamte server-seitige reCAPTCHA-Validierung wirkungslos**. Alle Formular-Einsendungen wurden akzeptiert, unabhängig vom Token-Inhalt.

### Ursache

In `EventListener/IxCaptchaFormSubscriber.php` war in den `builderOptions` des Formularfeld-Typs folgende Zeile gesetzt:

```php
'addSaveResult' => false,
```

Mautic interpretiert `addSaveResult=false` so, dass das Feld-Ergebnis nicht in der Datenbank gespeichert werden soll. Intern fügt Mautic alle solchen Feld-Typen zu einer internen Liste namens `viewOnlyFields` hinzu.

Im `SubmissionModel::saveSubmission()` existiert folgende Logik:

```php
if (in_array($type, $components['viewOnlyFields'])) {
    continue; // Überspringt ALLE weitere Verarbeitung
}
```

Dieser `continue`-Befehl übersprung nicht nur das Speichern des Wertes, sondern **die gesamte Feld-Verarbeitung** — inklusive des Aufrufs unseres `ValidationEvent`-Listeners. Die Funktion `onFormValidate()` wurde **niemals aufgerufen**.

### Testergebnisse VOR dem Fix (alle Formulare, alle Tokens)

| Test | Payload | Ergebnis (IST) | Ergebnis (SOLL) |
|------|---------|---------------|-----------------|
| Leerer Token | `pluginixcaptcha=` | ❌ Akzeptiert | Ablehnen |
| Müll-Token | `<script>…` | ❌ Akzeptiert | Ablehnen |
| Fake Token (488 Zeichen) | `03AGdBq2…` | ❌ Akzeptiert | Ablehnen |
| Kein Token-Feld | (fehlendes Feld) | ❌ Akzeptiert | Ablehnen |
| Token = `null` | `pluginixcaptcha=null` | ❌ Akzeptiert | Ablehnen |

### Fix

Die Zeile `'addSaveResult' => false` wurde aus den `builderOptions` entfernt. Dadurch wird `plugin.ixcaptcha` nicht mehr als `viewOnlyField` behandelt, und die normale Feld-Verarbeitungskette (inkl. Validator-Aufruf) greift.

**Commit:** `4a9e44b` – *"Fix critical security bug: server-side reCAPTCHA validation was never called"*

```diff
- 'addSaveResult' => false,
+ // NOTE: Do NOT set addSaveResult => false here!
+ // Mautic adds any field type with addSaveResult=false to its
+ // internal "viewOnlyFields" list. For fields in that list,
+ // SubmissionModel::saveSubmission() hits a `continue` statement
+ // that skips ALL processing — including our custom validator.
+ // Omitting this key keeps the field in the normal processing path
+ // so the reCAPTCHA token is validated server-side on every submit.
```

---

## 4. Validierungslogik (nach Fix)

Die server-seitige Prüfung erfolgt in zwei Stufen:

### Stufe 1 – Format-Validierung (`RecaptchaClient.php`)

```php
if (!preg_match('/^[\w\-]{10,4096}$/', $token)) {
    return ['success' => false, 'message' => 'Invalid reCAPTCHA token'];
}
```

Leere Tokens, Sonderzeichen und Tokens außerhalb der erlaubten Zeichenmenge werden sofort abgewiesen, **ohne Google-API-Aufruf**.

### Stufe 2 – Google API-Verifizierung (`RecaptchaClient.php`)

```php
POST https://www.google.com/recaptcha/api/siteverify
  secret=<SECRET_KEY>
  response=<TOKEN>
  remoteip=<CLIENT_IP>
```

Google prüft:
- Gültigkeit und Herkunft des Tokens
- Score (0.0–1.0) gegen konfigurierten Mindestschwellenwert

Tokens die das Format-Check bestehen, aber von Google abgelehnt werden (z.B. Fake-Tokens im richtigen Format), werden in Stufe 2 geblockt.

### Fehlermeldungen je Stufe

| Stufe | Ursache | Meldung (EN) |
|-------|---------|--------------|
| Vor Stufe 1 | Token leer / fehlt | *"Please confirm you are not a robot."* |
| Stufe 1 | Token-Format ungültig | *"Security check failed. Please try again."* |
| Stufe 2 | Google API lehnt ab | *"Security check failed. Please try again."* |
| Stufe 2 | Score unter Schwellenwert | *"Security check failed. Please try again."* |

---

## 5. Sicherheitsarchitektur (Überblick)

```
Browser (Nutzer)                    Server (Mautic)
─────────────────                   ─────────────────────────────────────
1. Consent-Button klicken     →
2. reCAPTCHA JS laden         →
3. Token von Google holen     →
4. Token ins Hidden Field     →
5. Formular absenden          →     6. Token leer? → Ablehnen
                                    7. Token-Format gültig? → sonst Ablehnen
                                    8. Google API: Token echt? → sonst Ablehnen
                                    9. Score ≥ Schwellenwert? → sonst Ablehnen
                                   10. Einsendung speichern ✓
```

**Client-seitige Schutzmaßnahmen** (können von Bots umgangen werden, daher nur UX):
- Submit-Button deaktiviert bis Token vorliegt
- Consent-Banner verhindert automatisches Token-Laden

**Server-seitige Schutzmaßnahmen** (nicht umgehbar):
- Leerer Token → direkte Ablehnung
- Ungültiges Format → direkte Ablehnung (kein Google-API-Call)
- Gefälschter Token → Ablehnung durch Google API
- Niedriger Score → Ablehnung nach Schwellenwert-Prüfung

---

## 6. Empfehlungen

| Priorität | Empfehlung |
|-----------|------------|
| ✅ Erledigt | Server-seitiger Validator wird korrekt aufgerufen |
| ✅ Erledigt | Format-Validierung vor API-Call (verhindert unnötige Google-Requests) |
| ✅ Erledigt | Client-IP wird an Google weitergegeben (`remoteip`) |
| ℹ️ Optional | Score-Schwellenwert in Plugin-Settings prüfen (Standard: 0.5) |
| ℹ️ Optional | Mautic-Logs auf niedrige Scores überwachen (`var/logs/`) |

---

*Erstellt mit Claude Code – MauticIxCaptchaBundle Security Test Suite*
