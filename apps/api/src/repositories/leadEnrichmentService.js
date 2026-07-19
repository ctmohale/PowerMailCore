import dns from 'node:dns/promises';
import net from 'node:net';
import { config } from '../config.js';

const DIRECTORY_DOMAINS = new Set([
  'facebook.com', 'linkedin.com', 'instagram.com', 'x.com', 'twitter.com',
  'google.com', 'youtube.com', 'yelp.com', 'yellowpages.com', 'snupit.co.za',
  'brabys.co.za', 'africabizinfo.com', 'cybo.com', 'hotfrog.com', 'cylex.net.za',
]);

const REJECT_EMAIL_PARTS = [
  'example@', 'test@', 'noreply@', 'no-reply@', 'donotreply@', 'yourname@',
  'name@', 'email@', '@domain.com', '.png', '.jpg', '.jpeg', '.webp', '.svg',
];

const PRIMARY_PREFIXES = ['info@', 'enquiries@', 'reception@', 'contact@', 'hello@', 'office@', 'admin@', 'accounts@'];
const CONTACT_WORDS = ['contact', 'contact-us', 'about', 'about-us', 'team', 'people', 'locations', 'get-in-touch'];

function normalizeCompany(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function validEmail(value) {
  const email = String(value || '').trim().toLowerCase();
  return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(email)
    && !REJECT_EMAIL_PARTS.some((part) => email.includes(part));
}

function extractEmails(html) {
  const decoded = String(html || '')
    .replace(/&#64;|&commat;/gi, '@')
    .replace(/&#46;|&period;/gi, '.');
  return [...new Set(decoded.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi) || [])]
    .map((email) => email.replace(/[.,;:]+$/, '').toLowerCase())
    .filter(validEmail);
}

function extractPhones(html) {
  return [...new Set(String(html || '').match(/(?:\+27|0)\d{2}[\s().-]?\d{3}[\s.-]?\d{4}/g) || [])]
    .map((phone) => phone.trim());
}

function primaryEmail(emails, website) {
  const unique = [...new Set(emails.filter(validEmail))];
  if (!unique.length) return '';
  const host = new URL(website).hostname.replace(/^www\./, '').toLowerCase();
  const sameDomain = unique.filter((email) => {
    const domain = email.split('@')[1];
    return domain === host || domain.endsWith(`.${host}`);
  });

  for (const prefix of PRIMARY_PREFIXES) {
    const match = sameDomain.find((email) => email.startsWith(prefix));
    if (match) return match;
  }

  return sameDomain[0] || unique[0];
}

function privateAddress(address) {
  if (net.isIPv4(address)) {
    const [a, b] = address.split('.').map(Number);
    return a === 10 || a === 127 || a === 0 || (a === 169 && b === 254)
      || (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168);
  }

  const value = address.toLowerCase();
  return value === '::1' || value === '::' || value.startsWith('fc') || value.startsWith('fd') || value.startsWith('fe80:');
}

async function publicUrl(value) {
  let url;
  try {
    url = new URL(value);
  } catch {
    return null;
  }

  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return null;
  const host = url.hostname.replace(/^www\./, '').toLowerCase();
  if (host === 'localhost' || DIRECTORY_DOMAINS.has(host) || [...DIRECTORY_DOMAINS].some((domain) => host.endsWith(`.${domain}`))) return null;

  try {
    const addresses = await dns.lookup(url.hostname, { all: true });
    if (!addresses.length || addresses.some(({ address }) => privateAddress(address))) return null;
  } catch {
    return null;
  }

  return url;
}

async function fetchPage(value, redirects = 0) {
  const url = await publicUrl(value);
  if (!url || redirects > 3) return null;

  try {
    const response = await fetch(url, {
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Accept-Language': 'en-ZA,en;q=0.9',
        'User-Agent': 'Mozilla/5.0 (compatible; PowerMailLeadResearch/1.0)',
      },
      redirect: 'manual',
      signal: AbortSignal.timeout(8000),
    });

    if (response.status >= 300 && response.status < 400) {
      const location = response.headers.get('location');
      return location ? fetchPage(new URL(location, url).toString(), redirects + 1) : null;
    }

    if (!response.ok || !String(response.headers.get('content-type') || '').includes('text/html')) return null;
    return { url: response.url || url.toString(), html: (await response.text()).slice(0, 250000) };
  } catch {
    return null;
  }
}

function pageTitle(html) {
  return String(html || '').match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1]
    ?.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() || '';
}

function candidateUrls(company) {
  const generic = new Set(['pty', 'ltd', 'inc', 'incorporated', 'the', 'and', 'services', 'group', 'south', 'africa']);
  const words = String(company || '').toLowerCase().replace(/\([^)]+\)/g, ' ')
    .replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(Boolean);
  const significant = words.filter((word) => word.length > 1 && !generic.has(word));
  const slugs = [...new Set([
    words.join(''),
    significant.join(''),
    significant.slice(0, 2).join(''),
    significant.map((word) => word[0]).join(''),
    significant[0],
  ].filter((slug) => slug?.length >= 2))];

  return slugs.flatMap((slug) => ['co.za', 'com', 'africa'].flatMap((tld) => [
    `https://${slug}.${tld}`,
    `https://www.${slug}.${tld}`,
  ])).slice(0, 24);
}

function scoreCandidate(lead, page) {
  const title = pageTitle(page.html).toLowerCase();
  const body = page.html.toLowerCase();
  const words = String(lead.company || '').toLowerCase().split(/\s+/).filter((word) => word.length > 3);
  const phone = String(lead.phone || '').replace(/\D/g, '');
  let score = words.reduce((sum, word) => sum + (title.includes(word) ? 10 : body.includes(word) ? 3 : 0), 0);
  if (phone && body.replace(/\D/g, '').includes(phone)) score += 35;
  if (new URL(page.url).hostname.endsWith('.co.za')) score += 5;
  return score;
}

async function guessedWebsite(lead) {
  const pages = await Promise.all(candidateUrls(lead.company).map(fetchPage));
  const ranked = pages.filter(Boolean).map((page) => ({ page, score: scoreCandidate(lead, page) }))
    .sort((a, b) => b.score - a.score);
  return ranked[0]?.score >= 18 ? ranked[0].page.url : '';
}

function responseText(payload) {
  if (payload.output_text) return payload.output_text;
  for (const item of payload.output || []) {
    for (const content of item.content || []) {
      if (content.type === 'output_text' && content.text) return content.text;
    }
  }
  return '';
}

async function searchBatch(leads) {
  if (!config.openai.key) return [];

  const companies = leads.map((lead) => ({
    company: lead.company,
    location: lead.address_or_location || '',
    phone: lead.phone || '',
  }));
  const schema = {
    type: 'object',
    additionalProperties: false,
    properties: {
      companies: {
        type: 'array',
        items: {
          type: 'object',
          additionalProperties: false,
          properties: {
            company: { type: 'string' },
            website: { type: 'string' },
            email: { type: 'string' },
            phone: { type: 'string' },
            notes: { type: 'string' },
          },
          required: ['company', 'website', 'email', 'phone', 'notes'],
        },
      },
    },
    required: ['companies'],
  };

  const response = await fetch(`${config.openai.baseUrl}/responses`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${config.openai.key}`, 'Content-Type': 'application/json' },
    signal: AbortSignal.timeout(120000),
    body: JSON.stringify({
      model: config.openai.model,
      store: false,
      tools: [{ type: 'web_search', user_location: { type: 'approximate', country: 'ZA' } }],
      input: `Find the official website and public business contact details for every South African company below. Avoid directories and social media. Return an empty string when a value cannot be verified.\n${JSON.stringify(companies)}`,
      text: { format: { type: 'json_schema', name: 'company_enrichment', strict: true, schema } },
    }),
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error?.message || `OpenAI web search failed with ${response.status}.`);
  const text = responseText(payload).replace(/^```(?:json)?\s*|\s*```$/g, '');
  return JSON.parse(text).companies || [];
}

function contactUrls(baseUrl, html) {
  const base = new URL(baseUrl);
  const urls = [];
  const pattern = /<a\b[^>]*href=["']([^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi;
  let match;
  while ((match = pattern.exec(html)) && urls.length < 8) {
    const text = `${match[1]} ${match[2].replace(/<[^>]+>/g, ' ')}`.toLowerCase();
    if (!CONTACT_WORDS.some((word) => text.includes(word))) continue;
    try {
      const url = new URL(match[1], base);
      if (url.hostname === base.hostname && !urls.includes(url.toString())) urls.push(url.toString());
    } catch {
      // Ignore malformed links in third-party websites.
    }
  }
  return [...new Set([...urls, ...CONTACT_WORDS.slice(0, 5).map((path) => new URL(`/${path}`, base).toString())])].slice(0, 3);
}

async function crawlWebsite(value) {
  const homepage = await fetchPage(value);
  if (!homepage) return null;
  const pages = [homepage];
  for (const url of contactUrls(homepage.url, homepage.html)) {
    const page = await fetchPage(url);
    if (page) pages.push(page);
    if (pages.length >= 3) break;
  }

  const html = pages.map((page) => page.html).join('\n');
  const image = homepage.html.match(/<meta[^>]+(?:property|name)=["']og:image["'][^>]+content=["']([^"']+)/i)?.[1] || '';
  return {
    website: homepage.url,
    emails: extractEmails(html),
    phones: extractPhones(html),
    image_url: image ? new URL(image, homepage.url).toString() : '',
    pages: pages.map((page) => page.url),
  };
}

export async function enrichLeadRecords(leads) {
  const found = [];
  for (let index = 0; index < leads.length; index += 8) {
    try {
      found.push(...await searchBatch(leads.slice(index, index + 8)));
    } catch (error) {
      found.push(...leads.slice(index, index + 8).map((lead) => ({ company: lead.company, notes: error.message })));
    }
  }
  const lookup = new Map(found.map((item) => [normalizeCompany(item.company), item]));
  const enriched = [];

  for (const lead of leads) {
    const searched = lookup.get(normalizeCompany(lead.company)) || {};
    let website = searched.website || lead.source_url || '';
    let crawled = website ? await crawlWebsite(website) : null;
    if (!crawled) {
      website = await guessedWebsite(lead);
      crawled = website ? await crawlWebsite(website) : null;
    }

    const emails = [...(crawled?.emails || []), searched.email || '', lead.email || ''].filter(validEmail);
    const phone = lead.phone || searched.phone || crawled?.phones?.[0] || '';
    enriched.push({
      ...lead,
      email: crawled ? primaryEmail(emails, crawled.website) : emails[0] || '',
      phone,
      source_url: crawled?.website || website || null,
      website: crawled?.website || website || null,
      logo_url: crawled?.image_url || '',
      enrichment_status: crawled ? (emails.length ? 'verified_email_found' : 'website_found_no_email') : 'no_website_found',
      notes: [lead.notes, searched.notes, crawled?.pages?.length ? `Crawled: ${crawled.pages.join(', ')}` : ''].filter(Boolean).join('\n').slice(0, 4000),
    });
  }

  return enriched;
}
