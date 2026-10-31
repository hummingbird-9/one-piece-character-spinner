# One Piece Character Builder — Render-ready

This package preserves the supplied Windows-98-like spinner and changes its JSON-file-based community API to PostgreSQL.

## Important changes

- One active vote per post per browser identity, enforced by the PostgreSQL `votes` primary key. You can switch like/dislike; repeating a vote doesn't add votes.
- Delete only your own posts. Visitor IDs are random and stored in browser localStorage, so this works in a cross-origin iframe without third-party cookies.
- Community posts, comments, votes survive Render web-service sleeps/redeployments because they're stored in an external PostgreSQL database.
- Escapes user-generated content when displaying posts and validates input lengths. Rate limits posts and comments per anonymous browser identity.
- Preserves character wheels, conditional skips, downloads, and Windows 98 styling.

**Limitations:** Anonymous identity isn't a real account. Someone clearing site storage, using a different browser, or generating new IDs can bypass one-vote-per-visitor; for stronger anti-bot protection add accounts or CAPTCHA, IP-aware rate limiting, and moderation. People who clear storage also lose the ability to delete their earlier posts. Wheel outcomes still happen in client-side code and can be manipulated by determined users. A PHP server alone cannot make the spins verifiable. No automated chat pruning: external storage is used instead of erasing chat after 200KB.

## Deploy on Render

1. Create a GitHub repo (e.g. `hummingbird-9/OnePieceCharacterBuilder`) and put `Dockerfile`, `index.php`, `backend.php`, `render.yaml`, and `.dockerignore` at its root.
2. Create a PostgreSQL database with an external provider that supports long-lived database storage (e.g. [Neon](https://neon.tech)). In its dashboard, use **Connect** to copy your PostgreSQL connection URI. Keep the URI private; never commit it to GitHub.
3. On [Render](https://dashboard.render.com/), choose **New > Web Service**, select that GitHub repo, choose Docker runtime and Free instance. If using `render.yaml` via Blueprint, it creates the matching web service.
4. In Render service **Environment**, set `DATABASE_URL` to the copied `postgresql://...` URI and `COOKIE_SECRET` to a long randomly generated secret (32+ characters). If deploying using Blueprint, `COOKIE_SECRET` is generated automatically. You can generate one in Linux with `openssl rand -hex 32`.
5. Deploy. The PHP API initializes the required tables on its first database request. Test `https://YOUR-SERVICE.onrender.com/?action=health` for `{"status":"ok"}` and the main page for chat loading.
6. **Don't put the database connection URL or cookie secret in frontend code.**

Render's Free web services spin down on idle, meaning first-load delay. Free services have nonpersistent local files. The external database is required to preserve your chat.

## Embed in your main website

On `hummingbird-9.github.io`, put something like the following in a page such as `onepiece.html` (replace the example hostname with the real Render service URL):

```html
<!doctype html>
<html><head><meta charset="utf-8"><title>One Piece Character Builder</title>
<style>html,body{height:100%;margin:0;background:#808080}iframe{width:100%;height:100%;border:0}</style>
</head><body>
<iframe src="https://YOUR-SERVICE.onrender.com/" title="One Piece Character Builder"
  allow="fullscreen" loading="eager"></iframe>
</body></html>
```

In your gallery, add a link/button that opens `/onepiece.html` (or load the Render URL in an iframe directly). Keep this app on Render rather than loading the PHP source through jsDelivr; CDN/static hosting cannot run PHP.

## Git commands on Arch Linux

After extracting this ZIP and creating an empty repo on GitHub:

```bash
cd ~/Downloads/onepiece-render-ready  # adjust to your actual extraction folder

git init
git add .
git commit -m 'Deploy One Piece spinner with persistent community and single votes'
git branch -M main
gh repo create hummingbird-9/OnePieceCharacterBuilder --public --source=. --remote=origin --push
```

If the remote repo already exists, clone it and copy these files into its root, then use normal `git add -A`, `git commit`, `git push`.

## Smoke tests

- Create and post a character; refresh; post is still there.
- Like once, refresh and click like again. The number must not increase. Click dislike: the vote switches rather than creating a second vote.
- Try deleting a different visitor's post: the server rejects it.
- Test directly on Render, then from inside your GitHub Pages iframe.

PHP lint and JS syntax checks were performed locally. Full online deployment and live PostgreSQL integration have not been run in this environment.
