# Capacitor 8 compatibility audit

Research companion to the Capacitor 7 to 8 bump. Everything below was verified
against npm, the Capacitor source at tag `8.5.1`, the per-plugin GitHub repos,
and the official docs on **2026-09-08**. Version facts go stale fast; re-check
before reusing this on a project bootstrapped months from now.

Scope note: this file is research only. The bump itself (package.json,
capacitor.config.ts, scripts, README) was done by a separate session.

---

## Bottom line

1. **Every plugin the template uses has a stable v8.** Nothing blocks the bump,
   and nothing needs to be held back at 7.x.
2. **Use carets, not pins.** I found no bad patch anywhere in the 8.0.x to 8.5.1
   range across all ten packages. There is no version to avoid.
3. **The three specific hazards in the research brief were all wrong or
   misdiagnosed.** Two of them, followed literally, would have made the build
   worse. Details in [Brief claims, checked](#brief-claims-checked).
4. **The real hazard is not a version number, it is the toolchain.** Capacitor 8
   requires **Xcode 26.0+**. Every dev machine and every CI runner needs it, or
   iOS builds fail with errors that look exactly like a broken package.
5. **The second real hazard is a silent default flip.** `cap add ios` produced a
   CocoaPods project in Cap 7 and produces an SPM project in Cap 8. Same
   command, different output. See [SPM vs CocoaPods](#ios-spm-vs-cocoapods).

---

## Plugin inventory

Locked-before column is from `package-lock.json` prior to the bump.

| Package | Locked before | Latest stable 8.x | Latest 7.x | v8 exists |
| --- | --- | --- | --- | --- |
| `@capacitor/core` | 7.6.5 | **8.5.1** | 7.6.9 | yes |
| `@capacitor/cli` | 7.6.5 | **8.5.1** | 7.6.9 | yes |
| `@capacitor/android` | 7.6.5 | **8.5.1** | 7.6.9 | yes |
| `@capacitor/ios` | 7.6.5 | **8.5.1** | 7.6.9 | yes |
| `@capacitor/app` | 7.1.2 | **8.1.1** | 7.1.2 | yes |
| `@capacitor/keyboard` | 7.0.6 | **8.0.5** | 7.0.6 | yes |
| `@capacitor/network` | 7.0.4 | **8.0.1** | 7.0.4 | yes |
| `@capacitor/preferences` | 7.0.4 | **8.0.1** | 7.0.4 | yes |
| `@capacitor/splash-screen` | 7.0.5 | **8.0.2** | 7.0.5 | yes |
| `@capacitor/status-bar` | 7.0.6 | **8.0.3** | 7.0.6 | yes |
| `@capgo/capacitor-uploader` | not installed | **8.3.13** | 7.3.10 | yes |

Two structural changes worth knowing when you go looking for changelogs:

- **Plugins were split out of the monorepo during the Cap 8 cycle.**
  `@capacitor/keyboard` now lives at `ionic-team/capacitor-keyboard`. The old
  `ionic-team/capacitor-plugins` repo has **no 8.x tags for keyboard at all**, so
  a search there returns nothing and looks like the plugin was abandoned. It was
  not. `status-bar`, `splash-screen`, `network`, `preferences` and `app` are
  still in the monorepo, which moved to release-please in August 2026.
- **A `9.0.0-alpha.6` is published on the `next` tag** for core, cli, android and
  ios, and there is a `9.0.0-dev-*` on `dev`. Carets will not pull these. Do not
  reach for them.

---

## Brief claims, checked

### Claim 1: pin keyboard to 8.0.1, because 8.0.2 and 8.0.3 are broken

**False, and inverted.** The brief said teams pin `@capacitor/keyboard` to 8.0.1
because "8.0.2 painted the navbar colour above the navbar and 8.0.3 made
`setNavigationBarColor` a no-op on API 31."

Actual `@capacitor/keyboard` 8.0.x history, from `ionic-team/capacitor-keyboard`:

| Version | Published | What shipped |
| --- | --- | --- |
| 8.0.0 | 2025-12-08 | Prepare for Capacitor 8 stable (#42) |
| 8.0.1 | 2026-02-24 | AGP 9.0 no longer supports `proguard-android.txt` (#47) |
| 8.0.2 | 2026-03-25 | **fix(android):** calling `ViewCompat.onApplyWindowInsets` (#59) |
| 8.0.3 | 2026-04-10 | **fix(android):** fixing Keyboard interaction with SystemBars (#62) |
| 8.0.4 | 2026-06-16 | **fix(android):** reset WebView height when keyboard hides without animation (#60) |
| 8.0.5 | 2026-06-16 | Docs typo, `Package.swift` formatting. No code change. |

Both versions the brief calls broken are Android inset **fixes**, and 8.0.2 fixes
precisely the symptom the brief blames it for. PR #59's own rationale:

> This fixes a problem where when using the Keyboard plugin, the Status Bar
> plugin cannot set the status bar background color.

That is the "navbar color painted in the wrong place" complaint, described as
the bug 8.0.2 **removed**. Someone remembered the version and the symptom and
lost the direction between them.

PR #62 (8.0.3) closes `capacitor#8412` and `capacitor#8408`, both SystemBars
interaction bugs.

`setNavigationBarColor` is also not a Keyboard API. The plugin's entire surface
is `show`, `hide`, `setAccessoryBarVisible`, `setScroll`, `setStyle`,
`setResizeMode`, `getResizeMode`, and the four `keyboardWill/Did` listeners. No
navigation bar anything. Whatever that report was about, it was not this package.

**Consequence of following the brief:** pinning 8.0.1 ships the version *before*
all three Android inset fixes. Cap 8 is the release where Android edge-to-edge
inset handling changed, so 8.0.1 is the single worst choice in the 8.0.x line.

**Recommendation: `^8.0.5`.**

### Claim 2: official plugins fail to compile under SPM against core 8.0.2

**Real report, wrong diagnosis. Not an SPM bug and not an API regression.**

The brief matches `capacitor#8333`, "Capacitor 8 Official Plugins fail to compile
with SPM due to major API regressions", filed 2026-02-06 against exactly
`@capacitor/core@8.0.2`, closed 2026-02-16. The reported errors were real and
alarming:

- `Value of type 'PluginConfig' has no member 'getString'`
- `Incorrect argument label in call (have 'fromHex:', expected 'argb:')`
- `Value of type 'CAPPluginCall' has no member 'reject'`
- `Value of type 'any CAPBridgeProtocol' has no member 'viewController'`

The reporter dug into the built `.swiftinterface` and concluded a large part of
the API was "erroneously gated" behind the experimental `$NonescapableTypes`
Swift feature flag.

The actual cause: **the reporter was on Xcode 16.** Capacitor 8's iOS binary is
built with Swift 6.2, where non-escapable types are supported. A Swift 6.0 or 6.1
compiler cannot see API behind that flag, so a big slice of Capacitor's surface
simply does not exist from its point of view. A maintainer reproduced clean on
Xcode 26, the reporter confirmed "that solved it", issue closed.

So:

- It is **not fixed by 8.5.1**, because nothing was ever broken in the package.
- It **will reproduce on any Capacitor 8 version**, under SPM *or* CocoaPods, on
  any machine below Xcode 26.
- The fix is the toolchain, not a version pin.

**Capacitor 8 requires Xcode 26.0+** and an iOS deployment target of **15.0**
(up from 14.0 in Cap 7). This is the item most likely to burn a developer or a CI
runner on this bump, and it presents as a package problem, which is why it is
worth writing down.

### Claim 3: `android.adjustMarginsForEdgeToEdge` was removed, replaced by a SystemBars core plugin

**True, and confirmed on both halves.**

- **Removed:** `adjustMarginsForEdgeToEdge` has zero occurrences in
  `CapConfig.java` at tag `8.5.1`. It only survives in the historical CHANGELOG
  entries for PR #7885, which added it during the Cap 7 line.
- **Replacement is core, not a package.** `SystemBars` is registered inside
  `@capacitor/core` (`core-plugins.ts`, alongside `CapacitorHttp` and
  `CapacitorCookies`, exporting `SystemBarsStyle`, `SystemBarType`,
  `SystemBarsStyleOptions`). Both `@capacitor/system-bars` and
  `@capacitor/navigation-bar` 404 on npm. **Nothing to install.**
- **No opt-in flag. It is opt-out.** The config key is
  `plugins.SystemBars.insetsHandling`, `@since 8.0.0`, values `'css' | 'disable'`,
  **default `'css'`**. The CSS variables are injected with no configuration at
  all. `plugins.SystemBars` also carries `style`, `hidden` (Android) and
  `animation` (iOS).

The detail that matters more than the config: the injected variables are named
**`--safe-area-inset-*`**, which is a CSS custom property, and is *not* the same
thing as `env(safe-area-inset-*)`. They exist because `env()` is unreliable.
From the SystemBars docs:

> For Android versions with WebView bugs (< 140), safe area values might not be
> available via CSS env variables. This plugin injects correct inset values into
> `--safe-area-inset-x` CSS variables as a fallback.

The official pattern prefers the injected variable and falls back to `env()`:

```css
padding-top: var(--safe-area-inset-top, env(safe-area-inset-top, 0px));
```

Reading `env()` alone means taking the unreliable source and never seeing the
reliable one. Under Cap 7 that was survivable, because
`adjustMarginsForEdgeToEdge` could apply the margins natively instead. With that
option gone, the CSS chain is the whole story on Android.

`resources/scss/settings.scss` in this branch already resolves it correctly:
`--v-safe-area-*` reads `max(var(--safe-area-inset-X, env(safe-area-inset-X, 0px)), var(--c-safe-area-X, 0px))`,
which takes the Capacitor value first, falls back to `env()`, and lets the Safe
Area Simulator extension override a real 0px on desktop. Nothing to change.

---

## iOS: SPM vs CocoaPods

Both work under Capacitor 8. The default changed, and the ecosystem is moving.

**SPM is the Capacitor 8 default.** From the environment-setup docs:

> CocoaPods was the default iOS dependency manager in Capacitor 7 and earlier.
> Since Capacitor 8, the default has been replaced with SPM, but you can still
> use CocoaPods as an alternative if your project needs it, by passing
> `--packagemanager CocoaPods` to `npx cap add ios`.

**CocoaPods still works, but its registry freezes this year.** From
`jcesarmobile` (Capacitor maintainer) on `capacitor#8134`: CocoaPods is not being
discontinued, but the CocoaPods trunk specs repo **goes read-only at the end of
2026**, so no new packages or updates can be published there. Existing packages
keep resolving, and custom specs repos remain possible. As of today that is about
four months out.

The plugins are already drifting off it. `capacitor-plugins` carries
"chore: do not publish camera to CocoaPods (#2513)" from 2026-04-16, and
`capacitor-keyboard`'s CI has a commit about detecting `pod trunk push` success
by exit code rather than output text.

**Why this matters more here than on a normal project.** This template does not
commit `ios/` or `android/`; both are gitignored and generated per-machine by
`cap add`. So the package manager is not recorded anywhere in the repo. It is
decided by a flag on a command in `package.json`, and that command's meaning
changed between majors:

```
"cap:add:ios": "cap add ios"     # CocoaPods under Cap 7, SPM under Cap 8
```

Left bare, a developer who ran it last month and one who runs it tomorrow get
different native projects from the same commit, with no diff to explain why.
Whichever manager is chosen, **make it explicit in the script** rather than
inheriting the default:

```
cap add ios --packagemanager SPM
cap add ios --packagemanager CocoaPods
```

**Recommendation: SPM**, explicitly. It is the default, it is where the plugin
ecosystem is going, and the one reported reason to avoid it turned out to be a
stale Xcode (Claim 2 above). All eleven packages audited here ship a
`Package.swift`, so nothing in the current dependency set forces CocoaPods.

If you go SPM, know that the tuning knobs are namespaced under
`experimental.ios.spm` in the Capacitor config: `swiftToolsVersion`
(`@since 8.3.0`, default `'5.9'`), `packageTraits` (8.3.0), `packageOptions`
(8.4.0). None need setting for a default build. The declaration still carries
"Warning: Capacitor does not officially support Swift 6 yet", which sits oddly
next to the Xcode 26 requirement; they are different things (the tools-version
header in the generated `Package.swift` versus the compiler that built
Capacitor's own binary), but the juxtaposition is confusing enough to note.

---

## Android target changes

| | Cap 7.6.9 | Cap 8.5.1 |
| --- | --- | --- |
| `minSdkVersion` | 23 | **24** (drops Android 6.0) |
| `compileSdkVersion` | 35 | **36** |
| `targetSdkVersion` | 35 (Android 15) | **36** (Android 16) |
| Java source/target | 21 | 21 |
| iOS deployment target | 14.0 | **15.0** |

Cap 8 also raises a batch of AndroidX floors: `appcompat` 1.7.1, `androidx.core`
1.17.0, `activity` 1.11.0, `fragment` 1.8.9, `webkit` 1.14.0,
`coordinatorlayout` 1.3.0, `core-splashscreen` 1.2.0, `material` 1.13.0,
`espresso` 3.7.0, `androidx.test.ext:junit` 1.3.0, `cordova-android` 14.0.1.

---

## `@capgo/capacitor-uploader` and the offline photo queue

Audited separately because it is load-bearing for an upcoming client build and is
**not currently installed in this template**. Nothing here blocks the template
bump; it is forward-looking for the project that will use it.

### v8 support: confirmed, and actively maintained

| Fact | Value |
| --- | --- |
| Latest | **8.3.13**, published **2026-09-07** (one day before this audit) |
| Peer dependency | `@capacitor/core: ">=8.0.0"` |
| First 8.x | 8.0.1 on 2025-12-10, **two days after Capacitor 8.0.0 stable** |
| Last 7.x | 7.3.10 on 2025-12-08 (`>=7.0.0` peer dep) |
| SPM | `Package.swift` present, depends on `capacitor-swift-pm` **from 8.0.0** |
| CocoaPods | `CapgoCapacitorUploader.podspec` present, iOS deployment target **15.0** |
| Android | `minSdk 24`, `compileSdk 36`, `targetSdk 36`, Java 21 |
| License | MPL-2.0 |
| JS dependency | `idb ^8.0.2` |

This plugin tracked Capacitor 8 within two days of stable and its Android and
iOS floors match Cap 8's exactly (minSdk 24, compileSdk/targetSdk 36, Java 21,
appcompat 1.7.1, espresso 3.7.0, androidx junit 1.3.0). Under Cap 7 it targeted
iOS 14 and `capacitor-swift-pm` from 7.0.0. It is a deliberate, current Cap 8
port, not a package that merely happens to install. It also ships both SPM and
CocoaPods, so it does not constrain that decision.

**Nothing about the Capacitor 8 bump itself disturbs this plugin.** The concerns
below are platform-level constraints on background uploading that the client
build has to design around. Most of them are not new in Cap 8, and I have marked
which are.

### iOS: the background session, and an App Store rejection trap

The plugin uploads through its own background `URLSession` with the fixed
identifier `CapacitorUploaderBackgroundSession`. That is independent of
Capacitor, so Cap 8 does not change it.

The trap is in `Info.plist`, and the plugin documents it in its own type
definitions:

> App Store Connect rejects builds that declare `UIBackgroundModes` →
> `processing` without `BGTaskSchedulerPermittedIdentifiers`. This plugin does
> not schedule `BGTaskScheduler` work, so avoid `processing` unless another
> feature needs it. If `processing` is present (for example from older setup
> guides), include `CapacitorUploaderBackgroundSession` in
> `BGTaskSchedulerPermittedIdentifiers`.

So:

- Uploads often work with **no** `UIBackgroundModes` at all.
- Add `fetch` only when uploads must continue after the app is suspended.
- Do **not** add `processing` unless something else needs it. If an older guide
  put it there, `BGTaskSchedulerPermittedIdentifiers` must list
  `CapacitorUploaderBackgroundSession` or the build gets rejected.

```xml
<key>UIBackgroundModes</key>
<array>
  <string>fetch</string>
</array>
<key>BGTaskSchedulerPermittedIdentifiers</key>
<array>
  <string>CapacitorUploaderBackgroundSession</string>
</array>
```

### Android: two permissions the plugin's README does not mention

Android background upload runs through `net.gotev.uploadservice`, a third-party
foreground-service library. The plugin's own `AndroidManifest.xml` declares the
services, including `android:foregroundServiceType="dataSync"`, and declares
**no `<uses-permission>` entries at all**.

The plugin README tells you to add three:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
```

That list is incomplete for a `targetSdk 36` app, which is what Cap 8 gives you:

- **`FOREGROUND_SERVICE_DATA_SYNC` is missing and is required.** From Android 14
  (API 34), a foreground service must declare a type *and* the app must hold the
  matching per-type permission. Calling `startForeground()` without it throws
  `SecurityException` with a message naming the permission. It is a normal
  install-time permission, so no runtime prompt, but it must be in the manifest.
- **`POST_NOTIFICATIONS` is missing.** From Android 13 (API 33) this is a runtime
  permission, and the upload notification (`notificationTitle`, default
  `'Uploading'`) will not be visible without it. A silently invisible upload
  notification on a photo queue is a support ticket, not a crash.
- **`READ_EXTERNAL_STORAGE` is the wrong permission on modern Android.** It is
  effectively a no-op from Android 13; media reads use `READ_MEDIA_IMAGES`. Keep
  it only for the older `maxSdkVersion` range.

Check the **merged** manifest
(`app/build/outputs/logs/manifest-merger-*-report.txt`), not the source one, when
verifying this. The service entry comes from the library.

### Android: the 6-hour cap, which is the real design constraint

This is the item most likely to bite an offline photo upload queue.

An app targeting **Android 15 or higher** may run `dataSync` foreground services
for a total of **6 hours in any 24-hour period**. At the limit the system calls
`Service.onTimeout()`, the service has seconds to `stopSelf()`, and an ANR
follows if it does not ("A foreground service of dataSync did not stop within its
timeout"). Once the budget is exhausted, starting another `dataSync` service
throws `ForegroundServiceStartNotAllowedException` until the **user brings the
app to the foreground**, which resets the budget. The budget is shared across the
whole app, not per service. Apps targeting Android 15+ also may not start a
`dataSync` foreground service from a `BOOT_COMPLETED` receiver.

**This is not new in Capacitor 8.** Cap 7 already shipped `targetSdkVersion 35`,
which is Android 15, so the cap already applied. The bump to `targetSdk 36` does
not introduce it. I am flagging it because it constrains the client build's
design regardless, and because it is easy to attribute to a bump that did not
cause it.

Google's own guidance is to prefer `WorkManager` or user-initiated data transfer
jobs over a long-lived `dataSync` service, and to chunk large syncs. For a photo
queue that means: upload in bounded batches, persist queue state, resume on next
launch, and never assume a single service run drains the queue. The plugin gives
you the pieces for this: `startUpload` returns an id, `removeUpload({ id })`
cancels, `maxRetries` is per upload, and the `events` listener reports progress,
completion and failure. Android 15+ additionally needs `acknowledgeEvent`
(added 2026-07-02, #165, fixed 2026-08-12 in #175) so background events are not
lost.

Also note `foregroundServiceType="dataSync"` must be **declared in Play Console**
with a description and justification, separately from the manifest. Missing that
declaration can block submission.

### One more relevant plugin note

`fix(android): stream multipart uploads to avoid OOM` landed 2026-07-03, after
8.3.0. For a photo queue, that is a reason to stay current on 8.3.x rather than
pinning to an early 8.x.

---

## Recommended versions

Every entry is a **caret**. I looked for a reason to pin and did not find one:
no bad patch exists in the 8.0.x to 8.5.1 range on any of these packages.

| Package | Use | Why |
| --- | --- | --- |
| `@capacitor/core` | `^8.5.1` | Must match cli/android/ios exactly. Released together from one monorepo, so a shared caret keeps them in step. `cap doctor` verifies. |
| `@capacitor/cli` | `^8.5.1` | As above. |
| `@capacitor/android` | `^8.5.1` | As above. |
| `@capacitor/ios` | `^8.5.1` | As above. |
| `@capacitor/app` | `^8.1.1` | 8.x line is features and fixes only. |
| `@capacitor/keyboard` | `^8.0.5` | **Do not pin 8.0.1.** 8.0.2, 8.0.3 and 8.0.4 are the Android inset and WebView-height fixes; 8.0.1 predates all three. See Claim 1. |
| `@capacitor/network` | `^8.0.1` | Two releases in the 8.x line, both clean. |
| `@capacitor/preferences` | `^8.0.1` | Two releases in the 8.x line, both clean. |
| `@capacitor/splash-screen` | `^8.0.2` | Three releases, all clean. |
| `@capacitor/status-bar` | `^8.0.3` | Four releases, all clean. Interacts with keyboard insets, so keep both current together. |
| `@capgo/capacitor-uploader` | `^8.3.13` | Not installed here. When added: caret, and stay on 8.3.x or later for the streamed-multipart OOM fix and `acknowledgeEvent`. |

Do not use the `next` (`9.0.0-alpha.6`) or `dev` tags.

### Not a version, but the actual gate

- **Xcode 26.0+** on every dev machine and CI runner. Below that, iOS builds fail
  with errors that read like a corrupt package. This is the most likely cause of
  a mysterious failure on this bump.
- **Make `--packagemanager` explicit** on `cap add ios`. The default flipped from
  CocoaPods to SPM, and this template does not commit `ios/`, so nothing else
  records the choice.

---

## What I did not verify

Stated plainly so nobody reads more confidence into this than it earned.

- **I ran no build.** No `npm install`, no `cap sync`, no Xcode, no Gradle, no
  device or simulator. `node_modules` is not installed in this worktree. Every
  claim here is from package metadata, source at a pinned tag, issue threads and
  official docs. None of it is "I watched it work."
- **The Android permission and 6-hour-cap findings are read from Android's own
  documentation, not observed.** They need confirming on a real Android 15 or 16
  device when the client build gets there, ideally by draining a real queue past
  the budget and watching what happens.
- **The `@capgo/capacitor-uploader` audit is metadata plus source reading.** I
  did not run an upload, background the app, or test resume. For a load-bearing
  offline queue, that testing is required before committing to it, particularly
  the interaction between the plugin's `dataSync` service and the 6-hour budget.
- **I did not audit the `modules/` tree for Capacitor usage.** The `Files` module
  has its own upload path (`AppFileUpload`, `useFileUpload`, presigned S3) that
  may overlap or conflict with a native uploader on mobile. Worth a look before
  the client build picks an approach.
- **Xcode 26 availability on the firm's machines and CI is unchecked.** I have no
  visibility into either.

---

## Sources

- npm registry via `npm view`, 2026-09-08, for all version, peer-dependency and
  publish-date facts.
- `ionic-team/capacitor` at tag `8.5.1`: `cli/src/declarations.ts`,
  `core/src/core-plugins.ts`,
  `android/capacitor/src/main/java/com/getcapacitor/CapConfig.java`,
  `android-template/variables.gradle`, `ios/Capacitor.podspec`. Cap 7 figures
  from the same paths at tag `7.6.9`.
- `ionic-team/capacitor-keyboard`: commit history, releases, `src/definitions.ts`,
  PRs #59, #60, #62.
- `ionic-team/capacitor` issues #8333 (SPM plugin compile failure, Xcode 26
  resolution) and #8134 (CocoaPods trunk read-only, SPM default in Cap 8).
- `Cap-go/capacitor-uploader`: `src/definitions.ts`, `README.md`,
  `android/src/main/AndroidManifest.xml`, `android/build.gradle`,
  `Package.swift`, `CapgoCapacitorUploader.podspec`, commit history.
- Capacitor docs (via context7): `docs/updating/8-0`,
  `docs/getting-started/environment-setup`, `docs/apis/system-bars`,
  `docs/ios/spm`, `docs/updating/plugins/8-0`.
- Android developer docs: [Foreground service types are required](https://developer.android.com/about/versions/14/changes/fgs-types-required),
  [Foreground service types](https://developer.android.com/develop/background-work/services/fgs/service-types),
  [Foreground service timeouts](https://developer.android.com/develop/background-work/services/fgs/timeout),
  [Behavior changes: Android 15+](https://developer.android.com/about/versions/15/behavior-changes-15).
