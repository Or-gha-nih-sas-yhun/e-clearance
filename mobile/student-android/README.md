# MCC e-Clearance Student Android App

This Android application provides the **student panel only** for the Laravel project in `C:\wamp64\www\e-clearance`.

The app uses the existing responsive Laravel student portal inside a restricted Android WebView. This means student login, password recovery, dashboard, clearance requests, office and subject submissions, remarks, account settings, chat, and clearance documents continue to use the same Laravel controllers, sessions, CSRF protection, authorization, and database records as the website.

## Included Android features

- Student routes only (`/student/...`)
- Account self-registration, including the emailed six-digit registration code
- New-device sign-in verification (emailed six-digit code)
- Cross-role chat support with instructors, offices, treasurers, and the registrar
- Persistent Laravel login session through first-party cookies, flushed to disk
  whenever the app is backgrounded so leaving for the mail app cannot lose a
  half-finished registration
- File selection for subject and office uploads
- Authenticated document downloads to the Android Downloads folder
- Android back-button navigation
- Loading and connection-error states
- Fixed official HTTPS server address
- First-party redirects stay inside the app; external sites open in the browser
- Invalid TLS certificates are rejected
- Release builds require HTTPS

## How website features reach the app

The app is a restricted WebView over the live student portal, so a new student
feature on the website is normally available in the app with **no Android change
at all** — it is the same Laravel routes, session, and CSRF protection.

The one thing that can block a new feature is the URL allow-list in
`MainActivity.isAllowedStudentUrl()`. It permits only the portal's own origin
*and* a path of `/student` or `/student/...`; any other first-party page is
bounced back to the student login, and anything off-origin opens in the browser.

So when adding a student feature to the website:

- Put its routes under the `student.` prefix (`/student/...`) and it just works.
  Account registration (`/student/register...`) and chat support
  (`/student/chat-support`) both qualify.
- Subresources are unaffected — the allow-list only sees navigations. The login
  captcha `<img>` and the notification-bell `fetch()` calls live outside
  `/student/` and still work.
- A first-party **link or form post** outside `/student/` will be hijacked to the
  login page. The landing-page link is the only one, and the student login
  template already hides it when the user agent contains `MCCStudentAndroid/`.

## Online server

Debug and release builds connect only to the live student portal:

```text
https://mcceclearance.com/student/login
```

Version 1.2 and newer ignore server addresses saved by older installations so
launching the app cannot redirect from a local address into the external browser.

## Open in Android Studio

1. Start Android Studio.
2. Choose **Open**.
3. Select:

   ```text
   C:\wamp64\www\e-clearance\mobile\student-android
   ```

4. Let Gradle sync.
5. Select the `app` run configuration and an Android emulator or connected phone.
6. Click **Run**.

The project uses Java 17, Android Gradle Plugin 9.0.1, Gradle 9.1, compile SDK 36.1, and supports Android 8.0 or newer.

For local Android development, temporarily change `default_server_root` in
`app/src/main/res/values/strings.xml` and rebuild a debug APK. Do not publish a
build containing a local address.

## Build and install the debug APK

From this directory in PowerShell:

```powershell
$env:JAVA_HOME='C:\Program Files\Android\Android Studio\jbr'
$env:ANDROID_HOME='C:\Users\Aljun\AppData\Local\Android\Sdk'
.\gradlew.bat assembleDebug
```

Generated APK:

```text
app\build\outputs\apk\debug\app-debug.apk
```

Install it on a running emulator or USB-connected phone:

```powershell
& "$env:ANDROID_HOME\platform-tools\adb.exe" install -r app\build\outputs\apk\debug\app-debug.apk
```

## Production configuration

Before publishing:

1. Deploy Laravel to a real HTTPS domain.
2. Confirm `https://mcceclearance.com` remains configured in `app/src/main/res/values/strings.xml`.
3. Create a private Android signing key and configure release signing in `app/build.gradle.kts` without committing passwords or the keystore.
4. Build an Android App Bundle with `gradlew.bat bundleRelease`.
5. Test registration, login, new-device codes, uploads, downloads, chat, password recovery, and logout against production.

Do not enable cleartext HTTP or bypass certificate validation in a production build.
