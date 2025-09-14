
NETLIFY MAIL SETUP (Serverless Function)

1) Set environment variables in Netlify (Site settings → Build & deploy → Environment):
   SMTP_HOST=smtp.hostinger.com
   SMTP_PORT=465
   SMTP_SECURE=true
   SMTP_USER=fachkraft@bordea-pflege.de
   SMTP_PASS=PAROLA-CASEI
   MAIL_TO=fachkraft@bordea-pflege.de
   MAIL_FROM=Website <no-reply@bordea-pflege.de>
   DOMAIN=bordea-pflege.de

2) Ensure form action in buchen.html points to /.netlify/functions/send (this zip already contains it).

3) Deploy to Netlify:
   - If you use Git, commit these files and push.
   - If you drag & drop, upload the WHOLE folder (including netlify/ and netlify.toml and package.json).

4) Netlify will install nodemailer and build functions automatically.

5) Test: open /buchen.html, fill form, submit. You should get a mail to MAIL_TO.
