// netlify/functions/send.js
// Serverless mail sender for Netlify using Nodemailer (SMTP)
const qs = require('querystring');
const nodemailer = require('nodemailer');

exports.handler = async (event) => {
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'POST, OPTIONS'
  };

  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 200, headers, body: '' };
  }
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, headers, body: 'Method Not Allowed' };
  }

  // Decode body (Netlify may base64-encode)
  let raw = event.body || '';
  if (event.isBase64Encoded) {
    raw = Buffer.from(raw, 'base64').toString('utf8');
  }

  // Parse urlencoded or JSON
  let data = {};
  const ctype = (event.headers['content-type'] || event.headers['Content-Type'] || '').toLowerCase();
  try {
    if (ctype.includes('application/json')) {
      data = JSON.parse(raw || '{}');
    } else {
      data = qs.parse(raw || '');
    }
  } catch (e) {
    return { statusCode: 400, headers, body: 'Bad Request' };
  }

  // Honeypot + basic timing anti-bot
  if (data.hpcheck) {
    return {
      statusCode: 302,
      headers: { ...headers, Location: '/success.html' },
      body: ''
    };
  }
  const now = Date.now();
  const ts = parseInt(data.ts, 10);
  if (ts && (now - ts) < 1200) { // require at least 1.2s
    return { statusCode: 429, headers, body: 'Bitte erneut versuchen.' };
  }

  // helper
  const field = (name) => {
    const v = data[name] ?? data[`${name}[]`];
    if (Array.isArray(v)) return v.join(', ').trim();
    return (v || '').toString().trim();
  };

  // Collect fields from buchen.html
  const vorname    = field('vorname');
  const nachname   = field('nachname');
  const einrichtung= field('einrichtung');
  const rolle      = field('rolle');
  const email      = field('email');
  const telefon    = field('telefon');
  const einsatzort = field('einsatzort');
  const beginn     = field('beginn');
  const ende       = field('ende');
  const schicht    = field('schicht');
  const honorar    = field('honorar');
  const nachricht  = field('nachricht');

  // Validate minimal fields
  const missing = [];
  if (!vorname) missing.push('Vorname');
  if (!nachname) missing.push('Nachname');
  if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) missing.push('E‑Mail');
  if (!telefon) missing.push('Telefon');
  if (!einsatzort) missing.push('Einsatzort');
  if (!beginn) missing.push('Einsatzbeginn');

  if (missing.length) {
    return { statusCode: 422, headers, body: 'Bitte prüfen: ' + missing.join(', ') };
  }

  // Build mail
  const TO   = process.env.MAIL_TO   || process.env.SMTP_USER;
  const FROM = process.env.MAIL_FROM || `Website <no-reply@${(process.env.DOMAIN || 'bordea-pflege.de')}>`;

  const subject = `Neue Buchungsanfrage – ${vorname} ${nachname}`;
  const lines = [
    "Buchungsanfrage über bordea-pflege.de",
    "",
    `Vorname: ${vorname}`,
    `Nachname: ${nachname}`,
    `Einrichtung: ${einrichtung}`,
    `Rolle: ${rolle || '-'}`,
    `E-Mail: ${email}`,
    `Telefon: ${telefon}`,
    `Einsatzort: ${einsatzort}`,
    `Beginn: ${beginn}`,
    `Ende: ${ende || '-'}`,
    `Schicht: ${schicht || '-'}`,
    `Budget: ${honorar || '-'}`,
    "",
    "Nachricht:",
    nachricht || '-'
  ];
  const text = lines.join('\n');

  // SMTP transport
  const transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST || 'smtp.hostinger.com',
    port: Number(process.env.SMTP_PORT || 465),
    secure: (process.env.SMTP_SECURE || 'true') !== 'false', // default true
    auth: {
      user: process.env.SMTP_USER,
      pass: process.env.SMTP_PASS
    }
  });

  try {
    await transporter.verify();
    await transporter.sendMail({
      from: FROM,
      to: TO,
      subject,
      text,
      replyTo: email
    });
    return {
      statusCode: 302,
      headers: { ...headers, Location: '/success.html' },
      body: ''
    };
  } catch (err) {
    console.error('Mail error:', err);
    return { statusCode: 500, headers, body: 'Nachricht konnte nicht gesendet werden. Bitte senden Sie direkt an ' + TO };
  }
};
