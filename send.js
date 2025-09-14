const qs = require('querystring');
const nodemailer = require('nodemailer');

exports.handler = async (event) => {
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'POST, OPTIONS'
  };
  if (event.httpMethod === 'OPTIONS') return { statusCode: 200, headers, body: '' };
  if (event.httpMethod !== 'POST')   return { statusCode: 405, headers, body: 'Method Not Allowed' };

  let raw = event.body || '';
  if (event.isBase64Encoded) raw = Buffer.from(raw, 'base64').toString('utf8');

  let data = {};
  const ct = (event.headers['content-type'] || event.headers['Content-Type'] || '').toLowerCase();
  try { data = ct.includes('application/json') ? JSON.parse(raw||'{}') : qs.parse(raw||''); }
  catch { return { statusCode: 400, headers, body: 'Bad Request' }; }

  // Anti-spam simplu
  if (data.hpcheck) return { statusCode: 302, headers: { ...headers, Location: '/success.html' }, body: '' };
  const ts = parseInt(data.ts || '0', 10);
  if (ts && Date.now() - ts < 1200) return { statusCode: 429, headers, body: 'Bitte erneut versuchen.' };

  const f = (n) => (Array.isArray(data[n]) ? data[n].join(', ') : (data[n]||'')).toString().trim();

  const subject = `Neue Buchungsanfrage – ${f('vorname')} ${f('nachname')}`;
  const text = [
    'Buchungsanfrage über bordea-pflege.de','',
    `Vorname: ${f('vorname')}`,
    `Nachname: ${f('nachname')}`,
    `Einrichtung: ${f('einrichtung')}`,
    `Rolle: ${f('rolle')||'-'}`,
    `E-Mail: ${f('email')}`,
    `Telefon: ${f('telefon')}`,
    `Einsatzort: ${f('einsatzort')}`,
    `Beginn: ${f('beginn')}`,
    `Ende: ${f('ende')||'-'}`,
    `Schicht: ${f('schicht')||'-'}`,
    `Budget: ${f('honorar')||'-'}`,'',
    'Nachricht:', f('nachricht')||'-'
  ].join('\n');

  const transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST || 'smtp.hostinger.com',
    port: Number(process.env.SMTP_PORT || 465),
    secure: (process.env.SMTP_SECURE || 'true') !== 'false',
    auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
  });

  try {
    await transporter.verify();
    await transporter.sendMail({
      from: process.env.MAIL_FROM || `Website <no-reply@${process.env.DOMAIN||'bordea-pflege.de'}>`,
      to:   process.env.MAIL_TO   || process.env.SMTP_USER,
      subject, text, replyTo: f('email')
    });
    return { statusCode: 302, headers: { ...headers, Location: '/success.html' }, body: '' };
  } catch (e) {
    console.error('Mail error:', e);
    return { statusCode: 500, headers, body: 'Nachricht konnte nicht gesendet werden.' };
  }
};
