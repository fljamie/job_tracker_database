# 📋 Job Application Tracker

A lightweight, self-hosted job application tracking tool that runs entirely on your local machine. No cloud accounts, no subscriptions, no data leaving your computer — just a fast, polished interface for staying organized during your job search.

Built with plain PHP + HTML/JS. Your data stays in a local JSON file (or MySQL if you prefer).

---

## ✨ Features

- **Track applications** — company, role, status, date, job posting URL
- **Contact management** — recruiters, hiring managers, LinkedIn profiles per application
- **Communications log** — notes on every email, call, and interview
- **AI Interview Prep** — generates tailored prep guides using your own Anthropic API key (optional)
- **Resume storage** — paste your resume once, referenced automatically by AI prep
- **Statistics dashboard** — at-a-glance counts by status with click-to-filter
- **Sort & filter** — by status, date, company, contacts, communication count
- **MySQL or local mode** — runs with zero database setup using local JSON files
- **No internet required** — fully offline except for AI features

---

## 🚀 Quick Start

### Windows

1. Download or clone this repository
2. Double-click **`START_HERE.bat`**
3. Follow the prompts — it will check for PHP and offer to install it if missing
4. Your browser opens automatically at `http://127.0.0.1:8013`

### Mac / Linux

1. Download or clone this repository
2. Open Terminal in the project folder
3. Run:
   ```bash
   chmod +x START_HERE.sh
   ./START_HERE.sh
   ```
4. Follow the prompts — it will check for PHP and offer to install it via Homebrew (Mac) or apt/dnf (Linux)
5. Your browser opens automatically at `http://127.0.0.1:8013`

---

## 📋 Requirements

| Requirement | Notes |
|-------------|-------|
| **PHP 8.0+** | The only real dependency. `START_HERE` will help install it. |
| **PHP cURL extension** | Required for AI features only. Usually included by default on Mac/Linux. |
| **Modern browser** | Chrome, Firefox, Safari, Edge — any will do. |
| **MySQL** *(optional)* | Only needed if you want MySQL mode instead of local JSON files. |

> **Mac/Linux users:** PHP is often already installed. Run `php -v` in Terminal to check.
>
> **Windows users:** PHP is rarely pre-installed. `START_HERE.bat` will install it automatically via `winget` (Windows 11) or walk you through a manual install.

---

## 🗄️ Database Modes

When you first open the app, choose your storage mode:

**Local (JSON)** — recommended for most users
- Zero setup, works immediately
- Data stored in `data/jobtracker.json` next to the app files
- Back up by copying that file

**MySQL** — for users who already have MySQL running
- Requires a running MySQL server
- Enter your connection details in the app

You can migrate between modes any time using the built-in migration tool (⚙️ Settings → Migrate Data).

---

## 🤖 AI Interview Prep (Optional)

The AI feature generates a tailored interview prep guide for any application — company background, role-specific questions, STAR story angles, questions to ask them, and coaching on your contacts.

To enable it:
1. Get a free API key at [console.anthropic.com](https://console.anthropic.com)
2. Open ⚙️ Settings in the app
3. Paste your key and click **Test API Key** to verify it works
4. Open any application and click **AI Interview Prep**

Your API key is stored in your browser only — it never touches any server other than Anthropic's.

> **Cost:** The AI uses Claude Haiku, Anthropic's fastest/cheapest model. A full interview prep guide costs roughly $0.01–0.02.

---

## 📁 File Structure

```
job-application-tracker/
├── START_HERE.bat          ← Windows: double-click to launch
├── START_HERE.sh           ← Mac/Linux: run in Terminal
├── index.html              ← The entire frontend UI
├── api.php                 ← Backend API (PHP)
├── interview_tips_generator.php  ← Standalone tips utility
├── README.md               ← This file
├── WINDOWS_SETUP.md        ← Detailed Windows setup guide
├── setup-helper.ps1        ← PowerShell setup helper (advanced)
└── data/                   ← Created automatically on first run
    └── jobtracker.json     ← Your application data (gitignored)
```

---

## 🔒 Privacy

- All data is stored **locally on your machine**
- Nothing is sent to any server except:
  - Anthropic's API (only when you use AI features, with your own key)
  - `curl.se` (one-time download of SSL certificates on Windows, if needed)
- The app runs on `localhost` — it is not accessible from other devices

---

## 🛠️ Troubleshooting

**Browser doesn't open / can't reach the app**
- Make sure PHP is installed: run `php -v` in Terminal/Command Prompt
- Check that port 8013 isn't used by something else
- Try opening `http://127.0.0.1:8013` manually

**AI Test shows "cURL error: SSL certificate problem"**
- This is a known Windows issue. Click **Test API Key** again — the app auto-downloads a certificate fix on first use.

**AI shows "API key invalid"**
- Double-check your key at [console.anthropic.com](https://console.anthropic.com)
- Make sure your Anthropic account has a payment method and available credits

**Port already in use**
- Edit `START_HERE.bat` or `START_HERE.sh` and change `PORT=8013` to any other port (e.g. `8014`)

**Data directory not writable (Linux)**
- Run: `chmod 755 data/` from the project folder

---

## 🤝 Contributing

Pull requests welcome. Some ideas for contributions:
- Chrome/Firefox extension for one-click job capture from job boards
- Export to CSV / Google Sheets
- Email integration for auto-logging recruiter communications
- Dark/light theme toggle

---

## 📄 License

MIT License — free to use, modify, and distribute.

---

*Built for job seekers, by a job seeker. Good luck out there. 🎯*
