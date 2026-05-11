# DVCSHOW Editor — Deployment Guide

A WYSIWYG editor for the site. Hand `/editor.html` + a password to a non-technical user;
they can change any text, swap any image, edit links, add/remove past events,
and click **Save & Publish** to push changes to the live site.

Edits are written directly to `index.html`. The previous 20 versions are automatically backed up.

---

## What gets uploaded

| File                        | Purpose                                              |
| --------------------------- | ---------------------------------------------------- |
| `index.html`                | The live site (editor overwrites this on save)       |
| `editor.html`               | Editor UI                                            |
| `editor-api.php`            | Backend (save / upload / restore)                    |
| `editor-config.example.php` | Template — copy to `editor-config.php` and edit      |
| `Images/`                   | Image folder (must be writable by web server)        |

**Do NOT upload** `editor-config.php` from your laptop — generate the password hash on the VPS itself (see step 4).

---

## One-time setup on the VPS

SSH in:
```bash
ssh julian@160.153.187.85
```

### 1. Install PHP-FPM (if not already installed)

Check first:
```bash
php -v
systemctl status php*-fpm
```

If neither command works, install on Ubuntu/Debian:
```bash
sudo apt update
sudo apt install -y php-fpm php-gd
sudo systemctl enable --now php8.1-fpm   # adjust version: php8.3-fpm, php7.4-fpm, etc.
```

Note the exact PHP-FPM service name (`php8.1-fpm`, `php8.3-fpm`, …) — you'll need it below.

### 2. Make sure nginx can run PHP and write files

Find your nginx site config:
```bash
sudo ls /etc/nginx/sites-available/
sudo nano /etc/nginx/sites-available/default   # or the correct file
```

Inside the `server { }` block that serves your site, ensure these blocks exist:

```nginx
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;   # match installed version
}

# Block direct access to config & backups from the web
location ~ /editor-config\.php$        { return 404; }
location ~ /editor-config\.example\.php$ { return 404; }
location ~ /\.editor-backups/          { return 404; }
```

Test and reload:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 3. Give the web server write access to the site folder

The PHP process (usually running as `www-data`) needs to write to `index.html`,
`Images/`, and create `.editor-backups/`. The cleanest pattern:

```bash
# replace 'julian' with your user, 'www-data' is the typical PHP-FPM user
sudo chown -R julian:www-data ~/html
sudo chmod -R u+rwX,g+rwX ~/html
sudo find ~/html -type d -exec chmod g+s {} \;   # new files inherit group
```

This lets both you (julian) and the web server (www-data) write to the folder.

### 4. Upload the editor files

From your **local machine** (PowerShell in the project folder):
```powershell
scp editor.html editor-api.php editor-config.example.php julian@160.153.187.85:~/html/
```

### 5. Generate a password and create the config

SSH back in:
```bash
cd ~/html
cp editor-config.example.php editor-config.php
php -r "echo password_hash('PUT-YOUR-REAL-PASSWORD-HERE', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the output (starts with `$2y$10$…`). Edit the config:
```bash
nano editor-config.php
```
Replace the placeholder hash with what you generated. Save (Ctrl+O, Enter, Ctrl+X).

Lock it down so other users on the VPS can't read it:
```bash
chmod 640 editor-config.php
sudo chown julian:www-data editor-config.php
```

### 6. Test

In a browser, visit:
```
https://yoursite.com/editor.html
```

Sign in with the password you set. Try clicking a heading and editing it.
If you click **Save & Publish** and reload the live site, you should see the change.

---

## Security notes

- **Use HTTPS.** Without it, the password travels in plaintext. If you don't have
  HTTPS yet, install certbot and get a free Let's Encrypt cert:
  ```bash
  sudo apt install certbot python3-certbot-nginx
  sudo certbot --nginx -d yoursite.com
  ```
- **Pick a strong password** (16+ chars, random). The editor lets anyone with the
  password change anything on the site or upload images.
- **`editor-config.php` permissions** matter — `640` + group `www-data` keeps
  it readable only by you and the web server.
- **The editor has zero rate limiting** beyond a 150ms delay per attempt. That's
  enough to slow scripted brute-force, but if you want more, add nginx-level rate
  limiting on `/editor-api.php`.
- **Backups live at `~/html/.editor-backups/`** and are blocked from web access
  by the nginx rule in step 2. Make sure that rule is in place.

---

## Updating (if you change the editor code later)

Same as initial upload:
```powershell
scp editor.html editor-api.php julian@160.153.187.85:~/html/
```
(Do not overwrite `editor-config.php` — it has your password hash.)

---

## Troubleshooting

**"editor-config.php not found"** — you skipped step 5 or named the file wrong.

**"Cannot replace index.html. Check folder permissions."** — step 3 didn't take. Run:
```bash
ls -l ~/html/index.html
# owner should include www-data (group) and have w permission
```

**Editor loads but Save returns "Invalid password"** — the hash in
`editor-config.php` doesn't match what you typed. Regenerate it (step 5).

**"500 Internal Server Error" on any editor-api call** — check nginx error log:
```bash
sudo tail -f /var/log/nginx/error.log
# and PHP-FPM log:
sudo tail -f /var/log/php8.1-fpm.log
```

**Images upload OK but don't appear after save** — usually a permissions issue
on `Images/`. Make sure it's group-writable by www-data (step 3).
