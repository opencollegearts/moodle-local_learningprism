# OCA Learning Prism (`local_learningprism`)

Moodle 4.3+ local plugin that lets teachers enable **Learning Prism** per course and provides accessible alternate formats for **Book**, **Page**, **Label**, and **Articulate Rise SCORM** activities:

- **DOCX** — generated in PHP (Heading 1–6 styles and embedded images). No extra service.
- **EPUB 3** — generated in PHP. No extra service.
- **PDF** — Gotenberg (recommended), WeasyPrint HTTP, or LibreOffice. Needs a converter process.
- **TTS audio** — Google Cloud Text-to-Speech or ElevenLabs. Optional; needs an API account.

Teachers can **pre-generate** formats; students can **request** missing formats and **download** when ready. The course-page control changes icon: request → processing → download.

## What works on day one

| Format | After plugin install | Extra setup |
|--------|----------------------|-------------|
| Word (DOCX) | Ready | PHP `zip` extension (normal on Moodle) |
| EPUB | Ready | PHP `zip` extension |
| PDF | Hidden until a PDF engine is configured | **Gotenberg in Docker** (this README) |
| Audio | Hidden until a TTS provider is configured | Google Cloud or ElevenLabs |

You can install the plugin, enable it on a course, and generate Word/EPUB immediately. Add Gotenberg when you want PDF. Add TTS when you want audio.

## Requirements

- Moodle **4.3 or later** (tested on 4.5)
- PHP with **cURL** and **ZipArchive** (`zip` extension)
- Moodle **cron** running, or a way to run scheduled tasks by hand (conversions do not finish in the browser)
- For PDF: [Docker](https://docs.docker.com/get-docker/) on the Moodle host or on a machine Moodle can reach
- For audio (optional): a Google Cloud project with billing, or an ElevenLabs account

## Install the plugin

1. Copy this folder to `moodle/local/learningprism`. The directory name **must** be `learningprism`.
2. Visit **Site administration → Notifications** and complete the install.
3. Open **Site administration → Plugins → Local plugins → OCA Learning Prism**.
4. Leave **Enable Learning Prism**, **Enable DOCX**, and **Enable EPUB** ticked.
5. Leave **PDF engine** as **Not configured** until Gotenberg is running (see below). PDF stays hidden from users until then.
6. In a course, use **Course navigation → Enable Learning Prism** (or the checkbox on the course edit form).

Students and teachers with the usual roles then see the accessibility icon next to Book, Page, Label, and Articulate Rise SCORM activities.

## Make conversions run (cron)

Jobs are queued when someone clicks **Request**. They are processed by the scheduled task **Process Learning Prism conversion jobs** (every minute).

If cron is not running, files stay on **Queued** forever.

**Production:** set up Moodle cron as usual (`php admin/cli/cron.php` every minute). See [Moodle cron](https://docs.moodle.org/en/Cron).

**First test, or a machine without cron (MAMP, local):** run the task once from **Site administration → Server → Scheduled tasks → Process Learning Prism conversion jobs → Run now**, or from the Moodle root:

```bash
php admin/cli/scheduled_task.php --execute='\local_learningprism\task\process_jobs'
```

On MAMP, use that install’s PHP binary, for example:

```bash
/Applications/MAMP/bin/php/php8.3.9/bin/php admin/cli/scheduled_task.php --execute='\local_learningprism\task\process_jobs'
```

## PDF with Gotenberg (recommended)

Gotenberg is a small HTTP service that prints HTML to PDF with Chromium. Moodle POSTs the activity HTML to it and stores the PDF. This is the supported way for most sites.

### 1. Install Docker

Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) (macOS/Windows) or Docker Engine (Linux). Confirm it works:

```bash
docker --version
docker compose version
```

### 2. Start Gotenberg

**Option A — Docker Compose (preferred)**

From the plugin folder (or copy [`docker-compose.example.yml`](docker-compose.example.yml) somewhere convenient as `docker-compose.yml`):

```bash
docker compose -f docker-compose.example.yml up -d
```

That starts `gotenberg/gotenberg:8`, restarts it after reboot, listens on **localhost port 3000 only**, and gives Chromium 2 GB RAM.

**Option B — one `docker run` command**

```bash
docker run -d --name gotenberg --restart unless-stopped \
  -p 127.0.0.1:3000:3000 \
  gotenberg/gotenberg:8 \
  gotenberg --chromium-disable-javascript=true --api-timeout=120s
```

The `127.0.0.1:` bind keeps the converter off the public internet. Do not publish port 3000 on `0.0.0.0` unless it is firewalled.

### 3. Check it is up

From the **Moodle host**:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:3000/health
```

That should print `200`. Useful checks if it does not:

```bash
docker ps --filter name=gotenberg
docker logs gotenberg --tail 50
```

### 4. Point Moodle at Gotenberg

**Site administration → Plugins → Local plugins → OCA Learning Prism:**

1. Tick **Enable PDF format**.
2. Set **PDF engine** to **Gotenberg (HTTP)**.
3. Set **PDF engine URL** using this table:

| Where Moodle PHP runs | Where Gotenberg runs | PDF engine URL |
|-----------------------|----------------------|----------------|
| Same machine as Docker (MAMP, local PHP, host install) | Compose / `docker run` as above | `http://127.0.0.1:3000` |
| Inside Docker, **same Compose network** as the `gotenberg` service | This Compose file | `http://gotenberg:3000` |
| Inside Docker, Gotenberg published on the host | Docker Desktop (Mac/Windows) | `http://host.docker.internal:3000` |
| Another VM / server Moodle can reach | Gotenberg on that host | `http://<gotenberg-host>:3000` |

`http://gotenberg:3000` only works when Moodle’s PHP process is on the **same Docker network**. It will fail from MAMP or a host PHP install — use `http://127.0.0.1:3000` there.

4. Save.

### 5. Convert a test PDF

1. Open a course with Learning Prism enabled.
2. Click the activity icon → tick **PDF** → **Request or regenerate selected**.
3. Run **Process Learning Prism conversion jobs** (or wait for cron). The first Chromium run can take 15–30 seconds.
4. When the icon turns to the download state, open the modal and download the PDF.

If the course report shows **Error**, Moodle could not reach Gotenberg or did not get a PDF back. Recheck the URL with `curl` **from the same machine that runs PHP**, then `docker logs gotenberg`.

### Gotenberg troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| `curl` to `/health` is not `200` | Container not running; port 3000 already in use; Docker not started |
| PDF jobs error immediately | Wrong **PDF engine URL** (typical: `http://gotenberg:3000` from MAMP). Moodle may also block localhost in **Site administration → Security → HTTP security → cURL blocked hosts list** — remove `127.0.0.1` / `localhost` if Gotenberg runs on the same machine. |
| Jobs stay queued | Cron / scheduled task not running |
| Container exits or is killed | Not enough RAM — raise `mem_limit` in Compose (try `4g` for long Books) |
| Timeout on large activities | Increase `--api-timeout` (Compose file uses `120s`) |
| Works on the host, fails in Moodle | Moodle PHP is in a different network namespace than `curl` — use the URL table above |

### Production placement

Run Gotenberg **next to** Moodle (Compose sidecar, internal VM, or cluster service), not on a public web node if you can avoid it. Chromium is RAM-heavy.

- Give the container **2 GB+ RAM**; raise it for long Books.
- Keep port 3000 on a private interface or Docker network.
- Leave conversion on the scheduled task so web requests never wait on Chromium.
- You can restart or scale Gotenberg without touching Moodle.

## Other PDF engines

Gotenberg is the practical default. The plugin also supports:

| Engine | Use when | Setup |
|--------|----------|--------|
| **WeasyPrint (HTTP)** | You care more about tagged / structured PDF than Chromium’s visual match | There is no official WeasyPrint server image. Run WeasyPrint behind a small HTTP wrapper. Set **PDF engine** to **WeasyPrint (HTTP)** and **PDF engine URL** to that wrapper. The plugin POSTs HTML (`Content-Type: text/html`) to `{URL}/pdf`, then `{URL}`. |
| **LibreOffice (local binary)** | A laptop already has LibreOffice and you do not want Docker | Install LibreOffice. Set **PDF engine** to **LibreOffice (local binary)** and **LibreOffice binary path** to an executable, e.g. `/usr/bin/soffice` (Linux) or `/Applications/LibreOffice.app/Contents/MacOS/soffice` (macOS). Moodle writes DOCX then shells out to `soffice --headless --convert-to pdf`. Fine for a single-user test; avoid in production (slow, poor under overlapping cron). |

Failed PDF rows on the course **Learning Prism report** can be retried with **Regenerate**. Teachers can also tick PDF in the activity control and **Request or regenerate selected**.

## Admin settings

**Site administration → Plugins → Local plugins → OCA Learning Prism**

| Setting | Purpose |
|---------|---------|
| Enable Learning Prism | Site-wide master switch |
| Enable DOCX / PDF / EPUB / Audio | Format toggles |
| Print background colour | Default page colour for PDF, EPUB, and Word (light presets) |
| TTS provider | `google` or `elevenlabs` |
| Google credentials JSON | Service account key for Cloud TTS |
| ElevenLabs API key + voice ID | ElevenLabs TTS |
| PDF engine | `gotenberg`, `weasyprint`, `libreoffice`, or `none` |
| PDF engine URL | Gotenberg/WeasyPrint base URL, e.g. `http://127.0.0.1:3000` |
| LibreOffice path | e.g. `/usr/bin/soffice` |
| Maximum content length | Reject conversion when extracted text is longer than this |
| Jobs per cron run | How many queued jobs to process each minute |
| Purge stale files after (days) | Remove ready files whose content hash no longer matches |

### EPUB 3

EPUB is generated in PHP with `ZipArchive` (same idea as DOCX): a single XHTML document, heading-based table of contents, and images unpacked from data URIs. No extra server.

Enable **EPUB format** in the plugin settings. Files download as `.epub` (`application/epub+zip`).

### Print background colour

**Print background colour** applies a light page colour to **PDF, EPUB, and Word**. Body text stays dark. Audio is unchanged.

Presets: white (default), cream, pale yellow, pale blue, pale green, pale pink.

This is a conversion-time setting for the shared (teacher) files. Change the colour, then **Regenerate** existing files on the course report (or from the activity control).

Users can also pick their own background and a dyslexia-friendly font (Arial, Verdana, OpenDyslexic, Atkinson Hyperlegible, Lexend, and others) in the activity modal and **Request my version**. That copy is private to them. Teacher/admin generated files stay the shared default. Audio is not personalised. OpenDyslexic, Atkinson, and Lexend use those family names; if the font is not installed on the reader or in Word, the file falls back to Arial or Comic Sans.

### Articulate Rise SCORM

SCORM activities are included when the package is an **Articulate Rise** LMS export (SCORM 1.2 in the examples we tested). The plugin reads `scormcontent/runtime-data.js`, turns lessons and blocks into HTML (headings, lists, quotes, images, accordions, labeled graphics, flashcards, process steps, attachments), then runs the same DOCX / PDF / EPUB / audio converters as Book and Page.

Other SCORM authoring tools are skipped (no control icon). Interactive navigation is linearised for print and TTS. Attachment blocks are listed by filename; the binary file is not embedded. Images are taken from `scormcontent/assets/` in the package — the plugin does not fetch Articulate CDN URLs.

After uploading or updating a Rise package, **Regenerate** formats so the content hash picks up the new `runtime-data.js`.

## Audio (optional)

Skip this until Word/EPUB (and PDF, if you need it) work. Audio calls an external API.

### Google Cloud TTS

The plugin authenticates with a **service account JSON key** (OAuth2 JWT), then calls Google’s Cloud Text-to-Speech REST API (`text:synthesize`) to produce MP3. An API key alone is not enough.

Moodle must be able to reach `https://oauth2.googleapis.com` and `https://texttospeech.googleapis.com` over HTTPS. Cloud TTS is a billable Google API — enable billing on the project or synthesis will fail.

#### 1. Create or choose a Google Cloud project

1. Open [Google Cloud Console](https://console.cloud.google.com/) and sign in.
2. Use the project picker (top bar) → **New Project** (or select an existing one).
3. Give it a name (e.g. `moodle-learning-prism`) and create it.

#### 2. Enable billing

1. **Billing** → link a billing account to the project.
2. Optional but recommended: set a budget alert so unexpected TTS usage is noticed.

#### 3. Enable the Cloud Text-to-Speech API

1. **APIs & Services → Library**.
2. Search for **Cloud Text-to-Speech API**.
3. Open it and click **Enable**.

Direct link (with the project selected): [Text-to-Speech API](https://console.cloud.google.com/apis/library/texttospeech.googleapis.com).

#### 4. Create a service account

1. **IAM & Admin → Service Accounts → Create service account**.
2. Name it something like `moodle-tts`.
3. Click **Create and continue**.
4. Grant the role **Cloud Text-to-Speech User** (`roles/cloudtts.user`).
   If that role is not listed, **Cloud Text-to-Speech Admin** also works (broader than needed).
5. Skip optional user access and click **Done**.

#### 5. Create a JSON key

1. Open the new service account.
2. **Keys → Add key → Create new key**.
3. Choose **JSON** and create it. A `.json` file downloads immediately — store it privately (do not commit it to git).
4. Open the file in a text editor. It should look like:

```json
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "moodle-tts@your-project-id.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "..."
}
```

The plugin needs `client_email` and `private_key` from this file. If either is missing, audio jobs fail with “Text-to-speech is not configured.”

#### 6. Paste the key into Moodle

1. **Site administration → Plugins → Local plugins → OCA Learning Prism**.
2. Tick **Enable TTS audio format**.
3. Set **TTS provider** to **Google Cloud Text-to-Speech**.
4. Paste the **entire JSON file contents** into **Google Cloud credentials JSON** (not a file path).
5. Set **Google Cloud language code** (BCP-47), e.g. `en-US`.
6. Set **Google Cloud default voice** to a voice that matches that language, e.g. `en-US-Neural2-A`.
7. Save.

Voice names must match Google’s catalogue and the language code. Browse voices in the [Cloud TTS voice list](https://cloud.google.com/text-to-speech/docs/voices). Neural2 / Studio voices sound better than Standard; they also cost more.

Useful starting pairs:

| Language | Voice |
|----------|--------|
| `en-US` | `en-US-Neural2-A` (female), `en-US-Neural2-D` (male) |
| `en-GB` | `en-GB-Neural2-A` |
| `en-AU` | `en-AU-Neural2-A` |

#### 7. Check it works

1. In a course with Learning Prism enabled, pre-generate **Audio** for a Book, Page, Label, or Rise SCORM activity (or request it from the activity control).
2. Run **Process Learning Prism conversion jobs** (or wait for cron).
3. If the job is **ready**, download the MP3. If it is **error**, open the course Learning Prism report — typical causes:
   - billing not enabled / quota exceeded
   - API not enabled on this project
   - JSON truncated or extra commentary pasted around it
   - voice name does not exist for that language (`en-US-Neural2-A` with language `en-GB` will fail)
   - Moodle host blocked from reaching Google (firewall / proxy)

Treat the JSON as a secret. If it leaks, delete that key in GCP (**Keys → Delete**) and create a new one.

### ElevenLabs

1. In the [ElevenLabs dashboard](https://elevenlabs.io/app/settings/api-keys), create an API key. Copy the **secret key** — it must start with `sk_`. Do not paste the key *ID*.
2. Open **Voices**, pick a voice, and copy its **Voice ID**.
3. In Moodle, set **TTS provider** to **ElevenLabs**, paste the `sk_…` key and Voice ID, enable **TTS audio format**, and save.

## Capabilities

| Capability | Default | Purpose |
|------------|---------|---------|
| `local/learningprism:manage` | editingteacher, manager | Enable course feature, pre-generate, regenerate files |
| `local/learningprism:request` | student+ | Queue missing formats |
| `local/learningprism:download` | student+ | Download ready files |
| `local/learningprism:viewreport` | teacher+ | View course job report |

## Accessibility notes

- Source HTML is normalised: single `h1` title, heading levels adjusted, scripts stripped, `lang` set on the document.
- DOCX maps headings to Word **Heading 1–6** (outline levels, bold sizes) for navigation pane / AT, and embeds raster images from the HTML.
- EPUB 3 includes a `nav` document from those headings so ebook readers can offer a table of contents.
- PDF quality depends on the engine: WeasyPrint/LibreOffice generally preserve structure better for assistive tech than raw Chromium print; Gotenberg is convenient for fidelity. Full PDF/UA certification is out of scope for v1 — validate outputs with your institution’s checker.
- TTS sends plain text derived from the HTML (headings become pauses). Content may leave your site to Google or ElevenLabs.
- Articulate Rise SCORM is linearised (lessons as `h2`, interactive blocks as headings plus text) so print and TTS are usable; click-to-reveal behaviour is not preserved.
- Learners can request a private Word/PDF/EPUB with their own background colour and font; those files are not shared with other users.

## Scheduled tasks

- **Process Learning Prism conversion jobs** — every minute
- **Purge stale Learning Prism files** — daily (removes ready files whose content hash no longer matches)

## Development

```bash
# From Moodle root, after install:
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit local/learningprism/tests
```

Rebuild AMD after editing `amd/src/course.js`:

```bash
npx grunt amd --root=local/learningprism
```

## Architecture (short)

Course enable flag → course-page AMD injects controls on book/page/label/Rise SCORM → AJAX queues jobs → converters run (DOCX/EPUB local zip; PDF/TTS external) → files stored in `local_learningprism` file area → pluginfile download with capability checks.

## Moodle Marketplace listing

Zip the `learningprism` directory (not its parent). The folder name and `$plugin->component` must stay `learningprism` / `local_learningprism`.

Before you submit, the Marketplace review also expects:

- A **public git repository** named `moodle-local_learningprism`
- A **public issue tracker** (GitHub Issues is fine)
- Screenshots of the course control, modal, and report
- A short and a full English description (this README can be the full description)
- Demo credentials if you want reviewers to test Google or ElevenLabs audio (PDF/DOCX/EPUB do not need them)

Google Cloud TTS and ElevenLabs are optional paid third-party services. Gotenberg is optional and self-hosted. Word and EPUB work with no extra accounts.
