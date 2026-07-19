import { getDb } from '../database.js';
import { cleanString, nowSql, requireString } from './shared.js';

function validationError(message) {
  const error = new Error(message);
  error.status = 422;
  return error;
}

function notFound() {
  const error = new Error('Not found');
  error.status = 404;
  return error;
}

function publicClient(row) {
  return {
    id: row.id,
    name: row.name,
    slug: row.slug,
    contactEmail: row.contact_email,
  };
}

function publicSlot(row) {
  const startsAt = new Date(`${row.starts_at.replace(' ', 'T')}`);
  const endsAt = new Date(`${row.ends_at.replace(' ', 'T')}`);
  const durationMinutes = Number.isFinite(startsAt.getTime()) && Number.isFinite(endsAt.getTime())
    ? Math.max(0, Math.round((endsAt.getTime() - startsAt.getTime()) / 60000))
    : null;

  return {
    id: row.id,
    title: row.title,
    startsAt: row.starts_at,
    endsAt: row.ends_at,
    durationMinutes,
    location: row.location,
  };
}

function publicAppointment(row) {
  return {
    id: row.id,
    name: row.name,
    email: row.email,
    phone: row.phone,
    company: row.company,
    status: row.status,
    bookedAt: row.booked_at,
    slot: {
      title: row.title,
      startsAt: row.starts_at,
      endsAt: row.ends_at,
      location: row.location,
    },
  };
}

function findClientBySlug(slug) {
  const client = getDb().prepare(`
    SELECT id, name, slug, contact_email
    FROM clients
    WHERE slug = @slug AND is_active = 1
    LIMIT 1
  `).get({ slug: cleanString(slug) });

  if (!client) {
    throw notFound();
  }

  return client;
}

export function publicBookingPage(slug) {
  const client = findClientBySlug(slug);
  const now = nowSql();
  const slots = getDb().prepare(`
    SELECT booking_availabilities.id, booking_availabilities.title, booking_availabilities.starts_at,
           booking_availabilities.ends_at, booking_availabilities.location
    FROM booking_availabilities
    LEFT JOIN booking_appointments
      ON booking_appointments.booking_availability_id = booking_availabilities.id
    WHERE booking_availabilities.client_id = @clientId
      AND booking_availabilities.status = 'available'
      AND booking_availabilities.starts_at > @now
      AND booking_appointments.id IS NULL
    ORDER BY booking_availabilities.starts_at ASC
  `).all({ clientId: client.id, now });

  return {
    client: publicClient(client),
    slots: slots.map(publicSlot),
  };
}

export function bookPublicSlot(slug, payload) {
  const client = findClientBySlug(slug);
  const slotId = Number.parseInt(String(payload.booking_availability_id || ''), 10);

  if (!Number.isFinite(slotId) || slotId < 1) {
    throw validationError('Choose an available meeting time.');
  }

  const name = requireString(payload.name, 'Name');
  const email = requireString(payload.email, 'Email').toLowerCase();

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    throw validationError('Enter a valid email address.');
  }

  const now = nowSql();

  return getDb().transaction(() => {
    const slot = getDb().prepare(`
      SELECT booking_availabilities.*
      FROM booking_availabilities
      LEFT JOIN booking_appointments
        ON booking_appointments.booking_availability_id = booking_availabilities.id
      WHERE booking_availabilities.id = @slotId
        AND booking_availabilities.client_id = @clientId
        AND booking_availabilities.status = 'available'
        AND booking_availabilities.starts_at > @now
        AND booking_appointments.id IS NULL
      LIMIT 1
    `).get({ slotId, clientId: client.id, now });

    if (!slot) {
      throw validationError('That meeting time is no longer available.');
    }

    const insert = getDb().prepare(`
      INSERT INTO booking_appointments (
        client_id, booking_availability_id, name, email, phone, company, notes, status, booked_at, created_at, updated_at
      ) VALUES (
        @clientId, @slotId, @name, @email, @phone, @company, @notes, 'booked', @now, @now, @now
      )
    `).run({
      clientId: client.id,
      slotId,
      name,
      email,
      phone: cleanString(payload.phone),
      company: cleanString(payload.company),
      notes: cleanString(payload.notes, 2000),
      now,
    });

    getDb().prepare(`
      UPDATE booking_availabilities
      SET status = 'booked', updated_at = @now
      WHERE id = @slotId
    `).run({ slotId, now });

    const appointment = getDb().prepare(`
      SELECT booking_appointments.*, booking_availabilities.title, booking_availabilities.starts_at,
             booking_availabilities.ends_at, booking_availabilities.location
      FROM booking_appointments
      LEFT JOIN booking_availabilities
        ON booking_availabilities.id = booking_appointments.booking_availability_id
      WHERE booking_appointments.id = @id
      LIMIT 1
    `).get({ id: insert.lastInsertRowid });

    return {
      client: publicClient(client),
      appointment: publicAppointment(appointment),
    };
  })();
}

export function publicBookingConfirmation(slug, appointmentId) {
  const client = findClientBySlug(slug);
  const appointment = getDb().prepare(`
    SELECT booking_appointments.*, booking_availabilities.title, booking_availabilities.starts_at,
           booking_availabilities.ends_at, booking_availabilities.location
    FROM booking_appointments
    LEFT JOIN booking_availabilities
      ON booking_availabilities.id = booking_appointments.booking_availability_id
    WHERE booking_appointments.client_id = @clientId
      AND booking_appointments.id = @appointmentId
    LIMIT 1
  `).get({
    clientId: client.id,
    appointmentId: Number.parseInt(String(appointmentId), 10),
  });

  if (!appointment) {
    throw notFound();
  }

  return {
    client: publicClient(client),
    appointment: publicAppointment(appointment),
  };
}
