/**
 * Gestione Scarto Librario - Frontend React
 * Version: 9.4.8
 *
 * v8.8.0 Changes:
 * - Added privacy policy link display on public page
 * - Support for configurable rate limits and retention settings
 *
 * v8.7.1 Changes:
 * - Fixed auto-refresh bug: increased interval from 30s to 5 minutes
 * - Added input detection to prevent refresh while user is typing
 * - Updated settings API to use session-based authentication
 * - Skips refresh when modals are open
 */
import React, { startTransition, useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';

// ============================================================================
// CONFIGURAZIONE API WORDPRESS
// ============================================================================
interface RuntimeSettings {
    root?: string;
    isAdmin?: boolean;
    adminPage?: 'reservations' | 'catalog' | 'create-reservation';
    nonce?: string;
    publicUrl?: string;
}

const rootElement = document.getElementById('scarto-librario-root');
const readEmbeddedSettings = (): RuntimeSettings => {
    const encoded = rootElement?.dataset.scartoSettings;
    if (!encoded) return {};
    try {
        return JSON.parse(encoded) as RuntimeSettings;
    } catch {
        return {};
    }
};
const runtimeSettings: RuntimeSettings = (window as any).scartoSettings || readEmbeddedSettings();
const IS_WP_ADMIN = __SCARTO_ADMIN__;
const ADMIN_PAGE = runtimeSettings.adminPage || 'reservations';

const getApiUrl = () => runtimeSettings.root
    ? runtimeSettings.root + 'scarto/v1'
    : '/wp-json/scarto/v1';

const getHeaders = (_protectedRequest = false) => {
    const headers: HeadersInit = { 'Content-Type': 'application/json' };
    if (IS_WP_ADMIN && runtimeSettings.nonce) {
        headers['X-WP-Nonce'] = runtimeSettings.nonce;
    }
    return headers;
};

const API_URL = getApiUrl();

// ============================================================================
// DEFINIZIONI TIPI
// ============================================================================
interface Book {
    id: string;
    scatola?: string | number;
    autore: string;
    titolo: string;
    editore: string;
    anno: string | number;
    inventario: string;
    collocazione?: string;
    stato?: string;
    motivazioni?: string;
    note?: string;
    _reserved?: boolean;
    _delivered?: boolean;
    _availability?: 'available' | 'reserved' | 'delivered';
    reservedUntil?: number;
}

interface ImportResult {
    success: boolean;
    count: number;
    inserted: number;
    updated: number;
    deleted: number;
}

interface PreparedImport {
    books: Book[];
    warnings: string[];
}

const spreadsheetValue = (row: Map<string, unknown>, aliases: string[]): string => {
    for (const alias of aliases) {
        const value = row.get(alias.toLocaleLowerCase('it-IT'));
        if (value !== undefined && String(value).trim() !== '') return String(value).trim();
    }
    return '';
};

const stableRowHash = (value: string): string => {
    let hash = 2166136261;
    for (let index = 0; index < value.length; index++) {
        hash ^= value.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(36).padStart(7, '0');
};

const prepareCatalogFile = async (file: File): Promise<PreparedImport> => {
    const XLSX = await import('xlsx');
    // Read at most one row beyond the supported limit so oversized sheets fail predictably.
    const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', sheetRows: 50001 });
    const firstSheetName = workbook.SheetNames[0];
    if (!firstSheetName || !workbook.Sheets[firstSheetName]) {
        throw new Error('Il file non contiene alcun foglio leggibile.');
    }

    const rows = XLSX.utils.sheet_to_json<Record<string, unknown>>(workbook.Sheets[firstSheetName], {
        defval: '',
        raw: false
    });
    if (rows.length === 0) throw new Error('Il foglio Excel non contiene righe di catalogo.');
    if (rows.length > 50000) throw new Error('Il file supera il limite di 50.000 righe.');

    const normalizedRows = rows.map(row => new Map(
        Object.entries(row).map(([key, value]) => [key.trim().toLocaleLowerCase('it-IT'), value])
    ));
    const inventoryCounts = new Map<string, number>();
    for (const row of normalizedRows) {
        const inventory = spreadsheetValue(row, ['Inventario', 'Numero inventario', 'N. inventario']);
        const key = inventory.toLocaleLowerCase('it-IT');
        if (key) inventoryCounts.set(key, (inventoryCounts.get(key) || 0) + 1);
    }

    const usedIds = new Set<string>();
    const generatedIdOccurrences = new Map<string, number>();
    const errors: string[] = [];
    let missingInventory = 0;
    let missingTitle = 0;

    const books = normalizedRows.map((row, index): Book => {
        const rowNumber = index + 2;
        const inventory = spreadsheetValue(row, ['Inventario', 'Numero inventario', 'N. inventario']);
        const explicitId = spreadsheetValue(row, ['ID', 'Id']);
        const title = spreadsheetValue(row, ['Titolo']);
        const book: Omit<Book, 'id'> = {
            scatola: spreadsheetValue(row, ['Scatola']) || '-',
            autore: spreadsheetValue(row, ['Autore']) || 'Sconosciuto',
            titolo: title || 'Senza titolo',
            editore: spreadsheetValue(row, ['Editore']),
            anno: spreadsheetValue(row, ['Anno']),
            inventario: inventory,
            collocazione: spreadsheetValue(row, ['Collocazione']),
            stato: spreadsheetValue(row, ['Stato di conservazione', 'Stato']),
            motivazioni: spreadsheetValue(row, ['Motivazioni per lo scarto', 'Motivazioni']),
            note: spreadsheetValue(row, ['Note interne', 'Note'])
        };

        if (!title) missingTitle++;
        if (!inventory) missingInventory++;
        if (explicitId.length > 50) errors.push(`Riga ${rowNumber}: ID oltre 50 caratteri`);

        const fingerprint = stableRowHash(Object.values(book).join('\u001f'));
        const safeInventory = inventory.normalize('NFKD')
            .replace(/[^A-Za-z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        const inventoryKey = inventory.toLocaleLowerCase('it-IT');
        const repeatedInventory = inventoryKey !== '' && (inventoryCounts.get(inventoryKey) || 0) > 1;
        const baseId = inventory
            ? `INV-${safeInventory || 'SENZA-NUMERO'}`
            : `LIB-${fingerprint}`;
        let id = explicitId || (repeatedInventory ? `${baseId.slice(0, 38)}-${fingerprint}` : baseId.slice(0, 50));
        const normalizedId = id.toLocaleLowerCase('it-IT');

        if (explicitId && usedIds.has(normalizedId)) {
            errors.push(`Riga ${rowNumber}: ID esplicito duplicato`);
        } else if (!explicitId && usedIds.has(normalizedId)) {
            const occurrence = (generatedIdOccurrences.get(normalizedId) || 1) + 1;
            generatedIdOccurrences.set(normalizedId, occurrence);
            id = `${id.slice(0, 46 - String(occurrence).length)}-${occurrence}`;
        }
        usedIds.add(id.toLocaleLowerCase('it-IT'));

        return { id, ...book };
    });

    if (errors.length > 0) {
        const suffix = errors.length > 8 ? `; altri ${errors.length - 8} errori` : '';
        throw new Error(`${errors.slice(0, 8).join('; ')}${suffix}`);
    }

    const repeatedValues = [...inventoryCounts.values()].filter(count => count > 1);
    const warnings: string[] = [];
    if (repeatedValues.length > 0) {
        warnings.push(`${repeatedValues.reduce((total, count) => total + count, 0)} righe con inventario ripetuto mantenute come volumi distinti`);
    }
    if (missingInventory > 0) warnings.push(`${missingInventory} righe senza inventario identificate dal contenuto`);
    if (missingTitle > 0) warnings.push(`${missingTitle} righe senza titolo importate come "Senza titolo"`);
    return { books, warnings };
};

interface UserData {
    nome: string;
    cognome: string;
    email: string;
    emailConfirm: string;
    via: string;
    civico: string;
    cap: string;
    citta: string;
    provincia: string;
    noteSpedizione: string;
    indirizzo?: string;
}

type ReservationUserData = Omit<UserData, 'emailConfirm'>;
type StaffReservationUserData = Omit<ReservationUserData, 'email'> & { email?: string };

const formatUserAddress = (user: Partial<ReservationUserData>): string => {
    if (user.via || user.civico || user.cap || user.citta || user.provincia) {
        const main = `${user.via || ''} ${user.civico || ''}`.trim();
        const locality = `${user.cap || ''} ${user.citta || ''}`.trim();
        const province = user.provincia ? ` (${user.provincia.toUpperCase()})` : '';
        const notes = user.noteSpedizione ? ` - Note di spedizione: ${user.noteSpedizione}` : '';
        return `${main}, ${locality}${province}${notes}`;
    }
    return user.indirizzo || '';
};

type ReservationStatus = 'active' | 'completed' | 'cancelled' | 'expired';

// Dati prenotazioni esposti al pubblico (nessun PII, nessun codice)
interface PublicReservation {
    bookIds: string[];
    createdAt: number;
    status: ReservationStatus;
    updatedAt?: number;
    completedAt?: number;
}

interface Reservation {
    code: string;
    bookIds: string[];
    createdAt: number;
    status: ReservationStatus;
    updatedAt?: number;
    completedAt?: number;
    expiresAt?: number;
    booksData?: Record<string, Book>;
    userData?: ReservationUserData;
    source?: 'online' | 'in_person';
}

interface StaffPagination {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
}

interface BookState {
    status: 'available' | 'reserved' | 'gone';
    expiryDate?: number;
}

interface CatalogAvailabilityState {
    id: string;
    _availability: 'reserved' | 'delivered';
    reservedUntil?: number;
}

interface AppSettings {
    reservationDays: number;
    maxBooksPerReservation: number;
    libraryName: string;
    libraryAddress: string;
    libraryPhone: string;
    libraryEmail: string;
    homepageUrl: string;
    privacyPolicyUrl: string;
    collectDomicile: boolean;
    appearance: AppearanceSettings;
}

interface ReservationPdfPayload {
    filename: string;
    contentBase64: string;
}

interface AppearanceSettings {
    primaryColor: string;
    secondaryColor: string;
    headerOpacity: number;
    accentColor: string;
    backgroundColor: string;
    textColor: string;
    fontFamily: string;
    logoUrl: string;
    logoAlt: string;
    siteTitle: string;
    siteSubtitle: string;
    contactUrl: string;
    contactLabel: string;
}

interface FullSettings {
    reservation_days: number;
    email_from: string;
    email_to: string;
    email_from_name: string;
    email_subject_prefix: string;
    library_name: string;
    library_address: string;
    library_phone: string;
    max_books_per_reservation: number;
    collect_domicile?: boolean;
    homepage_url: string;
    privacy_policy_url: string;
    // Rate limiting settings (v8.8.0)
    max_login_attempts?: number;
    login_lockout_minutes?: number;
    max_reservations_per_day?: number;
    max_reservations_per_email?: number;
    max_active_reservations_per_email?: number;
    rate_limit_email_exemptions?: string;
    reservation_email_blocklist?: string;
    // Retention settings (v8.8.0)
    retention_completed?: number;
    retention_cancelled?: number;
    retention_expired?: number;
    retention_audit_logs?: number;
    retention_ip?: number;
}

type FontSize = 'small' | 'medium' | 'large';

interface UnavailableBook {
    id: string;
    titolo: string;
    autore?: string;
    inventario?: string;
}

class ReservationConflictError extends Error {
    unavailableBooks: UnavailableBook[];

    constructor(message: string, unavailableBooks: UnavailableBook[]) {
        super(message);
        this.name = 'ReservationConflictError';
        this.unavailableBooks = unavailableBooks;
    }
}

class CatalogActiveReservationsError extends Error {
    activeReservations: number;

    constructor(activeReservations: number) {
        super(`${activeReservations} prenotazioni attive impediscono l’aggiornamento automatico del catalogo.`);
        this.name = 'CatalogActiveReservationsError';
        this.activeReservations = activeReservations;
    }
}

const sanitizeSpreadsheetCell = (value: unknown) => {
    if (typeof value !== 'string') return value;
    return /^[=+\-@\t\r]/.test(value) ? `'${value}` : value;
};

const getReservationError = (response: Response, payload: any, fallback: string): Error => {
    const unavailableBooks = Array.isArray(payload?.data?.unavailableBooks)
        ? payload.data.unavailableBooks.filter((book: any) => typeof book?.id === 'string')
        : [];
    if (response.status === 409 && unavailableBooks.length > 0) {
        return new ReservationConflictError(payload.message || fallback, unavailableBooks);
    }
    if (payload?.code === 'rest_invalid_param' && payload?.data?.params?.userData) {
        return new Error('I dati anagrafici inseriti non sono stati riconosciuti. Controllare nome, cognome ed email, quindi ricaricare la pagina e riprovare.');
    }
    return new Error(payload?.message || fallback);
};

let serverClockOffsetMs = 0;
const syncServerClock = (serverTime: unknown) => {
    const parsed = Number(serverTime);
    if (Number.isFinite(parsed) && parsed > 0) serverClockOffsetMs = parsed - Date.now();
};

// ============================================================================
// FUNZIONI HELPER
// ============================================================================
const formatDate = (timestamp: number): string => {
    if (!timestamp) return '-';
    return new Date(timestamp).toLocaleString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

interface TimeRemaining {
    totalMs: number;
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
}

const getTimeRemaining = (expiryDate: number, now = Date.now() + serverClockOffsetMs): TimeRemaining => {
    const diff = expiryDate - now;

    if (diff <= 0) {
        return { totalMs: 0, days: 0, hours: 0, minutes: 0, seconds: 0 };
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hoursRemainder = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutesRemainder = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const secondsRemainder = Math.floor((diff % (1000 * 60)) / 1000);

    return { totalMs: diff, days, hours: hoursRemainder, minutes: minutesRemainder, seconds: secondsRemainder };
};

const formatTimeRemaining = (remaining: TimeRemaining): string => {
    const segments = [];
    if (remaining.days > 0) segments.push(`${remaining.days}g`);
    segments.push(`${String(remaining.hours).padStart(2, '0')}h`);
    segments.push(`${String(remaining.minutes).padStart(2, '0')}m`);
    segments.push(`${String(remaining.seconds).padStart(2, '0')}s`);
    return segments.join(' ');
};

const ReservationCountdown: React.FC<{ expiryDate: number; compact?: boolean }> = ({ expiryDate, compact = false }) => {
    const [now, setNow] = useState(Date.now() + serverClockOffsetMs);

    useEffect(() => {
        const interval = setInterval(() => setNow(Date.now() + serverClockOffsetMs), 1000);
        return () => clearInterval(interval);
    }, []);

    const remaining = getTimeRemaining(expiryDate, now);
    if (remaining.totalMs <= 0) {
        return compact
            ? <>Prenotato - scadenza in aggiornamento</>
            : <p className="text-orange-300">Scadenza raggiunta: disponibilità in aggiornamento.</p>;
    }

    const countdown = formatTimeRemaining(remaining);
    const dateTime = new Date(expiryDate).toISOString();
    return compact ? (
        <>Prenotato - <time dateTime={dateTime}>{countdown}</time></>
    ) : (
        <p>Torna disponibile tra <strong className="text-yellow-300"><time dateTime={dateTime}>{countdown}</time></strong> se non ritirato.</p>
    );
};

const validateEmail = (email: string): boolean => {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
};

// ============================================================================
// GENERAZIONE PDF
// ============================================================================
const generateReservationPDF = async (
    reservation: Reservation,
    books: Book[],
    library: Pick<AppSettings, 'libraryName' | 'libraryAddress' | 'libraryPhone' | 'libraryEmail'>,
) => {
    const { jsPDF } = await import('jspdf');
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 20;
    const contentBottom = 258;
    let y = 25;

    const resolvedBooks = reservation.bookIds.map(bookId => (
        reservation.booksData?.[bookId]
        || books.find(book => book.id === bookId)
        || { id: bookId, titolo: 'Dati volume non disponibili', autore: '-', inventario: '-' }
    ));

    const addTableHeader = () => {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text('N.', margin, y);
        doc.text('Inventario', margin + 12, y);
        doc.text('Titolo', margin + 45, y);
        doc.text('Autore', margin + 120, y);
        y += 2;
        doc.line(margin, y, pageWidth - margin, y);
        y += 5;
        doc.setFont('helvetica', 'normal');
    };

    // Titolo - SOLO "Modulo Ritiro Scarto Librario"
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('Modulo Ritiro Scarto Librario', pageWidth / 2, y, { align: 'center' });
    y += 15;

    // Linea separatrice
    doc.setLineWidth(0.5);
    doc.line(margin, y, pageWidth - margin, y);
    y += 10;

    // Codice prenotazione
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('CODICE PRENOTAZIONE:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text(reservation.code, margin + 65, y);
    y += 8;

    // Data prenotazione
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('Data prenotazione:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text(formatDate(reservation.createdAt), margin + 45, y);
    y += 6;

    // Data ritiro
    doc.setFont('helvetica', 'bold');
    doc.text('Data ritiro:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text(formatDate(reservation.completedAt || Date.now()), margin + 45, y);
    y += 12;

    // Sezione dati utente
    doc.setLineWidth(0.3);
    doc.line(margin, y, pageWidth - margin, y);
    y += 8;

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('DATI RICHIEDENTE', margin, y);
    y += 8;

    if (reservation.userData) {
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        
        doc.text(`Nome: ${reservation.userData.nome}`, margin, y, { maxWidth: pageWidth - (margin * 2) });
        y += 6;
        doc.text(`Cognome: ${reservation.userData.cognome}`, margin, y, { maxWidth: pageWidth - (margin * 2) });
        y += 6;
        if (reservation.userData.email) {
            doc.text(`Email: ${reservation.userData.email}`, margin, y, { maxWidth: pageWidth - (margin * 2) });
            y += 6;
        }
        const address = formatUserAddress(reservation.userData);
        if (address) {
            const addressLines = doc.splitTextToSize(`Indirizzo: ${address}`, pageWidth - (margin * 2));
            doc.text(addressLines, margin, y);
            y += (addressLines.length * 5) + 5;
        }
    }

    // Sezione libri
    doc.setLineWidth(0.3);
    doc.line(margin, y, pageWidth - margin, y);
    y += 8;

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text(`ELENCO VOLUMI (${reservation.bookIds.length} totali)`, margin, y);
    y += 10;

    // Tabella libri - SENZA SCATOLA
    doc.setFontSize(9);
    
    addTableHeader();

    resolvedBooks.forEach((book, index) => {
        const inventoryLines = doc.splitTextToSize(book.inventario || '-', 30);
        const titleLines = doc.splitTextToSize(book.titolo || '-', 70);
        const authorLines = doc.splitTextToSize(book.autore || '-', 42);
        const rowLines = Math.max(inventoryLines.length, titleLines.length, authorLines.length);
        const rowHeight = Math.max(6, rowLines * 4 + 2);
        if (y + rowHeight > contentBottom) {
            doc.addPage();
            y = 20;
            addTableHeader();
        }
        doc.text((index + 1).toString(), margin, y);
        doc.text(inventoryLines, margin + 12, y);
        doc.text(titleLines, margin + 45, y);
        doc.text(authorLines, margin + 120, y);
        y += rowHeight;
    });

    // Reserve an uncluttered signing area on the final page.
    if (y > 230) {
        doc.addPage();
        y = 20;
    }
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setLineWidth(0.3);
    const signatureStart = pageWidth - 90;
    const signatureEnd = pageWidth - margin;
    const signatureCenter = (signatureStart + signatureEnd) / 2;
    doc.line(signatureStart, 244, signatureEnd, 244);
    doc.text('Firma', signatureCenter, 250, { align: 'center' });

    // Footer con dati biblioteca (su tutte le pagine, in fondo)
    const totalPages = doc.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(80, 80, 80);
        doc.setFont('helvetica', 'normal');
        
        // Linea separatrice footer
        doc.setLineWidth(0.2);
        doc.line(margin, 272, pageWidth - margin, 272);
        
        // Info biblioteca, configurate dal pannello WordPress.
        doc.setFont('helvetica', 'bold');
        doc.text(library.libraryName || 'Biblioteca', pageWidth / 2, 277, { align: 'center', maxWidth: pageWidth - (margin * 2) });
        doc.setFont('helvetica', 'normal');
        if (library.libraryAddress) doc.text(library.libraryAddress, pageWidth / 2, 282, { align: 'center', maxWidth: pageWidth - (margin * 2) });
        const contacts = [
            library.libraryPhone ? `Tel. ${library.libraryPhone}` : '',
            library.libraryEmail ? `email: ${library.libraryEmail}` : '',
        ].filter(Boolean).join(' - ');
        if (contacts) doc.text(contacts, pageWidth / 2, 287, { align: 'center', maxWidth: pageWidth - (margin * 2) });
    }

    // Scarica il PDF
    doc.save(`ritiro_${reservation.code}.pdf`);
};

// ============================================================================
// CONFIGURAZIONE STILI FONT
// ============================================================================
const getFontStyles = (size: FontSize) => {
    switch (size) {
        case 'small':
            return {
                base: 'text-sm',
                title: 'text-base font-bold',
                meta: 'text-xs',
                header: 'text-xs uppercase',
                input: 'text-sm',
                badge: 'text-[10px]',
                cellPadding: 'py-2 px-1 md:px-2',
                switcherPadding: 'p-1',
                iconScale: 'scale-90',
                rowHeight: 'h-10'
            };
        case 'large':
            return {
                base: 'text-lg',
                title: 'text-xl font-bold',
                meta: 'text-base',
                header: 'text-base uppercase',
                input: 'text-lg',
                badge: 'text-sm',
                cellPadding: 'py-4 px-2 md:px-4',
                switcherPadding: 'p-2.5',
                iconScale: 'scale-110',
                rowHeight: 'h-16'
            };
        case 'medium':
        default:
            return {
                base: 'text-base',
                title: 'text-lg font-bold',
                meta: 'text-sm',
                header: 'text-sm uppercase',
                input: 'text-base',
                badge: 'text-xs',
                cellPadding: 'py-3 px-1.5 md:px-3',
                switcherPadding: 'p-1.5',
                iconScale: 'scale-100',
                rowHeight: 'h-14'
            };
    }
};

// ============================================================================
// CLIENT API
// ============================================================================
interface CatalogLoadProgress {
    loaded: number;
    total: number;
    percent: number;
}

const api = {
    init: async (onProgress?: (progress: CatalogLoadProgress) => void) => {
        const needsFullAdminCatalog = IS_WP_ADMIN && (ADMIN_PAGE === 'catalog' || ADMIN_PAGE === 'create-reservation');
        const needsCatalog = !IS_WP_ADMIN || needsFullAdminCatalog;
        const initRoute = needsCatalog ? 'init' : 'init?include_catalog=0';
        const res = await fetch(`${API_URL}/${initRoute}`, { headers: getHeaders(), credentials: 'same-origin' });
        if (!res.ok) throw new Error(`Errore: ${res.status}`);
        const data = await res.json();
        syncServerClock(data.serverTime);
        if (!needsCatalog) {
            onProgress?.({ loaded: 0, total: 0, percent: 100 });
            return { ...data, books: [] };
        }
        const catalogRoute = needsFullAdminCatalog ? 'admin/catalog' : 'catalog';
        let firstPage = data;
        if (needsFullAdminCatalog) {
            const catalogRes = await fetch(`${API_URL}/${catalogRoute}?page=1&per_page=500`, {
                headers: getHeaders(),
                credentials: 'same-origin'
            });
            if (!catalogRes.ok) throw new Error(`Errore catalogo: ${catalogRes.status}`);
            firstPage = await catalogRes.json();
        }
        const totalPages = Number(firstPage.pagination?.totalPages || 1);
        const total = Number(firstPage.pagination?.total || 0);
        const books: Book[] = [...(firstPage.books || [])];
        const reportProgress = () => onProgress?.({
            loaded: books.length,
            total,
            percent: total > 0 ? Math.min(100, Math.round((books.length / total) * 100)) : 100,
        });
        reportProgress();

        for (let firstPageNumber = 2; firstPageNumber <= totalPages; firstPageNumber += 3) {
            const pageNumbers = Array.from(
                { length: Math.min(3, totalPages - firstPageNumber + 1) },
                (_, index) => firstPageNumber + index,
            );
            const pageResults = await Promise.all(pageNumbers.map(async page => {
                const pageRes = await fetch(`${API_URL}/${catalogRoute}?page=${page}&per_page=500`, {
                    headers: getHeaders(),
                    credentials: 'same-origin'
                });
                if (!pageRes.ok) throw new Error(`Errore catalogo: ${pageRes.status}`);
                return { page, data: await pageRes.json() };
            }));
            pageResults.sort((left, right) => left.page - right.page);
            for (const result of pageResults) books.push(...(result.data.books || []));
            reportProgress();
        }

        return { ...data, books };
    },

    requestReservationVerification: async (
        booksDetails: Book[],
        userData: Omit<UserData, 'emailConfirm'>,
    ) => {
        // Online reservations never transmit domicile fields.
        const onlineUserData = {
            nome: userData.nome.trim(),
            cognome: userData.cognome.trim(),
            email: userData.email.trim(),
        };
        const body: Record<string, unknown> = {
            booksDetails,
            userData: onlineUserData,
            consent: {
                accepted: true,
                privacyVersion: '9.4.8'
            }
        };

        const res = await fetch(`${API_URL}/reserve`, {
            method: 'POST',
            headers: getHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body)
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            if (res.status === 429) throw new Error(err.message || 'Troppe richieste. Riprova più tardi.');
            if (res.status === 409) throw getReservationError(res, err, 'Alcuni libri non sono più disponibili.');
            throw new Error(err.message || 'Errore durante l\'invio del codice di verifica');
        }
        return res.json();
    },

    confirmReservation: async (requestId: string, verificationCode: string) => {
        const res = await fetch(`${API_URL}/reserve/confirm`, {
            method: 'POST',
            headers: getHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ requestId, verificationCode })
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            if (res.status === 429) throw new Error(err.message || 'Troppe verifiche. Attendi prima di riprovare.');
            if (res.status === 409) throw getReservationError(res, err, 'Alcuni libri non sono più disponibili.');
            throw new Error(err.message || 'Codice di verifica non valido');
        }
        return res.json();
    },

    getCatalogAvailability: async (): Promise<{ states: CatalogAvailabilityState[]; serverTime: number }> => {
        const res = await fetch(`${API_URL}/catalog/availability`, {
            headers: getHeaders(),
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (!res.ok) throw new Error(`Errore disponibilita: ${res.status}`);
        const data = await res.json();
        syncServerClock(data.serverTime);
        return data;
    },

    getSettings: async () => {
        const res = await fetch(`${API_URL}/settings`, { headers: getHeaders(), credentials: 'same-origin' });
        if (!res.ok) throw new Error('Errore caricamento impostazioni');
        return res.json();
    },

};

const adminApi = IS_WP_ADMIN ? {
    saveBooks: async (books: Book[], password: string, force = false): Promise<ImportResult> => {
        const res = await fetch(`${API_URL}/books`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ books, password, force })
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            if (res.status === 409 && err.code === 'conflict') {
                throw new CatalogActiveReservationsError(Number(err.data?.activeReservations || 0));
            }
            throw new Error(err.message || 'Errore salvataggio libri');
        }
        return res.json();
    },
    updateStatus: async (code: string, action: 'complete' | 'cancel' | 'expired' | 'revoke') => {
        const res = await fetch(`${API_URL}/status`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ code, action })
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Errore aggiornamento stato');
        }
    },
    resendReservationEmail: async (code: string): Promise<{ success: boolean; message: string }> => {
        const res = await fetch(`${API_URL}/admin/reservations/resend`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ code })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Reinvio email non riuscito');
        return data;
    },
    createStaffReservation: async (books: Book[], userData: StaffReservationUserData): Promise<{ success: boolean; code: string; emailSent: boolean; booksReserved: number }> => {
        const res = await fetch(`${API_URL}/admin/reservations`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({
                booksDetails: books.map(book => ({ id: book.id })),
                userData,
                consent: { accepted: true, privacyVersion: '9.4.8' }
            })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw getReservationError(res, data, 'Creazione della prenotazione non riuscita');
        return data;
    },
    reset: async (password: string) => {
        const res = await fetch(`${API_URL}/reset`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ password })
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Errore reset');
        }
    },
    getOrders: async (page = 1, perPage = 50, search = '', status: 'all' | 'active' = 'all') => {
        const res = await fetch(`${API_URL}/orders`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ page, per_page: perPage, search, status })
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Errore caricamento ordini');
        }
        const data = await res.json();
        syncServerClock(data.serverTime);
        return data;
    },
    getSettingsPrivate: async () => {
        const res = await fetch(`${API_URL}/admin/settings`, { headers: getHeaders(), credentials: 'same-origin' });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Errore caricamento impostazioni');
        }
        return res.json();
    },
    saveSettings: async (settings: Partial<FullSettings>) => {
        const res = await fetch(`${API_URL}/admin/settings`, {
            method: 'POST', headers: getHeaders(true), credentials: 'same-origin',
            body: JSON.stringify({ settings })
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Errore salvataggio impostazioni');
        }
        return res.json();
    }
} : null;

// ============================================================================
// HOOKS CUSTOM
// ============================================================================
const useModalClose = (onClose: () => void, isOpen: boolean) => {
    useEffect(() => {
        if (!isOpen) return;

        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            }
        };

        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [isOpen, onClose]);
};

const useFocusTrap = (ref: React.RefObject<HTMLElement>, isOpen: boolean) => {
    useEffect(() => {
        if (!isOpen || !ref.current) return;

        const element = ref.current;
        const focusableElements = element.querySelectorAll<HTMLElement>(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        firstElement?.focus();

        const handleTab = (e: KeyboardEvent) => {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement?.focus();
                }
            } else {
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement?.focus();
                }
            }
        };

        element.addEventListener('keydown', handleTab);
        return () => element.removeEventListener('keydown', handleTab);
    }, [isOpen, ref]);
};

// ============================================================================
// COMPONENTI UI
// ============================================================================

// ---------------------------------------------------------------------------
// MODAL PASSWORD SICURO
// ---------------------------------------------------------------------------
interface PasswordModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (password: string) => Promise<void>;
    title: string;
    description: string;
    submitLabel?: string;
    isDanger?: boolean;
}

const PasswordModal: React.FC<PasswordModalProps> = ({
    isOpen, onClose, onSubmit, title, description, submitLabel = 'Conferma', isDanger = false
}) => {
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const modalRef = useRef<HTMLDivElement>(null);

    useModalClose(onClose, isOpen);
    useFocusTrap(modalRef, isOpen);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!password.trim()) {
            setError('Inserisci la password');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            await onSubmit(password);
            setPassword('');
            onClose();
        } catch (err: unknown) {
            setError(err instanceof Error ? err.message : 'Errore');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!isOpen) {
            setPassword('');
            setError(null);
            setLoading(false);
        }
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
                <div className="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onClick={onClose} />
                <div ref={modalRef} className="inline-block align-middle bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-md relative z-10 animate-fade-in">
                    <form onSubmit={handleSubmit}>
                        <div className="bg-white px-6 py-6">
                            <div className={`mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4 ${isDanger ? 'bg-red-100' : 'bg-blue-100'}`}>
                                <svg className={`h-7 w-7 ${isDanger ? 'text-red-600' : 'text-blue-600'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 text-center mb-2">{title}</h3>
                            <p className="text-gray-500 text-center text-sm mb-6">{description}</p>
                            <div className="space-y-4">
                                <div>
                                    <label htmlFor="modal-password" className="sr-only">Password</label>
                                    <input
                                        id="modal-password"
                                        type="password"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-center text-lg tracking-widest"
                                        placeholder="••••••••"
                                        autoComplete="current-password"
                                        disabled={loading}
                                    />
                                </div>
                                {error && (
                                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm text-center font-medium">
                                        {error}
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-3">
                            <button
                                type="submit"
                                disabled={loading}
                                className={`flex-1 py-3 px-4 rounded-lg font-bold text-white transition-colors ${
                                    loading ? 'bg-gray-400 cursor-not-allowed' : isDanger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'
                                }`}
                            >
                                {loading ? 'Verifica...' : submitLabel}
                            </button>
                            <button
                                type="button"
                                onClick={onClose}
                                disabled={loading}
                                className="flex-1 py-3 px-4 rounded-lg font-bold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors disabled:opacity-50"
                            >
                                Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// FONT SWITCHER
// ---------------------------------------------------------------------------
interface FontSwitcherProps {
    fontSize: FontSize;
    setFontSize: (size: FontSize) => void;
    styles: ReturnType<typeof getFontStyles>;
    theme?: 'dark' | 'light';
}

const FontSwitcher: React.FC<FontSwitcherProps> = ({ fontSize, setFontSize, styles, theme = 'dark' }) => {
    const btnClass = (isActive: boolean) =>
        theme === 'dark'
            ? (isActive ? '!bg-gray-500 !text-white' : '!text-gray-400 hover:!text-white')
            : (isActive ? '!bg-white !text-blue-900' : '!text-blue-100 hover:!bg-white/10');

    const containerClass = theme === 'dark' ? 'bg-gray-700' : 'bg-white/10 border border-white/20';

    return (
        <div className={`flex rounded-lg p-1 ${containerClass}`} role="group" aria-label="Dimensione testo">
            {(['small', 'medium', 'large'] as FontSize[]).map((size) => (
                <button
                    key={size}
                    onClick={() => setFontSize(size)}
                    className={`${styles.switcherPadding} rounded transition-all ${btnClass(fontSize === size)}`}
                    title={`Testo ${size === 'small' ? 'piccolo' : size === 'medium' ? 'medio' : 'grande'}`}
                    aria-pressed={fontSize === size}
                >
                    <span
                        className={`block font-serif font-bold leading-none transform ${styles.iconScale} text-center`}
                        style={{ fontSize: size === 'small' ? '12px' : size === 'medium' ? '16px' : '20px' }}
                        aria-hidden="true"
                    >
                        T
                    </span>
                </button>
            ))}
        </div>
    );
};

// ---------------------------------------------------------------------------
// LOGIN PANEL
// ---------------------------------------------------------------------------
interface LoginPanelProps {
    onLogin: (pwd: string) => Promise<void>;
}

// @ts-expect-error Componente legacy non esportato: non entra nel bundle v9.
const LoginPanel: React.FC<LoginPanelProps> = ({ onLogin }) => {
    const [pwd, setPwd] = useState('');
    const [error, setError] = useState(false);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(false);
        try {
            await onLogin(pwd);
        } catch {
            setError(true);
            setLoading(false);
        }
    };

    return (
        <div className="flex-1 flex items-center justify-center p-6 bg-gray-100 animate-fade-in">
            <div className="bg-white p-8 rounded-xl shadow-xl border border-gray-200 w-full max-w-md text-center">
                <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-6 text-blue-800">
                    <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-2">Accesso Personale</h2>
                <p className="text-gray-500 mb-6">Inserisci la password di amministrazione.</p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <label htmlFor="staff-password" className="sr-only">Password</label>
                    <input
                        id="staff-password"
                        type="password"
                        value={pwd}
                        onChange={e => setPwd(e.target.value)}
                        className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-center text-lg tracking-widest"
                        placeholder="••••••••"
                        autoFocus
                        autoComplete="current-password"
                    />
                    {error && (
                        <p className="text-red-600 text-sm font-bold bg-red-50 py-2 rounded" role="alert">
                            Password errata
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={loading}
                        className={`w-full py-3 rounded-lg font-bold !text-white transition-colors ${
                            loading ? '!bg-gray-400' : '!bg-blue-800 hover:!bg-blue-900'
                        }`}
                    >
                        {loading ? 'Verifica...' : 'Accedi'}
                    </button>
                </form>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// SETTINGS MODAL
// ---------------------------------------------------------------------------
interface SettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    onPasswordChanged: () => void;
}

// @ts-expect-error Componente legacy non esportato: le impostazioni v9 sono native WordPress.
const SettingsModal: React.FC<SettingsModalProps> = ({ isOpen, onClose, onPasswordChanged }) => {
    const [settings, setSettings] = useState<FullSettings | null>(null);
    const [originalSettings, setOriginalSettings] = useState<FullSettings | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const modalRef = useRef<HTMLDivElement>(null);

    // Cambio password
    const [showPasswordChange, setShowPasswordChange] = useState(false);
    const [newPassword, setNewPassword] = useState('');
    const [confirmNewPassword, setConfirmNewPassword] = useState('');
    const [passwordError, setPasswordError] = useState<string | null>(null);
    const [passwordSuccess, setPasswordSuccess] = useState(false);
    const [changingPassword, setChangingPassword] = useState(false);
    const [recoveringPassword, setRecoveringPassword] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState<string | null>(null);

    useModalClose(onClose, isOpen);
    useFocusTrap(modalRef, isOpen);

    useEffect(() => {
        if (isOpen) {
            setLoading(true);
            setError(null);
            setSuccess(false);
            setShowPasswordChange(false);
            setNewPassword('');
            setConfirmNewPassword('');
            setPasswordError(null);
            setPasswordSuccess(false);
            setRecoveryMessage(null);
            
            adminApi!.getSettingsPrivate()
                .then(data => {
                    setSettings(data);
                    setOriginalSettings(data);
                    setLoading(false);
                })
                .catch(err => {
                    setError(err.message);
                    setLoading(false);
                });
        }
    }, [isOpen]);

    const handleSave = async (closeAfter: boolean = false) => {
        if (!settings) return;
        setSaving(true);
        setError(null);
        setSuccess(false);

        try {
            await adminApi!.saveSettings(settings);
            setOriginalSettings(settings);
            setSuccess(true);
            if (closeAfter) {
                setTimeout(() => onClose(), 500);
            } else {
                setTimeout(() => setSuccess(false), 3000);
            }
        } catch (err: unknown) {
            setError(err instanceof Error ? err.message : 'Errore salvataggio');
        } finally {
            setSaving(false);
        }
    };

    const handleCancel = () => {
        setSettings(originalSettings);
        onClose();
    };

    const handleChangePassword = async () => {
        setPasswordError(null);
        setPasswordSuccess(false);

        if (newPassword.length < 12) {
            setPasswordError('La nuova password deve essere di almeno 12 caratteri.');
            return;
        }

        if (newPassword !== confirmNewPassword) {
            setPasswordError('Le password non coincidono.');
            return;
        }

        setChangingPassword(true);

        try {
            throw new Error('La password personale si gestisce dal profilo WordPress.');
            setPasswordSuccess(true);
            setShowPasswordChange(false);
            setNewPassword('');
            setConfirmNewPassword('');
            onPasswordChanged();
            setTimeout(() => setPasswordSuccess(false), 5000);
        } catch (err: unknown) {
            setPasswordError(err instanceof Error ? err.message : 'Errore cambio password');
        } finally {
            setChangingPassword(false);
        }
    };

    const handleRecoverPassword = async () => {
        setRecoveringPassword(true);
        setRecoveryMessage(null);

        try {
            throw new Error('Il recupero password si gestisce dalla schermata di accesso WordPress.');
            setRecoveryMessage('✓ Email inviata! Controlla la casella di posta.');
        } catch (err: unknown) {
            setRecoveryMessage(err instanceof Error ? err.message : 'Errore invio email');
        } finally {
            setRecoveringPassword(false);
        }
    };

    const updateSetting = (key: keyof FullSettings, value: string | number) => {
        if (settings) {
            setSettings({ ...settings, [key]: value });
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex items-center justify-center min-h-screen px-4 py-8">
                <div className="fixed inset-0 bg-gray-900 bg-opacity-75" />
                <div ref={modalRef} className="bg-white rounded-xl shadow-2xl w-full max-w-2xl relative z-10 animate-fade-in max-h-[90vh] overflow-y-auto">
                    <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10">
                        <h2 className="text-xl font-bold text-gray-900">⚙️ Impostazioni</h2>
                        <button onClick={handleCancel} className="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100">
                            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div className="p-6">
                        {loading ? (
                            <div className="text-center py-8">
                                <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-blue-600 mx-auto"></div>
                                <p className="mt-4 text-gray-500">Caricamento impostazioni...</p>
                            </div>
                        ) : error && !settings ? (
                            <div className="text-center py-8 text-red-600">
                                <p>{error}</p>
                            </div>
                        ) : settings ? (
                            <div className="space-y-6">
                                {/* Sezione Prenotazioni */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">📅 Prenotazioni</h3>
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Giorni durata prenotazione
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="30"
                                                value={settings.reservation_days}
                                                onChange={e => updateSetting('reservation_days', parseInt(e.target.value) || 7)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Da 1 a 30 giorni</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Max libri per prenotazione
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="100"
                                                value={settings.max_books_per_reservation}
                                                onChange={e => updateSetting('max_books_per_reservation', parseInt(e.target.value) || 20)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Limite per utenti non admin</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Sezione Sito */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">🌐 Sito Web</h3>
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                URL Homepage Biblioteca
                                            </label>
                                            <input
                                                type="url"
                                                value={settings.homepage_url || ''}
                                                onChange={e => updateSetting('homepage_url', e.target.value)}
                                                placeholder="https://www.bibliotecacrise.it"
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Link nel breadcrumb per tornare alla homepage</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                URL Privacy Policy (GDPR)
                                            </label>
                                            <input
                                                type="url"
                                                value={settings.privacy_policy_url || ''}
                                                onChange={e => updateSetting('privacy_policy_url', e.target.value)}
                                                placeholder="https://www.bibliotecacrise.it/privacy-policy"
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Link alla pagina con l'informativa privacy (visibile nella pagina pubblica)</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Sezione Email */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">📧 Notifiche Email</h3>
                                    <div className="space-y-4">
                                        <div className="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Email mittente
                                                </label>
                                                <input
                                                    type="email"
                                                    value={settings.email_from}
                                                    onChange={e => updateSetting('email_from', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Email destinatario notifiche
                                                </label>
                                                <input
                                                    type="email"
                                                    value={settings.email_to}
                                                    onChange={e => updateSetting('email_to', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Nome mittente
                                            </label>
                                            <input
                                                type="text"
                                                value={settings.email_from_name}
                                                onChange={e => updateSetting('email_from_name', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Prefisso oggetto email
                                            </label>
                                            <input
                                                type="text"
                                                value={settings.email_subject_prefix}
                                                onChange={e => updateSetting('email_subject_prefix', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Sezione Biblioteca */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">🏛️ Dati Biblioteca (per PDF)</h3>
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Nome biblioteca
                                            </label>
                                            <input
                                                type="text"
                                                value={settings.library_name}
                                                onChange={e => updateSetting('library_name', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Indirizzo biblioteca
                                            </label>
                                            <input
                                                type="text"
                                                value={settings.library_address}
                                                onChange={e => updateSetting('library_address', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Telefono (opzionale)
                                            </label>
                                            <input
                                                type="text"
                                                value={settings.library_phone}
                                                onChange={e => updateSetting('library_phone', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Sezione Sicurezza - Cambio Password */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">🔐 Sicurezza</h3>
                                    
                                    {passwordSuccess && (
                                        <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                                            ✓ Password cambiata con successo!
                                        </div>
                                    )}

                                    {!showPasswordChange ? (
                                        <button
                                            onClick={() => setShowPasswordChange(true)}
                                            className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors"
                                        >
                                            Cambia password personale
                                        </button>
                                    ) : (
                                        <div className="bg-gray-50 p-4 rounded-lg space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Nuova password * (min. 12 caratteri)
                                                </label>
                                                <input
                                                    type="password"
                                                    value={newPassword}
                                                    onChange={e => setNewPassword(e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Conferma nuova password *
                                                </label>
                                                <input
                                                    type="password"
                                                    value={confirmNewPassword}
                                                    onChange={e => setConfirmNewPassword(e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>

                                            {passwordError && (
                                                <div className="text-red-600 text-sm">{passwordError}</div>
                                            )}

                                            <div className="flex gap-3">
                                                <button
                                                    onClick={handleChangePassword}
                                                    disabled={changingPassword}
                                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:bg-gray-400"
                                                >
                                                    {changingPassword ? 'Salvataggio...' : 'Cambia Password'}
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setShowPasswordChange(false);
                                                        setPasswordError(null);
                                                        setNewPassword('');
                                                        setConfirmNewPassword('');
                                                    }}
                                                    className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium"
                                                >
                                                    Annulla
                                                </button>
                                            </div>

                                            <div className="pt-3 border-t border-gray-200">
                                                <p className="text-sm text-gray-600 mb-2">Non ricordi la password?</p>
                                                <button
                                                    onClick={handleRecoverPassword}
                                                    disabled={recoveringPassword}
                                                    className="text-blue-600 hover:text-blue-800 text-sm font-medium disabled:text-gray-400"
                                                >
                                                    {recoveringPassword ? 'Invio in corso...' : '→ Invia promemoria via email'}
                                                </button>
                                                {recoveryMessage && (
                                                    <p className={`mt-2 text-sm ${recoveryMessage.startsWith('✓') ? 'text-green-600' : 'text-red-600'}`}>
                                                        {recoveryMessage}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <p className="text-xs text-gray-500 mt-3">
                                        Nota: le credenziali staff e database si ruotano da Strumenti → Sicurezza Scarto Librario in WordPress.
                                    </p>
                                </div>

                                {/* Sezione Rate Limiting */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">⚡ Limiti di Utilizzo</h3>
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Max tentativi login
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="20"
                                                value={settings.max_login_attempts || 5}
                                                onChange={e => updateSetting('max_login_attempts', parseInt(e.target.value) || 5)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Prima del blocco temporaneo</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Minuti blocco login
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="60"
                                                value={settings.login_lockout_minutes || 15}
                                                onChange={e => updateSetting('login_lockout_minutes', parseInt(e.target.value) || 15)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Max prenotazioni/giorno per IP
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="10"
                                                value={settings.max_reservations_per_day || 1}
                                                onChange={e => updateSetting('max_reservations_per_day', parseInt(e.target.value) || 1)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Max prenotazioni per email
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="20"
                                                value={settings.max_reservations_per_email || 2}
                                                onChange={e => updateSetting('max_reservations_per_email', parseInt(e.target.value) || 2)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Totale prenotazioni consentite per email</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Max prenotazioni attive per email
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="10"
                                                value={settings.max_active_reservations_per_email || 2}
                                                onChange={e => updateSetting('max_active_reservations_per_email', parseInt(e.target.value) || 2)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Limita l'accaparramento di volumi non ancora ritirati</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Sezione Conservazione Dati GDPR */}
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">🗄️ Conservazione Dati (GDPR)</h3>
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Ordini completati (giorni)
                                            </label>
                                            <input
                                                type="number"
                                                min="30"
                                                max="730"
                                                value={settings.retention_completed || 365}
                                                onChange={e => updateSetting('retention_completed', parseInt(e.target.value) || 365)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Poi anonimizzati</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Ordini annullati (giorni)
                                            </label>
                                            <input
                                                type="number"
                                                min="7"
                                                max="365"
                                                value={settings.retention_cancelled || 90}
                                                onChange={e => updateSetting('retention_cancelled', parseInt(e.target.value) || 90)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Poi eliminati</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Indirizzi IP (giorni)
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="90"
                                                value={settings.retention_ip || 30}
                                                onChange={e => updateSetting('retention_ip', parseInt(e.target.value) || 30)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">Poi anonimizzati</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Log di sistema (giorni)
                                            </label>
                                            <input
                                                type="number"
                                                min="7"
                                                max="365"
                                                value={settings.retention_audit_logs || 90}
                                                onChange={e => updateSetting('retention_audit_logs', parseInt(e.target.value) || 90)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-3">
                                        I dati vengono elaborati automaticamente ogni notte. Gli IP sono anonimizzati separatamente per maggiore privacy.
                                    </p>
                                </div>

                                {/* Messaggi */}
                                {error && (
                                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                                        {error}
                                    </div>
                                )}
                                {success && (
                                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                                        ✓ Impostazioni salvate con successo!
                                    </div>
                                )}
                            </div>
                        ) : null}
                    </div>

                    <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex flex-wrap justify-end gap-3">
                        <button
                            onClick={handleCancel}
                            className="px-4 py-2 rounded-lg font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 transition-colors"
                        >
                            Annulla
                        </button>
                        <button
                            onClick={() => handleSave(false)}
                            disabled={saving || loading}
                            className={`px-5 py-2 rounded-lg font-medium text-white transition-colors ${
                                saving || loading ? 'bg-gray-400' : 'bg-blue-600 hover:bg-blue-700'
                            }`}
                        >
                            {saving ? 'Salvataggio...' : 'Salva'}
                        </button>
                        <button
                            onClick={() => handleSave(true)}
                            disabled={saving || loading}
                            className={`px-5 py-2 rounded-lg font-medium text-white transition-colors ${
                                saving || loading ? 'bg-gray-400' : 'bg-green-600 hover:bg-green-700'
                            }`}
                        >
                            Salva e Chiudi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// STAFF HEADER
// ---------------------------------------------------------------------------
interface StaffHeaderProps {
    styles: ReturnType<typeof getFontStyles>;
    fontSize: FontSize;
    setFontSize: (size: FontSize) => void;
    showImport: boolean;
    setShowImport: (show: boolean) => void;
    staffSearch: string;
    setStaffSearch: (search: string) => void;
    setHeaderHeight: (height: number) => void;
    onLogout: () => void;
    isAuthenticated: boolean;
    onOpenSettings: () => void;
}

const StaffHeader: React.FC<StaffHeaderProps> = ({
    styles, fontSize, setFontSize, showImport, setShowImport,
    staffSearch, setStaffSearch, setHeaderHeight, onLogout, isAuthenticated, onOpenSettings
}) => {
    const headerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!headerRef.current) return;
        const update = () => setHeaderHeight(headerRef.current!.offsetHeight);
        update();
        const observer = new ResizeObserver(update);
        observer.observe(headerRef.current);
        return () => observer.disconnect();
    }, [setHeaderHeight, fontSize]);

    return (
        <div ref={headerRef} className="scarto-admin-header flex-none shadow-lg z-40 bg-gray-50 sticky top-0 transition-all duration-200">
            <header className="bg-gray-800 text-white">
                <div className="container mx-auto px-4 py-2 md:py-4">
                    <div className="flex flex-row flex-wrap md:flex-nowrap justify-between items-center gap-2 md:gap-4">
                        <div className="flex-1 text-left min-w-[50%]">
                            <h1 className="text-2xl md:text-4xl font-bold tracking-tight">Scarto Librario</h1>
                            <p className="text-gray-400 mt-0.5 md:mt-1 text-sm md:text-lg font-medium">
                                {ADMIN_PAGE === 'catalog' ? 'Gestione catalogo' : ADMIN_PAGE === 'create-reservation' ? 'Prenotazione in sede' : 'Gestione prenotazioni'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0 ml-auto md:ml-0 flex-wrap">
                            {false && isAuthenticated && (
                                <>
                                    <button
                                        onClick={() => setShowImport(!showImport)}
                                        className="!bg-gray-700 hover:!bg-gray-600 !text-white px-3 py-1.5 md:px-4 md:py-2 rounded transition-all text-xs md:text-base flex items-center gap-2 border border-gray-600 ${showImport ? 'ring-2 ring-blue-500' : ''}"
                                        aria-expanded={showImport}
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        <span className="hidden sm:inline">Gestione Dati</span>
                                        <span className="sm:hidden">Dati</span>
                                    </button>
                                    <button
                                        onClick={onOpenSettings}
                                        className="!bg-gray-700 hover:!bg-gray-600 !text-white px-3 py-1.5 md:px-4 md:py-2 rounded transition-all text-xs md:text-base flex items-center gap-2 border border-gray-600"
                                        title="Impostazioni"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span className="hidden md:inline">Impostazioni</span>
                                    </button>
                                </>
                            )}
                            <FontSwitcher fontSize={fontSize} setFontSize={setFontSize} styles={styles} theme="dark" />
                            {false && isAuthenticated && (
                                <button onClick={onLogout} className="!bg-red-900/50 hover:!bg-red-700 !text-white px-3 py-1.5 md:px-4 md:py-2 rounded text-xs md:text-base border border-red-800">
                                    Esci
                                </button>
                            )}
                            <a href={runtimeSettings.publicUrl || '/'} className="text-xs md:text-base underline text-gray-300 hover:text-white px-2">
                                Sito pubblico
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            {isAuthenticated && ADMIN_PAGE !== 'create-reservation' && (
                <div className="container mx-auto px-4 py-2 md:py-4">
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className={`text-gray-400 ${fontSize === 'large' ? 'h-6 w-6' : 'h-5 w-5'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <label htmlFor="staff-search" className="sr-only">
                            {ADMIN_PAGE === 'catalog' ? 'Cerca nel catalogo' : 'Cerca prenotazioni'}
                        </label>
                        <input
                            id="staff-search"
                            type="text"
                            placeholder={ADMIN_PAGE === 'catalog' ? 'CERCA TITOLO, AUTORE O INVENTARIO...' : 'CERCA CODICE, NOME, COGNOME, EMAIL O VOLUME...'}
                            value={staffSearch}
                            onChange={e => setStaffSearch(e.target.value)}
                            className={`block w-full !pl-12 pr-4 h-12 bg-white border border-gray-300 rounded-lg shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase text-gray-900 placeholder-gray-500 ${styles.input}`}
                        />
                    </div>
                </div>
            )}
        </div>
    );
};

// ---------------------------------------------------------------------------
// DATA MANAGEMENT PANEL
// ---------------------------------------------------------------------------
interface DataManagementPanelProps {
    styles: ReturnType<typeof getFontStyles>;
    onImportClick: () => void;
    onResetClick: () => void;
    handleExport: () => void;
    isUploading: boolean;
    importFeedback: string | null;
}

const DataManagementPanel: React.FC<DataManagementPanelProps> = ({
    styles, onImportClick, onResetClick, handleExport, isUploading, importFeedback
}) => {
    return (
        <div className="bg-white border-b border-gray-200 p-6 shadow-inner animate-fade-in">
            <div className="container mx-auto grid md:grid-cols-3 gap-8">
                <div>
                    <h3 className={`font-semibold text-gray-800 mb-2 ${styles.title}`}>Importa catalogo Excel</h3>
                    <p className={`text-gray-600 mb-2 ${styles.meta}`}>Aggiorna il catalogo e rimuove i libri non presenti nel file. Richiede la <strong>password di sicurezza</strong>.</p>
                    <p className={`text-gray-500 mb-4 ${styles.meta}`}>Formati: .xlsx o .xls, massimo 10 MB e 50.000 righe. Sono riconosciute le colonne Titolo, Inventario, Autore, Scatola e gli altri campi del catalogo.</p>
                    <button
                        onClick={onImportClick}
                        disabled={isUploading}
                        className="!bg-blue-50 hover:!bg-blue-100 !text-blue-700 border border-blue-200 px-4 py-2 rounded font-semibold transition-colors flex items-center gap-2 shadow-sm disabled:opacity-50"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {isUploading ? 'Importazione...' : 'Importa catalogo Excel'}
                    </button>
                    {importFeedback && (
                        <p className={`mt-3 text-blue-800 ${styles.meta}`} role="status" aria-live="polite">{importFeedback}</p>
                    )}
                </div>
                <div className="border-t md:border-t-0 md:border-l border-gray-200 pt-6 md:pt-0 md:pl-8">
                    <h3 className={`font-semibold text-green-700 mb-2 ${styles.title}`}>Esporta Dati</h3>
                    <p className={`text-gray-600 mb-4 ${styles.meta}`}>Scarica la lista completa con lo stato.</p>
                    <button
                        onClick={handleExport}
                        className="!bg-white hover:!bg-green-50 !text-green-700 border border-green-200 px-4 py-2 rounded font-semibold transition-colors flex items-center gap-2 shadow-sm"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Scarica Excel
                    </button>
                </div>
                <div className="border-t md:border-t-0 md:border-l border-gray-200 pt-6 md:pt-0 md:pl-8">
                    <h3 className={`font-semibold text-red-700 mb-2 ${styles.title}`}>Zona Pericolo</h3>
                    <p className={`text-gray-600 mb-4 ${styles.meta}`}>Elimina TUTTO. Richiede password.</p>
                    <button
                        onClick={onResetClick}
                        className="!bg-red-50 hover:!bg-red-100 !text-red-700 border border-red-200 px-4 py-2 rounded font-semibold transition-colors flex items-center gap-2 shadow-sm"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Reset Totale
                    </button>
                </div>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// ADMIN CATALOG TABLE
// ---------------------------------------------------------------------------
interface AdminCatalogTableProps {
    books: Book[];
    styles: ReturnType<typeof getFontStyles>;
}

const AdminCatalogTable: React.FC<AdminCatalogTableProps> = ({ books, styles }) => (
    <div className="bg-white rounded shadow-sm border border-gray-200 overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-100">
                <tr>
                    <th className={`text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Inventario</th>
                    <th className={`text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Titolo</th>
                    <th className={`text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Autore</th>
                    <th className={`text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Scatola</th>
                    <th className={`text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Stato</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
                {books.length === 0 ? (
                    <tr><td colSpan={5} className="p-8 text-center text-gray-500">Nessun volume trovato.</td></tr>
                ) : books.map(book => {
                    const availability = book._availability || (book._delivered ? 'delivered' : (book._reserved ? 'reserved' : 'available'));
                    return (
                    <tr key={book.id}>
                        <td className={`font-mono text-gray-600 ${styles.cellPadding}`}>{book.inventario || '-'}</td>
                        <td className={`font-semibold text-gray-900 ${styles.cellPadding}`}>{book.titolo}</td>
                        <td className={`text-gray-700 ${styles.cellPadding}`}>{book.autore}</td>
                        <td className={`text-gray-700 ${styles.cellPadding}`}>{book.scatola}</td>
                        <td className={styles.cellPadding}>
                            <span className={`inline-flex rounded px-2 py-1 text-xs font-semibold ${
                                availability === 'delivered'
                                    ? 'bg-blue-100 text-blue-800'
                                    : availability === 'reserved'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-green-100 text-green-800'
                            }`}>
                                {availability === 'delivered'
                                    ? 'Consegnato'
                                    : availability === 'reserved'
                                        ? (book.reservedUntil ? <ReservationCountdown expiryDate={book.reservedUntil} compact /> : 'Prenotato')
                                        : 'Disponibile'}
                            </span>
                        </td>
                    </tr>
                    );
                })}
            </tbody>
        </table>
    </div>
);

// ---------------------------------------------------------------------------
// RESERVATIONS TABLE (con dati utente e PDF)
// ---------------------------------------------------------------------------
interface ReservationsTableProps {
    reservations: Reservation[];
    books: Book[];
    handleStaffAction: (code: string, action: 'complete' | 'cancel' | 'revoke') => Promise<void>;
    handleResendEmail: (code: string) => Promise<void>;
    pendingActions: Set<string>;
    loading: boolean;
    styles: ReturnType<typeof getFontStyles>;
    headerHeight: number;
    library: Pick<AppSettings, 'libraryName' | 'libraryAddress' | 'libraryPhone' | 'libraryEmail'>;
    pagination: StaffPagination;
    onPageChange: (page: number) => void;
}

const ReservationsTable: React.FC<ReservationsTableProps> = ({
    reservations, books, handleStaffAction, handleResendEmail, pendingActions, loading, styles, headerHeight, library, pagination, onPageChange
}) => {
    const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());
    const [pdfPending, setPdfPending] = useState<string | null>(null);
    const [pdfFeedback, setPdfFeedback] = useState<string | null>(null);
    const firstVisiblePage = Math.max(1, Math.min(pagination.page - 3, pagination.totalPages - 6));
    const visiblePages = Array.from(
        { length: Math.min(7, pagination.totalPages) },
        (_, index) => firstVisiblePage + index
    );

    const toggleRow = (code: string) => {
        const newSet = new Set(expandedRows);
        if (newSet.has(code)) {
            newSet.delete(code);
        } else {
            newSet.add(code);
        }
        setExpandedRows(newSet);
    };

    const handleDownloadPDF = async (reservation: Reservation) => {
        if (pdfPending) return;
        setPdfPending(reservation.code);
        setPdfFeedback(`Generazione PDF ${reservation.code} in corso...`);
        try {
            await generateReservationPDF(reservation, books, library);
            setPdfFeedback(`PDF ${reservation.code} scaricato correttamente.`);
        } catch (error) {
            console.error('Generazione PDF non riuscita.', error);
            setPdfFeedback(`Impossibile generare il PDF ${reservation.code}. Riprova o contatta l'amministratore tecnico.`);
        } finally {
            setPdfPending(null);
        }
    };

    return (
        <div className="bg-white rounded shadow-sm border border-gray-200">
            {pdfFeedback && <div className="m-3 border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-900" role="status" aria-live="polite">{pdfFeedback}</div>}
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="shadow-sm">
                    <tr>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Codice</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Data</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Utente</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Stato</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Libri</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Azioni</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {loading ? (
                        <tr><td colSpan={6} className="p-8 text-center text-blue-800 font-semibold">Caricamento prenotazioni in corso...</td></tr>
                    ) : reservations.length === 0 ? (
                        <tr><td colSpan={6} className="p-8 text-center text-gray-500">Nessuna prenotazione trovata.</td></tr>
                    ) : (
                        reservations.map((res) => {
                            const isExpanded = expandedRows.has(res.code);
                            const actionPending = pendingActions.has(res.code);
                            return (
                                <React.Fragment key={res.code}>
                                    <tr className={`transition-colors ${
                                        res.status === 'completed' ? 'bg-green-50/50' :
                                        res.status === 'active' ? 'bg-yellow-50/30' :
                                        res.status === 'expired' ? 'bg-orange-50/30' : 'bg-white'
                                    }`}>
                                        <td onClick={() => toggleRow(res.code)} className={`${styles.cellPadding} cursor-pointer align-top`}>
                                            <div className="flex items-center gap-1">
                                                <span className={`font-mono font-bold text-blue-800 ${styles.base}`}>{res.code}</span>
                                                <svg className={`w-3 h-3 text-gray-400 transform transition-transform ${isExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td className={`${styles.cellPadding} align-top`}>
                                            <span className={`${styles.meta} block leading-tight`}>{formatDate(res.createdAt)}</span>
                                        </td>
                                        <td className={`${styles.cellPadding} align-top`}>
                                            {res.userData ? (
                                                <div className={styles.meta}>
                                                    <div className="font-semibold text-gray-800">{res.userData.nome} {res.userData.cognome}</div>
                                                    <div className="text-gray-500 text-xs">{res.userData.email || 'Nessuna email'}</div>
                                                </div>
                                            ) : (
                                                <span className="text-gray-400 text-xs">N/D</span>
                                            )}
                                        </td>
                                        <td className={`${styles.cellPadding} align-top`}>
                                            <span className={`inline-flex items-center px-1.5 py-0.5 rounded font-semibold uppercase tracking-wide text-[10px] sm:text-xs ${
                                                res.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                res.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                                res.status === 'active' ? 'bg-yellow-100 text-yellow-800' :
                                                res.status === 'expired' ? 'bg-orange-100 text-orange-800' :
                                                'bg-gray-200 text-gray-600'
                                            }`}>
                                                {res.status === 'active' ? 'Attiva' :
                                                 res.status === 'completed' ? 'Consegnato' :
                                                 res.status === 'expired' ? 'Scaduta' : 'Annullata'}
                                            </span>
                                        </td>
                                        <td onClick={() => toggleRow(res.code)} className={`${styles.cellPadding} cursor-pointer align-top`}>
                                            <span className={`font-semibold text-gray-700 ${styles.base}`}>{res.bookIds.length} vol.</span>
                                        </td>
                                        <td className={`${styles.cellPadding} align-top`}>
                                            <div className="flex flex-col gap-1">
                                                {res.status === 'active' ? (
                                                    <div className="flex flex-col md:flex-row gap-1">
                                                        <button disabled={actionPending} onClick={(e) => { e.stopPropagation(); void handleStaffAction(res.code, 'complete'); }} className="!bg-green-600 hover:!bg-green-700 disabled:!bg-gray-400 !text-white py-1 px-2 rounded font-bold uppercase shadow-sm transition-colors text-[10px]">{actionPending ? 'Operazione...' : 'Consegna'}</button>
                                                        <button disabled={actionPending} onClick={(e) => { e.stopPropagation(); void handleStaffAction(res.code, 'cancel'); }} className="!bg-white border border-red-200 !text-red-600 hover:!bg-red-50 disabled:opacity-50 py-1 px-2 rounded font-bold uppercase shadow-sm transition-colors text-[10px]">{actionPending ? 'Attendere' : 'Annulla'}</button>
                                                        <button disabled={actionPending || !res.userData?.email} title={res.userData?.email ? 'Reinvia il riepilogo' : 'Prenotazione priva di indirizzo email'} onClick={(e) => { e.stopPropagation(); void handleResendEmail(res.code); }} className="!bg-blue-50 border border-blue-200 !text-blue-700 hover:!bg-blue-100 disabled:opacity-50 py-1 px-2 rounded font-bold uppercase shadow-sm transition-colors text-[10px]">{actionPending ? 'Invio...' : 'Reinvia email'}</button>
                                                    </div>
                                                ) : res.status === 'completed' ? (
                                                    <div className="flex flex-col gap-1">
                                                        <button disabled={actionPending || pdfPending !== null} onClick={(e) => { e.stopPropagation(); void handleDownloadPDF(res); }} className="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-gray-400 !text-white py-1 px-2 rounded font-bold uppercase shadow-sm transition-colors text-[10px] flex items-center gap-1 justify-center">
                                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            {pdfPending === res.code ? 'Generazione...' : 'PDF'}
                                                        </button>
                                                        <button disabled={actionPending} onClick={(e) => { e.stopPropagation(); void handleStaffAction(res.code, 'revoke'); }} className="!bg-orange-100 !text-orange-700 border border-orange-200 hover:!bg-orange-200 disabled:opacity-50 py-1 px-2 rounded font-bold uppercase shadow-sm transition-colors text-[10px]">{actionPending ? 'Operazione...' : 'Restituisci'}</button>
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-300 text-center">&bull;</span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                    {isExpanded && (
                                        <tr className="bg-gray-50/50">
                                            <td colSpan={6} className="p-4 border-b border-gray-200">
                                                <div className="grid md:grid-cols-2 gap-6">
                                                    {/* Info utente */}
                                                    {res.userData && (
                                                        <div className="bg-white p-4 rounded-lg border border-gray-200">
                                                            <h4 className="text-xs uppercase font-bold text-gray-400 mb-3">👤 Dati Utente</h4>
                                                            <div className="space-y-2 text-sm">
                                                                <div><span className="text-gray-500">Nome:</span> <span className="font-medium">{res.userData.nome}</span></div>
                                                                <div><span className="text-gray-500">Cognome:</span> <span className="font-medium">{res.userData.cognome}</span></div>
                                                                <div><span className="text-gray-500">Email:</span> <span className="font-medium">{res.userData.email || 'Non fornita'}</span></div>
                                                                {formatUserAddress(res.userData) && <div><span className="text-gray-500">Domicilio:</span> <span className="font-medium">{formatUserAddress(res.userData)}</span></div>}
                                                                {res.source === 'in_person' && <div><span className="text-gray-500">Origine:</span> <span className="font-medium">Prenotazione inserita in sede</span></div>}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {/* Lista libri */}
                                                    <div className={res.userData ? '' : 'md:col-span-2'}>
                                                        <h4 className="text-xs uppercase font-bold text-gray-400 mb-3">📚 Dettaglio Libri</h4>
                                                        <div className="grid grid-cols-1 gap-2 max-h-60 overflow-y-auto custom-scrollbar">
                                                            {res.bookIds.map((bid, idx) => {
                                                                const b = res.booksData?.[bid] || books.find(x => x.id === bid);
                                                                return (
                                                                    <div key={`${res.code}-${bid}-${idx}`} className="flex items-start gap-3 p-2 bg-white rounded border border-gray-200">
                                                                        {b ? (
                                                                            <>
                                                                                <div className="font-mono text-gray-500 text-xs min-w-[70px]">{b.inventario}</div>
                                                                                <div className="flex-1">
                                                                                    <div className="font-semibold text-gray-900 text-sm">{b.titolo}</div>
                                                                                    <div className="text-xs text-gray-600">{b.autore}</div>
                                                                                </div>
                                                                                <div className="bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs font-mono">Sc. {b.scatola}</div>
                                                                            </>
                                                                        ) : <span className="text-red-400 text-xs">Libro rimosso</span>}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            );
                        })
                    )}
                </tbody>
            </table>
            {pagination.totalPages > 1 && (
                <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3" aria-label="Pagine prenotazioni">
                    <p className="m-0 text-sm text-gray-600">Pagina {pagination.page} di {pagination.totalPages}, {pagination.total} prenotazioni</p>
                    <div className="flex flex-wrap gap-1">
                        <button type="button" disabled={loading || pagination.page <= 1} onClick={() => onPageChange(pagination.page - 1)} className="border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 disabled:opacity-40">Precedente</button>
                        {visiblePages.map(page => (
                            <button
                                type="button"
                                key={page}
                                disabled={loading}
                                aria-current={page === pagination.page ? 'page' : undefined}
                                onClick={() => onPageChange(page)}
                                className={`border px-3 py-1.5 text-sm ${page === pagination.page ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-300 bg-white text-gray-700'}`}
                            >{page}</button>
                        ))}
                        <button type="button" disabled={loading || pagination.page >= pagination.totalPages} onClick={() => onPageChange(pagination.page + 1)} className="border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 disabled:opacity-40">Successiva</button>
                    </div>
                </nav>
            )}
        </div>
    );
};

// ---------------------------------------------------------------------------
// PUBLIC HEADER
// ---------------------------------------------------------------------------
interface PublicHeaderProps {
    styles: ReturnType<typeof getFontStyles>;
    fontSize: FontSize;
    setFontSize: (size: FontSize) => void;
    searchTerm: string;
    setSearchTerm: (term: string) => void;
    searchPlaceholder: string;
    filteredCount: number;
    setHeaderHeight: (height: number) => void;
    homepageUrl: string;
    privacyPolicyUrl: string;
    appearance: AppearanceSettings;
}

const PublicHeader: React.FC<PublicHeaderProps> = ({
    styles, fontSize, setFontSize, searchTerm, setSearchTerm,
    searchPlaceholder, filteredCount, setHeaderHeight, homepageUrl, privacyPolicyUrl, appearance
}) => {
    const headerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!headerRef.current) return;
        const update = () => setHeaderHeight(headerRef.current!.offsetHeight);
        update();
        const observer = new ResizeObserver(update);
        observer.observe(headerRef.current);
        return () => observer.disconnect();
    }, [setHeaderHeight, fontSize]);

    return (
        <div ref={headerRef} className="flex-none shadow-lg z-40 bg-gray-50 sticky top-0 transition-all duration-200">
            <header className="library-gradient text-white">
                <div className="container mx-auto px-4 py-2 md:py-4">
                    <div className="flex flex-row flex-wrap md:flex-nowrap justify-between items-center gap-2 md:gap-4">
                        <div className="flex flex-1 items-center gap-3 text-left min-w-[50%]">
                            {appearance.logoUrl && (
                                homepageUrl ? (
                                    <a href={homepageUrl} aria-label="Vai alla homepage della biblioteca" className="inline-flex rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-blue-900">
                                        <img className="scarto-public-logo" src={appearance.logoUrl} alt={appearance.logoAlt} />
                                    </a>
                                ) : <img className="scarto-public-logo" src={appearance.logoUrl} alt={appearance.logoAlt} />
                            )}
                            <div>
                                <h1 className="text-2xl md:text-4xl font-bold tracking-tight">{appearance.siteTitle}</h1>
                                <p className="scarto-header-subtitle mt-0.5 md:mt-1 text-sm md:text-lg font-medium">{appearance.siteSubtitle}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 shrink-0 ml-auto md:ml-0">
                            <FontSwitcher fontSize={fontSize} setFontSize={setFontSize} styles={styles} theme="light" />
                        </div>
                    </div>
                </div>
            </header>
            {/* Breadcrumb and links */}
            {(homepageUrl || privacyPolicyUrl || appearance.contactUrl) && (
                <div className="scarto-links-bar bg-blue-50 border-b border-blue-100">
                    <div className="container mx-auto px-4 py-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            {homepageUrl && (
                                <nav className="flex items-center text-sm text-blue-700">
                                    <a
                                        href={homepageUrl}
                                        className="hover:text-blue-900 hover:underline flex items-center gap-1"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                        </svg>
                                        Homepage Biblioteca
                                    </a>
                                    <span className="mx-2 text-blue-400">/</span>
                                    <span className="text-blue-900 font-medium">Prenotazione Scarto</span>
                                </nav>
                            )}
                            {privacyPolicyUrl && (
                                <a
                                    href={privacyPolicyUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm text-blue-600 hover:text-blue-900 hover:underline flex items-center gap-1"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Privacy Policy
                                </a>
                            )}
                            {appearance.contactUrl && (
                                <a
                                    href={appearance.contactUrl}
                                    className="text-sm text-blue-600 hover:text-blue-900 hover:underline"
                                >
                                    {appearance.contactLabel || 'Contatti'}
                                </a>
                            )}
                        </div>
                    </div>
                </div>
            )}
            <div className="container mx-auto px-4 py-2 md:py-4">
                <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg className={`text-gray-400 ${fontSize === 'large' ? 'h-6 w-6' : 'h-5 w-5'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <label htmlFor="book-search" className="sr-only">Cerca libri</label>
                    <input
                        id="book-search"
                        type="search"
                        placeholder={searchPlaceholder}
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className={`block w-full !pl-16 pr-4 h-12 bg-white border border-gray-300 rounded-lg shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-500 ${styles.input}`}
                    />
                    <div className={`absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 ${styles.meta}`}>
                        {filteredCount} volumi
                    </div>
                </div>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// BOOKS TABLE
// ---------------------------------------------------------------------------
type ConservationStatus = 'good' | 'slightly-damaged' | 'damaged' | 'unspecified';

const getConservationStatus = (value?: string): { key: ConservationStatus; label: string; classes: string; dot: string } => {
    const label = value?.trim() || 'Non indicato';
    const normalized = label.toLocaleLowerCase('it-IT');

    if (normalized === 'buono') {
        return { key: 'good', label: 'Buono', classes: 'bg-green-50 text-green-800 border-green-300', dot: 'bg-green-600' };
    }
    if (normalized.includes('leggermente') && normalized.includes('deteriorato')) {
        return { key: 'slightly-damaged', label: 'Leggermente deteriorato', classes: 'bg-amber-50 text-amber-900 border-amber-300', dot: 'bg-amber-500' };
    }
    if (normalized.includes('deteriorato')) {
        return { key: 'damaged', label: 'Deteriorato', classes: 'bg-red-50 text-red-800 border-red-300', dot: 'bg-red-600' };
    }
    return { key: 'unspecified', label, classes: 'bg-gray-50 text-gray-700 border-gray-300', dot: 'bg-gray-500' };
};

const ConservationBadge: React.FC<{ value?: string; compact?: boolean }> = ({ value, compact = false }) => {
    const status = getConservationStatus(value);
    const description = `Stato di conservazione: ${status.label}`;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border font-semibold ${status.classes} ${compact ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm'}`}
            aria-label={description}
            title={description}
            data-conservation-status={status.key}
        >
            <span className={`h-2 w-2 shrink-0 rounded-full ${status.dot}`} aria-hidden="true" />
            <span>{status.label}</span>
        </span>
    );
};

interface BooksTableProps {
    filteredBooks: Book[];
    books: Book[];
    cart: Book[];
    toggleCartItem: (book: Book, isDisabled: boolean) => void;
    bookStates: Map<string, BookState>;
    styles: ReturnType<typeof getFontStyles>;
    headerHeight: number;
}

const BooksTable: React.FC<BooksTableProps> = ({
    filteredBooks, books, cart, toggleCartItem, bookStates, styles, headerHeight
}) => {
    if (filteredBooks.length === 0) {
        return (
            <div className="p-12 text-center text-gray-500 bg-white rounded-lg shadow-sm border border-gray-200">
                <p className={styles.title}>Nessun libro trovato.</p>
                <p className={styles.meta}>{books.length === 0 ? "Il catalogo non è ancora disponibile." : "Prova a cercare qualcos'altro."}</p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 relative">
            <aside className="border-b border-gray-200 bg-slate-50 px-4 py-3" aria-labelledby="conservation-legend-title">
                <div className="flex flex-wrap items-center gap-2">
                    <span id="conservation-legend-title" className="mr-1 text-sm font-bold text-gray-700">Stato di conservazione:</span>
                    <ConservationBadge value="Buono" compact />
                    <ConservationBadge value="Leggermente deteriorato" compact />
                    <ConservationBadge value="Deteriorato" compact />
                    <ConservationBadge compact />
                </div>
                <p className="mt-2 text-xs text-gray-600">Il colore facilita la lettura, ma ogni stato è sempre indicato anche con testo.</p>
            </aside>
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="shadow-sm">
                    <tr>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 w-16 ${styles.header} ${styles.cellPadding}`}>Sel.</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Inv.</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 ${styles.header} ${styles.cellPadding}`}>Titolo / Autore</th>
                        <th style={{ top: headerHeight }} className={`sticky bg-gray-100 z-20 text-left font-medium text-gray-500 hidden md:table-cell ${styles.header} ${styles.cellPadding}`}>Dettagli e stato</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {filteredBooks.map((book) => {
                        const isSelected = cart.some(c => c.id === book.id);
                        const bookState = bookStates.get(book.id);
                        const isReserved = bookState?.status === 'reserved';
                        const isGone = bookState?.status === 'gone';
                        const isDisabled = isReserved || isGone;

                        return (
                            <tr
                                key={book.id}
                                onClick={() => toggleCartItem(book, isDisabled)}
                                className={`transition-colors border-b last:border-0 ${
                                    isDisabled ? 'bg-gray-50 text-gray-400 cursor-not-allowed opacity-60 grayscale' : 'cursor-pointer hover:bg-blue-50/30'
                                } ${isSelected ? 'bg-blue-50' : ''}`}
                            >
                                <td className={`${styles.cellPadding} relative group align-top`}>
                                    {isGone ? (
                                        <div className="flex items-center justify-center">
                                            <span className="text-gray-400 font-bold text-xs uppercase border border-gray-300 rounded px-1">Ritirato</span>
                                        </div>
                                    ) : isReserved ? (
                                        <div className="flex items-center justify-center cursor-help" tabIndex={0}>
                                            <svg className="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div className="absolute left-14 top-0 w-60 bg-gray-800 text-white text-xs rounded shadow-lg p-3 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity z-50 pointer-events-none">
                                                <p className="font-bold mb-1 text-yellow-400">Prenotato</p>
                                                {bookState.expiryDate
                                                    ? <ReservationCountdown expiryDate={bookState.expiryDate} />
                                                    : <p>Scadenza non disponibile.</p>}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-center justify-center">
                                            <input type="checkbox" checked={isSelected} onChange={() => {}} className="sr-only" tabIndex={-1} />
                                            <div className={`w-6 h-6 rounded custom-checkbox-container flex items-center justify-center transition-colors ${isSelected ? 'selected' : 'bg-white'}`}>
                                                {isSelected && (
                                                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </td>
                                <td className={`${styles.cellPadding} font-mono ${isDisabled ? 'text-gray-400' : 'text-gray-500'} ${styles.meta} align-top`}>{book.inventario}</td>
                                <td className={`${styles.cellPadding} align-top`}>
                                    <div className={`${styles.title} ${isDisabled ? 'text-gray-500' : 'text-blue-900'} leading-snug`}>
                                        {book.titolo}
                                        {isGone && <span className="ml-2 inline-block px-2 py-0.5 rounded text-[10px] bg-gray-200 text-gray-500 uppercase font-bold">Non disponibile</span>}
                                    </div>
                                    <div className={`${styles.meta} ${isDisabled ? 'text-gray-400' : 'text-gray-600'} mt-1`}>{book.autore}</div>
                                    <div className={`mt-2 md:hidden ${styles.meta} text-gray-400`}>{book.editore} {book.anno && <span>- {book.anno}</span>}</div>
                                    <div className="mt-2 md:hidden">
                                        <ConservationBadge value={book.stato} compact />
                                    </div>
                                </td>
                                <td className={`hidden md:table-cell ${styles.cellPadding} text-gray-500 align-top`}>
                                    <div className={styles.base}>{book.editore}</div>
                                    <div className={styles.meta}>{book.anno}</div>
                                    <div className="mt-2"><ConservationBadge value={book.stato} compact /></div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
};

// ---------------------------------------------------------------------------
// USER DATA FORM
// ---------------------------------------------------------------------------
interface UserDataFormProps {
    userData: UserData;
    setUserData: React.Dispatch<React.SetStateAction<UserData>>;
    errors: Record<string, string>;
    mode: 'online' | 'staff';
}

const UserDataForm: React.FC<UserDataFormProps> = ({ userData, setUserData, errors, mode }) => {
    const handleChange = (field: keyof UserData) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        setUserData(prev => ({ ...prev, [field]: e.target.value }));
    };
    const emailRequired = mode === 'online';
    const showDomicile = mode === 'staff' && userData.email.trim() === '';

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label htmlFor="user-nome" className="block text-sm font-medium text-gray-700 mb-1">
                        Nome <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="user-nome"
                        type="text"
                        value={userData.nome}
                        onChange={handleChange('nome')}
                        aria-invalid={Boolean(errors.nome)}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.nome ? 'border-red-500' : 'border-gray-300'}`}
                        placeholder="Mario"
                    />
                    {errors.nome && <p className="text-red-500 text-xs mt-1">{errors.nome}</p>}
                </div>
                <div>
                    <label htmlFor="user-cognome" className="block text-sm font-medium text-gray-700 mb-1">
                        Cognome <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="user-cognome"
                        type="text"
                        value={userData.cognome}
                        onChange={handleChange('cognome')}
                        aria-invalid={Boolean(errors.cognome)}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.cognome ? 'border-red-500' : 'border-gray-300'}`}
                        placeholder="Rossi"
                    />
                    {errors.cognome && <p className="text-red-500 text-xs mt-1">{errors.cognome}</p>}
                </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label htmlFor="user-email" className="block text-sm font-medium text-gray-700 mb-1">
                        Email {emailRequired
                            ? <span className="text-red-500">*</span>
                            : <span className="font-normal text-gray-500">(facoltativa se si inserisce il domicilio)</span>}
                    </label>
                    <input
                        id="user-email"
                        type="email"
                        value={userData.email}
                        onChange={handleChange('email')}
                        aria-invalid={Boolean(errors.email)}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.email ? 'border-red-500' : 'border-gray-300'}`}
                        placeholder="mario.rossi@email.com"
                    />
                    {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                </div>
                <div>
                    <label htmlFor="user-email-confirm" className="block text-sm font-medium text-gray-700 mb-1">
                        Conferma Email {emailRequired
                            ? <span className="text-red-500">*</span>
                            : <span className="font-normal text-gray-500">(obbligatoria solo se si inserisce l'email)</span>}
                    </label>
                    <input
                        id="user-email-confirm"
                        type="email"
                        value={userData.emailConfirm}
                        onChange={handleChange('emailConfirm')}
                        aria-invalid={Boolean(errors.emailConfirm)}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.emailConfirm ? 'border-red-500' : 'border-gray-300'}`}
                        placeholder="mario.rossi@email.com"
                    />
                    {errors.emailConfirm && <p className="text-red-500 text-xs mt-1">{errors.emailConfirm}</p>}
                </div>
            </div>
            {showDomicile && <fieldset className="space-y-4 rounded-lg border border-gray-300 bg-gray-50 p-4">
                <legend className="px-2 text-sm font-semibold text-gray-800">Domicilio per la spedizione <span className="text-red-600">(obbligatorio senza email)</span></legend>
                <p className="m-0 text-sm text-gray-700">In assenza dell'email, inserire il domicilio completo per l'eventuale spedizione del documento protocollato.</p>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_10rem]">
                    <div>
                        <label htmlFor="user-via" className="block text-sm font-medium text-gray-700 mb-1">Via o piazza <span className="text-red-500">*</span></label>
                        <input id="user-via" type="text" autoComplete="street-address" value={userData.via} onChange={handleChange('via')} aria-invalid={Boolean(errors.via)} className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 ${errors.via ? 'border-red-500' : 'border-gray-300'}`} placeholder="Via Roma" />
                        {errors.via && <p className="text-red-500 text-xs mt-1">{errors.via}</p>}
                    </div>
                    <div>
                        <label htmlFor="user-civico" className="block text-sm font-medium text-gray-700 mb-1">Numero civico <span className="text-red-500">*</span></label>
                        <input id="user-civico" type="text" value={userData.civico} onChange={handleChange('civico')} aria-invalid={Boolean(errors.civico)} className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 ${errors.civico ? 'border-red-500' : 'border-gray-300'}`} placeholder="12/A" />
                        {errors.civico && <p className="text-red-500 text-xs mt-1">{errors.civico}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-[8rem_minmax(0,1fr)_8rem]">
                    <div>
                        <label htmlFor="user-cap" className="block text-sm font-medium text-gray-700 mb-1">CAP <span className="text-red-500">*</span></label>
                        <input id="user-cap" type="text" inputMode="numeric" autoComplete="postal-code" maxLength={5} value={userData.cap} onChange={event => setUserData(previous => ({ ...previous, cap: event.target.value.replace(/\D/g, '').slice(0, 5) }))} aria-invalid={Boolean(errors.cap)} className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 ${errors.cap ? 'border-red-500' : 'border-gray-300'}`} placeholder="34123" />
                        {errors.cap && <p className="text-red-500 text-xs mt-1">{errors.cap}</p>}
                    </div>
                    <div>
                        <label htmlFor="user-citta" className="block text-sm font-medium text-gray-700 mb-1">Città <span className="text-red-500">*</span></label>
                        <input id="user-citta" type="text" autoComplete="address-level2" value={userData.citta} onChange={handleChange('citta')} aria-invalid={Boolean(errors.citta)} className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 ${errors.citta ? 'border-red-500' : 'border-gray-300'}`} placeholder="Trieste" />
                        {errors.citta && <p className="text-red-500 text-xs mt-1">{errors.citta}</p>}
                    </div>
                    <div>
                        <label htmlFor="user-provincia" className="block text-sm font-medium text-gray-700 mb-1">Provincia <span className="text-red-500">*</span></label>
                        <input id="user-provincia" type="text" autoComplete="address-level1" maxLength={2} value={userData.provincia} onChange={event => setUserData(previous => ({ ...previous, provincia: event.target.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 2) }))} aria-invalid={Boolean(errors.provincia)} className={`w-full px-3 py-2 border rounded-lg uppercase focus:ring-2 focus:ring-blue-500 ${errors.provincia ? 'border-red-500' : 'border-gray-300'}`} placeholder="TS" />
                        {errors.provincia && <p className="text-red-500 text-xs mt-1">{errors.provincia}</p>}
                    </div>
                </div>
                <div>
                    <label htmlFor="user-note-spedizione" className="block text-sm font-medium text-gray-700 mb-1">Note ulteriori di spedizione <span className="font-normal text-gray-500">(facoltative)</span></label>
                    <textarea id="user-note-spedizione" rows={2} value={userData.noteSpedizione} onChange={handleChange('noteSpedizione')} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Scala, interno, presso, indicazioni utili al recapito" />
                </div>
            </fieldset>}
            <p className="text-xs text-gray-500">
                <span className="text-red-500">*</span> Campi obbligatori. {mode === 'online'
                    ? "Il domicilio non viene raccolto nelle prenotazioni online."
                    : "Per il recapito viene registrata l'email oppure, se assente, il domicilio."}
            </p>
        </div>
    );
};

interface StaffReservationCreateProps {
    books: Book[];
    onCreated: (bookIds: string[]) => void;
}

const EMPTY_USER_DATA: UserData = {
    nome: '', cognome: '', email: '', emailConfirm: '', via: '', civico: '', cap: '', citta: '', provincia: '', noteSpedizione: ''
};

const StaffReservationCreate: React.FC<StaffReservationCreateProps> = ({ books, onCreated }) => {
    const [query, setQuery] = useState('');
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [userData, setUserData] = useState<UserData>(EMPTY_USER_DATA);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [acknowledged, setAcknowledged] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [feedback, setFeedback] = useState<{ message: string; tone: 'info' | 'error' | 'success' } | null>(null);

    const matchingBooks = useMemo(() => {
        const terms = query.toLocaleLowerCase('it-IT').split(/\s+/).filter(Boolean);
        return books.filter(book => {
            if (terms.length === 0) return true;
            const text = `${book.titolo} ${book.autore} ${book.inventario} ${book.scatola}`.toLocaleLowerCase('it-IT');
            return terms.every(term => text.includes(term));
        });
    }, [books, query]);
    const visibleBooks = matchingBooks.slice(0, 200);

    const validate = () => {
        const next: Record<string, string> = {};
        if (!userData.nome.trim()) next.nome = 'Nome obbligatorio';
        if (!userData.cognome.trim()) next.cognome = 'Cognome obbligatorio';
        const email = userData.email.trim();
        if (email !== '') {
            if (!validateEmail(email)) next.email = 'Inserire un indirizzo email valido';
            if (!userData.emailConfirm.trim()) next.emailConfirm = 'Conferma email obbligatoria quando si usa l’email';
            else if (email !== userData.emailConfirm.trim()) next.emailConfirm = 'Le email non corrispondono';
        } else {
            if (!userData.via.trim()) next.via = 'Via o piazza obbligatoria';
            if (!userData.civico.trim()) next.civico = 'Numero civico obbligatorio';
            if (!/^[0-9]{5}$/.test(userData.cap)) next.cap = 'Inserire un CAP di 5 cifre';
            if (!userData.citta.trim()) next.citta = 'Città obbligatoria';
            if (!/^[A-Za-z]{2}$/.test(userData.provincia)) next.provincia = 'Inserire la sigla di 2 lettere';
        }
        setErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setFeedback(null);
        if (selectedIds.size === 0) {
            setFeedback({ message: 'Selezionare almeno un volume disponibile.', tone: 'error' });
            return;
        }
        const formIsValid = validate();
        if (!formIsValid || !acknowledged) {
            const message = !formIsValid && !acknowledged
                ? 'Correggere i campi evidenziati e confermare la presa visione dell’informativa privacy.'
                : !formIsValid
                    ? 'Correggere i campi obbligatori evidenziati prima di creare la prenotazione.'
                    : 'Confermare che l’interessato ha ricevuto l’informativa privacy.';
            setFeedback({ message, tone: 'error' });
            if (!formIsValid) {
                window.requestAnimationFrame(() => {
                    const firstInvalid = document.querySelector<HTMLElement>('#scarto-librario-root [aria-invalid="true"]');
                    firstInvalid?.focus();
                    firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
            return;
        }
        const selectedBooks = books.filter(book => selectedIds.has(book.id));
        const emailProvided = userData.email.trim() !== '';
        const { emailConfirm, ...rawPayload } = userData;
        const payload: StaffReservationUserData = emailProvided
            ? {
                ...rawPayload,
                email: rawPayload.email.trim(),
                via: '', civico: '', cap: '', citta: '', provincia: '', noteSpedizione: '', indirizzo: '',
            }
            : {
                nome: rawPayload.nome,
                cognome: rawPayload.cognome,
                via: rawPayload.via,
                civico: rawPayload.civico,
                cap: rawPayload.cap,
                citta: rawPayload.citta,
                provincia: rawPayload.provincia,
                noteSpedizione: rawPayload.noteSpedizione,
                indirizzo: rawPayload.indirizzo,
            };
        setSubmitting(true);
        setFeedback({ message: 'Creazione della prenotazione in sede in corso...', tone: 'info' });
        try {
            const result = await adminApi!.createStaffReservation(selectedBooks, payload);
            onCreated(selectedBooks.map(book => book.id));
            setSelectedIds(new Set());
            setUserData(EMPTY_USER_DATA);
            setAcknowledged(false);
            setErrors({});
            setFeedback({
                message: `Prenotazione ${result.code} creata per ${result.booksReserved} volumi. ${emailProvided
                    ? (result.emailSent ? 'Email accettata dal sistema di posta.' : 'Prenotazione salvata, ma l’email non è stata accettata: usare il reinvio dalla sezione Prenotazioni.')
                    : 'Nessuna email prevista: conservare il codice per la gestione della pratica e della spedizione.'}`,
                tone: 'success',
            });
        } catch (error) {
            setFeedback({
                message: error instanceof Error ? error.message : 'Creazione della prenotazione non riuscita.',
                tone: 'error',
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form onSubmit={submit} className="mx-auto max-w-7xl space-y-6" noValidate>
            <header className="border-b border-gray-300 pb-4">
                <h1 className="m-0 text-2xl font-bold text-gray-900">Nuova prenotazione in sede</h1>
                <p className="mt-2 text-gray-600">Operazione riservata al personale. Non richiede OTP e non applica limiti di quantità, orari o giornalieri; disponibilità e blacklist restano vincolanti.</p>
            </header>
            <section className="border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="staff-books-title">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div className="min-w-64 flex-1">
                        <label id="staff-books-title" htmlFor="staff-book-search" className="mb-1 block font-semibold">1. Seleziona i volumi</label>
                        <input id="staff-book-search" type="search" value={query} onChange={event => setQuery(event.target.value)} className="w-full border border-gray-300 px-3 py-2" placeholder="Cerca titolo, autore, inventario o scatola" />
                    </div>
                    <p className="m-0 text-sm font-semibold text-gray-700">{selectedIds.size} selezionati</p>
                </div>
                <div className="max-h-[32rem] overflow-auto border border-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="sticky top-0 bg-gray-100"><tr><th className="p-2 text-left">Seleziona</th><th className="p-2 text-left">Inventario</th><th className="p-2 text-left">Titolo / autore</th><th className="p-2 text-left">Scatola</th><th className="p-2 text-left">Stato</th></tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {visibleBooks.map(book => {
                                const unavailable = book._availability === 'reserved' || book._availability === 'delivered' || book._reserved || book._delivered;
                                const selected = selectedIds.has(book.id);
                                return <tr key={book.id} className={unavailable ? 'bg-gray-50 text-gray-400' : selected ? 'bg-blue-50' : ''}>
                                    <td className="p-2"><input type="checkbox" className="scarto-admin-checkbox" checked={selected} disabled={unavailable || submitting} aria-label={`Seleziona ${book.titolo}`} onChange={() => setSelectedIds(current => { const next = new Set(current); next.has(book.id) ? next.delete(book.id) : next.add(book.id); return next; })} /></td>
                                    <td className="p-2 font-mono">{book.inventario || '-'}</td><td className="p-2"><strong>{book.titolo}</strong><br /><span className="text-sm">{book.autore}</span></td><td className="p-2">{book.scatola || '-'}</td><td className="p-2">{unavailable ? (book._availability === 'delivered' || book._delivered ? 'Consegnato' : 'Prenotato') : 'Disponibile'}</td>
                                </tr>;
                            })}
                        </tbody>
                    </table>
                </div>
                {matchingBooks.length > visibleBooks.length && <p className="mt-2 text-sm text-gray-600">Sono mostrati i primi 200 risultati su {matchingBooks.length}. Raffinare la ricerca per trovare altri volumi.</p>}
            </section>
            <section className="border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="staff-user-title">
                <h2 id="staff-user-title" className="mt-0 text-xl font-bold">2. Dati dell’interessato</h2>
                <p className="mb-4 text-sm text-gray-700">Inserire l'email se disponibile. Se il campo resta vuoto, il domicilio diventa obbligatorio per l'eventuale spedizione del documento protocollato.</p>
                <UserDataForm userData={userData} setUserData={setUserData} errors={errors} mode="staff" />
            </section>
            <section className="border border-gray-200 bg-white p-5 shadow-sm">
                <label className="flex items-start gap-3"><input type="checkbox" checked={acknowledged} onChange={event => setAcknowledged(event.target.checked)} className="scarto-admin-checkbox mt-1" /><span>Confermo di avere consegnato o mostrato all’interessato l’informativa privacy e di registrarne la presa visione. <strong>Obbligatorio.</strong></span></label>
            </section>
            <div className="sticky bottom-0 z-10 border border-gray-300 bg-white p-4 shadow-lg">
                {feedback && (
                    <div
                        className={`mb-3 border px-4 py-3 font-semibold ${feedback.tone === 'error' ? 'border-red-300 bg-red-50 text-red-800' : feedback.tone === 'success' ? 'border-green-300 bg-green-50 text-green-900' : 'border-blue-200 bg-blue-50 text-blue-900'}`}
                        role={feedback.tone === 'error' ? 'alert' : 'status'}
                        aria-live={feedback.tone === 'error' ? 'assertive' : 'polite'}
                    >
                        {feedback.message}
                    </div>
                )}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="m-0 text-sm font-semibold text-gray-700">{selectedIds.size} volumi selezionati</p>
                    <button type="submit" disabled={submitting || selectedIds.size === 0} aria-busy={submitting} className="!bg-blue-700 !text-white px-6 py-3 font-bold disabled:!bg-gray-400">{submitting ? 'Creazione in corso...' : `Crea prenotazione (${selectedIds.size} volumi)`}</button>
                </div>
            </div>
        </form>
    );
};

// ---------------------------------------------------------------------------
// CART MODAL (con form utente)
// ---------------------------------------------------------------------------
interface CartModalProps {
    cart: Book[];
    toggleCartItem: (book: Book, isDisabled: boolean) => void;
    requestReservationVerification: (userData: Omit<UserData, 'emailConfirm'>) => Promise<{ requestId: string; expiresIn: number }>;
    confirmReservation: (requestId: string, verificationCode: string, userData: Omit<UserData, 'emailConfirm'>) => Promise<void>;
    setIsCartOpen: (open: boolean) => void;
    styles: ReturnType<typeof getFontStyles>;
    isAuthenticated: boolean;
    maxBooks: number;
    reservationDays: number;
    privacyPolicyUrl: string;
    refreshCatalog: () => Promise<void>;
}

const CartModal: React.FC<CartModalProps> = ({
    cart, toggleCartItem, requestReservationVerification, confirmReservation, setIsCartOpen, styles, isAuthenticated, maxBooks, reservationDays, privacyPolicyUrl, refreshCatalog
}) => {
    const [step, setStep] = useState<'books' | 'form' | 'confirm' | 'verify'>('books');
    const [userData, setUserData] = useState<UserData>({ nome: '', cognome: '', email: '', emailConfirm: '', via: '', civico: '', cap: '', citta: '', provincia: '', noteSpedizione: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [privacyAccepted, setPrivacyAccepted] = useState(false);
    const [verificationRequestId, setVerificationRequestId] = useState('');
    const [verificationCode, setVerificationCode] = useState('');
    const [verificationExpiryMinutes, setVerificationExpiryMinutes] = useState(15);
    const modalRef = useRef<HTMLDivElement>(null);

    const isOverLimit = cart.length > maxBooks;
    const canProceed = !isOverLimit || isAuthenticated;

    const handleClose = useCallback(() => {
        if (!isSubmitting) setIsCartOpen(false);
    }, [isSubmitting, setIsCartOpen]);
    useModalClose(handleClose, true);
    useFocusTrap(modalRef, true);

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!userData.nome.trim()) newErrors.nome = 'Nome obbligatorio';
        if (!userData.cognome.trim()) newErrors.cognome = 'Cognome obbligatorio';
        if (!userData.email.trim()) {
            newErrors.email = 'Email obbligatoria';
        } else if (!validateEmail(userData.email)) {
            newErrors.email = 'Email non valida';
        }
        if (!userData.emailConfirm.trim()) {
            newErrors.emailConfirm = 'Conferma email obbligatoria';
        } else if (userData.email !== userData.emailConfirm) {
            newErrors.emailConfirm = 'Le email non corrispondono';
        }
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleNextStep = () => {
        setSubmitError(null);
        if (step === 'books') {
            setStep('form');
        } else if (step === 'form') {
            if (validateForm()) {
                setStep('confirm');
            }
        }
    };

    const handlePrevStep = () => {
        if (step === 'form') setStep('books');
        else if (step === 'confirm') setStep('form');
    };

    const handleRequestVerification = async () => {
        setIsSubmitting(true);
        setSubmitError(null);
        try {
            const { emailConfirm, ...userDataWithoutConfirm } = userData;
            const result = await requestReservationVerification(userDataWithoutConfirm);
            setVerificationRequestId(result.requestId);
            setVerificationExpiryMinutes(Math.max(1, Math.ceil(result.expiresIn / 60)));
            setVerificationCode('');
            setStep('verify');
            setIsSubmitting(false);
        } catch (error) {
            handleReservationFailure(error);
            setIsSubmitting(false);
        }
    };

    const handleConfirm = async () => {
        if (!/^[0-9]{6}$/.test(verificationCode)) {
            setSubmitError('Inserisci il codice di sei cifre ricevuto via email.');
            return;
        }

        setIsSubmitting(true);
        setSubmitError(null);
        try {
            const { emailConfirm, ...userDataWithoutConfirm } = userData;
            await confirmReservation(verificationRequestId, verificationCode, userDataWithoutConfirm);
        } catch (error) {
            handleReservationFailure(error);
            setIsSubmitting(false);
        }
    };

    const handleReservationFailure = (error: unknown) => {
        if (error instanceof ReservationConflictError) {
            const unavailableIds = new Set(error.unavailableBooks.map(book => book.id));
            cart.filter(book => unavailableIds.has(book.id)).forEach(book => toggleCartItem(book, false));

            const titles = error.unavailableBooks
                .map(book => book.titolo || book.inventario || book.id)
                .slice(0, 5);
            const remaining = error.unavailableBooks.length - titles.length;
            const suffix = remaining > 0 ? ` e altri ${remaining}` : '';
            setSubmitError(
                `Non più disponibili: ${titles.join('; ')}${suffix}. ` +
                'Sono stati rimossi dal carrello; gli altri libri sono rimasti selezionati.'
            );
            setVerificationRequestId('');
            setVerificationCode('');
            setPrivacyAccepted(false);
            setStep('books');
            void refreshCatalog();
            return;
        }

        setSubmitError(error instanceof Error ? error.message : 'Errore durante la prenotazione. Riprova.');
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={handleClose} />
                <div ref={modalRef} className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <div className="bg-white px-6 py-6 sm:p-8">
                        {/* Progress indicator */}
                        <div className="flex items-center justify-center mb-6">
                            <div className="flex items-center">
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${step === 'books' ? 'bg-blue-600 text-white' : 'bg-green-500 text-white'}`}>1</div>
                                <div className={`w-8 sm:w-12 h-1 ${step !== 'books' ? 'bg-green-500' : 'bg-gray-300'}`}></div>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${step === 'form' ? 'bg-blue-600 text-white' : ['confirm', 'verify'].includes(step) ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-600'}`}>2</div>
                                <div className={`w-8 sm:w-12 h-1 ${['confirm', 'verify'].includes(step) ? 'bg-green-500' : 'bg-gray-300'}`}></div>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${step === 'confirm' ? 'bg-blue-600 text-white' : step === 'verify' ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-600'}`}>3</div>
                                <div className={`w-8 sm:w-12 h-1 ${step === 'verify' ? 'bg-green-500' : 'bg-gray-300'}`}></div>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${step === 'verify' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'}`}>4</div>
                            </div>
                        </div>

                        {submitError && (
                            <div className="mb-4 border border-red-300 bg-red-50 p-4 text-sm text-red-800 rounded-lg" role="alert">
                                {submitError}
                            </div>
                        )}

                        {/* Step 1: Books */}
                        {step === 'books' && (
                            <>
                                <h3 className={`leading-6 font-bold text-gray-900 mb-4 ${styles.title}`}>📚 Libri Selezionati</h3>
                                
                                {isOverLimit && !isAuthenticated && (
                                    <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                        <p className="font-bold">Limite superato ({cart.length}/{maxBooks})</p>
                                        <p className="mt-1">Per ordini superiori contattare la biblioteca.</p>
                                    </div>
                                )}

                                <div className="max-h-72 overflow-y-auto border-t border-b border-gray-200 my-4 custom-scrollbar pr-2">
                                    {cart.map((book) => (
                                        <div key={book.id} className="flex justify-between items-center py-3 px-2 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                            <div className="flex-1 pr-4">
                                                <div className={`${styles.title} text-gray-900`}>{book.titolo}</div>
                                                <div className={`${styles.meta} text-gray-500`}>{book.autore} | Inv: {book.inventario}</div>
                                            </div>
                                            <button onClick={() => toggleCartItem(book, false)} className="text-red-600 hover:text-red-800 p-2 rounded hover:bg-red-50">
                                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}

                        {/* Step 2: Form */}
                        {step === 'form' && (
                            <>
                                <h3 className={`leading-6 font-bold text-gray-900 mb-4 ${styles.title}`}>👤 I Tuoi Dati</h3>
                                <p className="text-gray-600 text-sm mb-4">Compila i dati richiesti per completare la prenotazione.</p>
                                <UserDataForm userData={userData} setUserData={setUserData} errors={errors} mode="online" />
                                {privacyPolicyUrl && (
                                    <div className="mt-4 text-sm text-gray-500 flex items-center gap-2">
                                        <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        I tuoi dati saranno trattati secondo la nostra{' '}
                                        <a
                                            href={privacyPolicyUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-blue-600 hover:text-blue-800 underline"
                                        >
                                            informativa privacy
                                        </a>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Step 3: Confirm */}
                        {step === 'confirm' && (
                            <>
                                <h3 className={`leading-6 font-bold text-gray-900 mb-4 ${styles.title}`}>✅ Conferma Prenotazione</h3>
                                
                                <div className="bg-gray-50 rounded-lg p-4 mb-4">
                                    <h4 className="font-semibold text-gray-800 mb-2">Riepilogo dati:</h4>
                                    <div className="text-sm space-y-1">
                                        <p><span className="text-gray-500">Nome:</span> {userData.nome} {userData.cognome}</p>
                                        <p><span className="text-gray-500">Email:</span> {userData.email}</p>
                                        <p><span className="text-gray-500">Libri:</span> {cart.length} volumi</p>
                                    </div>
                                </div>

                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm">
                                    <p className="text-blue-800">
                                        <strong>Attenzione:</strong> riceverai un codice via email. I libri saranno riservati per <strong>{reservationDays} giorni</strong> solo dopo la verifica del codice.
                                    </p>
                                </div>

                                {/* Privacy policy acknowledgement */}
                                <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <label className="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={privacyAccepted}
                                            onChange={(e) => setPrivacyAccepted(e.target.checked)}
                                            className="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        />
                                        <span className="text-sm text-gray-700">
                                            Dichiaro di aver letto l'
                                            {privacyPolicyUrl ? (
                                                <a
                                                    href={privacyPolicyUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-blue-600 hover:text-blue-800 underline font-medium"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    informativa sulla privacy
                                                </a>
                                            ) : (
                                                <span className="font-medium">informativa sulla privacy</span>
                                            )}
                                            {' '}e prendo atto del trattamento dei dati necessario alla gestione della prenotazione e agli adempimenti amministrativi connessi.
                                        </span>
                                    </label>
                                </div>

                            </>
                        )}

                        {/* Step 4: Email verification */}
                        {step === 'verify' && (
                            <>
                                <h3 className={`leading-6 font-bold text-gray-900 mb-4 ${styles.title}`}>Verifica indirizzo email</h3>
                                <p className="text-gray-600 text-sm mb-4">
                                    Inserisci il codice di sei cifre inviato a <strong>{userData.email}</strong>. Il codice scade dopo {verificationExpiryMinutes} minuti.
                                </p>
                                <label htmlFor="reservation-verification-code" className="block text-sm font-medium text-gray-700 mb-1">
                                    Codice di verifica
                                </label>
                                <input
                                    id="reservation-verification-code"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    value={verificationCode}
                                    onChange={(event) => setVerificationCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                                    disabled={isSubmitting}
                                    className="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 tracking-[0.35em] text-center text-xl"
                                    aria-describedby="reservation-verification-help"
                                    autoFocus
                                />
                                <p id="reservation-verification-help" className="text-xs text-gray-500 mt-2">
                                    Nessun libro viene bloccato finché il codice non è verificato.
                                </p>
                                {isSubmitting && (
                                    <div className="mt-4 flex items-center gap-3 border border-blue-300 bg-blue-50 p-4 text-sm font-semibold text-blue-900 rounded-lg" role="status" aria-live="assertive">
                                        <span className="inline-block h-5 w-5 flex-none animate-spin rounded-full border-2 border-blue-200 border-t-blue-700" aria-hidden="true" />
                                        <span>Verifica del codice e creazione della prenotazione in corso. Non chiudere questa finestra.</span>
                                    </div>
                                )}

                            </>
                        )}
                    </div>

                    <div className="bg-gray-50 px-6 py-4 flex justify-between gap-3 border-t border-gray-200">
                        <button
                            type="button"
                            onClick={step === 'books' || step === 'verify' ? handleClose : handlePrevStep}
                            disabled={isSubmitting}
                            className="px-6 py-3 rounded-lg font-semibold bg-white text-gray-700 border-2 border-gray-300 hover:bg-gray-100 hover:border-gray-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
                        >
                            {step === 'books' || step === 'verify' ? '✕ Chiudi' : '← Indietro'}
                        </button>
                        
                        {step === 'confirm' ? (
                            <button
                                type="button"
                                onClick={handleRequestVerification}
                                disabled={isSubmitting || !privacyAccepted}
                                className={`px-6 py-3 rounded-lg font-bold shadow-md transition-all ${
                                    isSubmitting || !privacyAccepted
                                        ? 'bg-gray-400 text-gray-200 cursor-not-allowed'
                                        : 'bg-green-600 hover:bg-green-700 text-white'
                                }`}
                            >
                                {isSubmitting ? 'Invio in corso...' : 'Invia codice di verifica'}
                            </button>
                        ) : step === 'verify' ? (
                            <button
                                type="button"
                                onClick={handleConfirm}
                                disabled={isSubmitting || verificationCode.length !== 6}
                                className={`px-6 py-3 rounded-lg font-bold shadow-md transition-all ${
                                    isSubmitting || verificationCode.length !== 6
                                        ? 'bg-gray-400 text-gray-200 cursor-not-allowed'
                                        : 'bg-green-600 hover:bg-green-700 text-white'
                                }`}
                            >
                                {isSubmitting ? 'Creazione in corso...' : 'Conferma prenotazione'}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleNextStep}
                                disabled={!canProceed}
                                className={`px-6 py-3 rounded-lg font-bold shadow-md transition-all ${
                                    canProceed 
                                        ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                }`}
                            >
                                Avanti →
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// SUCCESS MODAL
// ---------------------------------------------------------------------------
interface SuccessModalProps {
    lastReservationCode: string;
    setLastReservationCode: (code: string | null) => void;
    styles: ReturnType<typeof getFontStyles>;
    reservedBooks: Book[];
    userData: Omit<UserData, 'emailConfirm'> | null;
    reservationDays: number;
    reservationPdf: ReservationPdfPayload | null;
    library: Pick<AppSettings, 'libraryName' | 'libraryAddress' | 'libraryPhone' | 'libraryEmail'>;
}

const generateUserReservationPDF = async (
    code: string,
    books: Book[],
    userData: Omit<UserData, 'emailConfirm'> | null,
    reservationDays: number,
    library: Pick<AppSettings, 'libraryName' | 'libraryAddress' | 'libraryPhone' | 'libraryEmail'>
) => {
    const { jsPDF } = await import('jspdf');
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 20;
    let y = 25;

    // Titolo
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('Riepilogo Prenotazione Scarto Librario', pageWidth / 2, y, { align: 'center' });
    y += 15;

    // Linea separatrice
    doc.setLineWidth(0.5);
    doc.line(margin, y, pageWidth - margin, y);
    y += 10;

    // Codice prenotazione
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('CODICE PRENOTAZIONE:', margin, y);
    y += 10;
    doc.setFontSize(24);
    doc.text(code, margin, y);
    y += 15;

    // Data e scadenza
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    const now = new Date();
    const expiry = new Date(now.getTime() + reservationDays * 24 * 60 * 60 * 1000);
    doc.text(`Data prenotazione: ${now.toLocaleDateString('it-IT')} ${now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}`, margin, y);
    y += 6;
    doc.text(`Scadenza prenotazione: ${expiry.toLocaleDateString('it-IT')} (${reservationDays} giorni)`, margin, y);
    y += 12;

    // Dati utente
    if (userData) {
        doc.setLineWidth(0.3);
        doc.line(margin, y, pageWidth - margin, y);
        y += 8;

        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text('DATI PRENOTAZIONE', margin, y);
        y += 8;

        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text(`Nome: ${userData.nome} ${userData.cognome}`, margin, y);
        y += 6;
        doc.text(`Email: ${userData.email}`, margin, y);
        y += 6;
        const address = formatUserAddress(userData);
        if (address) {
            doc.text(`Indirizzo: ${address}`, margin, y);
            y += 10;
        }
    }

    // Elenco libri
    doc.setLineWidth(0.3);
    doc.line(margin, y, pageWidth - margin, y);
    y += 8;

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text(`LIBRI PRENOTATI (${books.length} volumi)`, margin, y);
    y += 10;

    doc.setFontSize(9);
    books.forEach((book, index) => {
        if (y > 260) {
            doc.addPage();
            y = 20;
        }

        doc.setFont('helvetica', 'bold');
        doc.text(`${index + 1}. ${book.titolo?.substring(0, 50) || '-'}`, margin, y);
        y += 5;
        doc.setFont('helvetica', 'normal');
        doc.text(`   Autore: ${book.autore?.substring(0, 40) || '-'} | Inv: ${book.inventario || '-'}`, margin, y);
        y += 7;
    });

    y += 5;

    // Controlla se c'è spazio per le istruzioni (circa 50 pixel necessari)
    if (y > 220) {
        doc.addPage();
        y = 20;
    }

    // Istruzioni
    doc.setLineWidth(0.3);
    doc.line(margin, y, pageWidth - margin, y);
    y += 8;

    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('ISTRUZIONI PER IL RITIRO', margin, y);
    y += 7;
    doc.setFont('helvetica', 'normal');
    doc.text(`1. Presentarsi presso ${library.libraryName || 'la biblioteca'}`, margin, y);
    y += 5;
    doc.text('2. Mostrare questo codice al personale', margin, y);
    y += 5;
    doc.text(`3. Ritirare i libri entro ${reservationDays} giorni dalla prenotazione`, margin, y);
    y += 10;

    // Footer con dati biblioteca (su tutte le pagine)
    const totalPages = doc.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(80, 80, 80);
        doc.setLineWidth(0.2);
        doc.line(margin, 272, pageWidth - margin, 272);
        doc.setFont('helvetica', 'bold');
        doc.text(library.libraryName || 'Biblioteca', pageWidth / 2, 277, { align: 'center', maxWidth: pageWidth - (margin * 2) });
        doc.setFont('helvetica', 'normal');
        if (library.libraryAddress) doc.text(library.libraryAddress, pageWidth / 2, 282, { align: 'center', maxWidth: pageWidth - (margin * 2) });
        const contacts = [
            library.libraryPhone ? `Tel. ${library.libraryPhone}` : '',
            library.libraryEmail ? `email: ${library.libraryEmail}` : '',
        ].filter(Boolean).join(' - ');
        if (contacts) doc.text(contacts, pageWidth / 2, 287, { align: 'center', maxWidth: pageWidth - (margin * 2) });
    }

    doc.save(`prenotazione_${code}.pdf`);
};

const downloadReservationPdfPayload = (payload: ReservationPdfPayload) => {
    const binary = window.atob(payload.contentBase64);
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }
    const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = payload.filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
};

const SuccessModal: React.FC<SuccessModalProps> = ({ 
    lastReservationCode, setLastReservationCode, styles, reservedBooks, userData, reservationDays, reservationPdf, library
}) => {
    const modalRef = useRef<HTMLDivElement>(null);
    const handleClose = useCallback(() => setLastReservationCode(null), [setLastReservationCode]);

    useModalClose(handleClose, true);
    useFocusTrap(modalRef, true);

    const handlePrintPDF = () => {
        if (reservationPdf) {
            downloadReservationPdfPayload(reservationPdf);
            return;
        }
        void generateUserReservationPDF(lastReservationCode, reservedBooks, userData, reservationDays, library);
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex items-center justify-center min-h-screen px-4 text-center">
                <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm" />
                <div ref={modalRef} className="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full relative z-10 animate-bounce-in">
                    <div className="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                        <svg className="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 className={`font-bold text-gray-900 mb-3 ${styles.title} text-2xl`}>Prenotazione Confermata!</h3>
                    <p className={`text-gray-500 mb-6 ${styles.base}`}>Mostrare questo codice al personale per il ritiro dei libri.</p>
                    <div className="bg-gray-100 p-6 rounded-xl border-2 border-dashed border-gray-300 mb-6">
                        <p className="text-xs text-gray-500 uppercase tracking-widest mb-2 font-bold">Codice Prenotazione</p>
                        <p className="text-4xl md:text-5xl font-mono font-bold text-blue-600 tracking-wider select-all">{lastReservationCode}</p>
                    </div>
                    <div className="flex flex-col gap-3">
                        <button 
                            onClick={handlePrintPDF} 
                            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors flex items-center justify-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Stampa riepilogo PDF
                        </button>
                        <button 
                            onClick={handleClose} 
                            className="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-3 px-6 rounded-lg transition-colors"
                        >
                            Chiudi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

// ============================================================================
// COMPONENTE PRINCIPALE APP
// ============================================================================
const DEFAULT_APPEARANCE: AppearanceSettings = {
    primaryColor: '#1e3a8a',
    secondaryColor: '#3b82f6',
    headerOpacity: 1,
    accentColor: '#2563eb',
    backgroundColor: '#f9fafb',
    textColor: '#1f2937',
    fontFamily: "'Titillium Web', sans-serif",
    logoUrl: '',
    logoAlt: 'Logo biblioteca',
    siteTitle: 'Prenotazione Scarto Librario',
    siteSubtitle: 'Biblioteca Statale Stelio Crise',
    contactUrl: '',
    contactLabel: 'Contatti'
};

const App: React.FC = () => {
    const [fontSize, setFontSize] = useState<FontSize>('medium');
    const needsInitialCatalog = !IS_WP_ADMIN || ADMIN_PAGE === 'catalog' || ADMIN_PAGE === 'create-reservation';
    const [loading, setLoading] = useState(needsInitialCatalog);
    const [loadProgress, setLoadProgress] = useState<CatalogLoadProgress>({ loaded: 0, total: 0, percent: 0 });
    const [error, setError] = useState<string | null>(null);

    const [isAuthenticated, setIsAuthenticated] = useState(IS_WP_ADMIN);

    const [books, setBooks] = useState<Book[]>([]);
    const [reservations, setReservations] = useState<PublicReservation[]>([]);
    const [staffReservations, setStaffReservations] = useState<Reservation[]>([]);
    const [staffPagination, setStaffPagination] = useState<StaffPagination>({ page: 1, perPage: 50, total: 0, totalPages: 1 });
    const [staffOrdersLoading, setStaffOrdersLoading] = useState(IS_WP_ADMIN && ADMIN_PAGE === 'reservations');
    const [pendingStaffActions, setPendingStaffActions] = useState<Set<string>>(new Set());
    const [staffActionFeedback, setStaffActionFeedback] = useState<string | null>(null);
    const [appSettings, setAppSettings] = useState<AppSettings>({
        reservationDays: 7,
        maxBooksPerReservation: 20,
        libraryName: 'Biblioteca Statale Stelio Crise',
        libraryAddress: '',
        libraryPhone: '',
        libraryEmail: '',
        homepageUrl: '',
        privacyPolicyUrl: '',
        collectDomicile: false,
        appearance: DEFAULT_APPEARANCE
    });

    const [searchTerm, setSearchTerm] = useState('');
    const [searchPlaceholder, setSearchPlaceholder] = useState('Cerca...');
    const [cart, setCart] = useState<Book[]>([]);
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [lastReservationCode, setLastReservationCode] = useState<string | null>(null);
    const [lastReservationBooks, setLastReservationBooks] = useState<Book[]>([]);
    const [lastReservationUserData, setLastReservationUserData] = useState<Omit<UserData, 'emailConfirm'> | null>(null);
    const [lastReservationPdf, setLastReservationPdf] = useState<ReservationPdfPayload | null>(null);

    const [staffSearch, setStaffSearch] = useState('');
    const [staffSearchQuery, setStaffSearchQuery] = useState('');
    const [staffStatusFilter, setStaffStatusFilter] = useState<'all' | 'active'>('all');
    const [showImport, setShowImport] = useState(ADMIN_PAGE === 'catalog');
    const [headerHeight, setHeaderHeight] = useState(0);
    const [isUploading, setIsUploading] = useState(false);
    const [importFeedback, setImportFeedback] = useState<string | null>(null);

    const [importModalOpen, setImportModalOpen] = useState(false);
    const [resetModalOpen, setResetModalOpen] = useState(false);
    const [settingsModalOpen, setSettingsModalOpen] = useState(false);
    const [pendingImport, setPendingImport] = useState<PreparedImport | null>(null);
    const [forceImportRequired, setForceImportRequired] = useState(0);

    // v8.7.1: Track if user is actively editing to prevent refresh during input
    const [isUserEditing, setIsUserEditing] = useState(false);
    const editingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const availabilityRequestSequenceRef = useRef(0);
    const staffOrdersRequestSequenceRef = useRef(0);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const styles = useMemo(() => getFontStyles(fontSize), [fontSize]);

    const RESERVATION_DURATION_MS = appSettings.reservationDays * 24 * 60 * 60 * 1000;

    const loadData = useCallback(async (showLoading = true) => {
        try {
            if (showLoading) setLoading(true);
            if (showLoading) setLoadProgress({ loaded: 0, total: 0, percent: 0 });
            const data = await api.init(showLoading ? setLoadProgress : undefined);
            setBooks(data.books || []);
            setReservations([]);
            if (data.settings) {
                setAppSettings({
                    reservationDays: data.settings.reservationDays || 7,
                    maxBooksPerReservation: data.settings.maxBooksPerReservation || 20,
                    libraryName: data.settings.libraryName || 'Biblioteca Statale Stelio Crise',
                    libraryAddress: data.settings.libraryAddress || '',
                    libraryPhone: data.settings.libraryPhone || '',
                    libraryEmail: data.settings.libraryEmail || '',
                    homepageUrl: data.settings.homepageUrl || '',
                    privacyPolicyUrl: data.settings.privacyPolicyUrl || '',
                    collectDomicile: false,
                    appearance: { ...DEFAULT_APPEARANCE, ...(data.settings.appearance || {}) }
                });
            }
            setError(null);
        } catch (e) {
            console.error(e);
            if (showLoading) setError('Impossibile connettersi al server. Ricarica la pagina.');
        } finally {
            if (showLoading) setLoading(false);
        }
    }, []);

    const loadStaffOrders = useCallback(async (showLoading = false) => {
        const requestSequence = ++staffOrdersRequestSequenceRef.current;
        if (showLoading) setStaffOrdersLoading(true);
        try {
            const data = await adminApi!.getOrders(staffPagination.page, staffPagination.perPage, staffSearchQuery, staffStatusFilter);
            if (requestSequence !== staffOrdersRequestSequenceRef.current) return;
            setStaffReservations(data.orders || []);
            if (data.pagination) setStaffPagination(data.pagination);
        } catch (e) {
            if (requestSequence !== staffOrdersRequestSequenceRef.current) return;
            if (showLoading) setStaffReservations([]);
            if (IS_WP_ADMIN) setError('Accesso alle prenotazioni non autorizzato. Verifica il ruolo WordPress assegnato.');
        } finally {
            if (showLoading && requestSequence === staffOrdersRequestSequenceRef.current) setStaffOrdersLoading(false);
        }
    }, [staffPagination.page, staffPagination.perPage, staffSearchQuery, staffStatusFilter]);

    const applyStaffStatusFilter = (nextFilter: 'all' | 'active') => {
        setStaffOrdersLoading(true);
        const mustReloadDirectly = nextFilter === staffStatusFilter && staffPagination.page === 1;
        setStaffStatusFilter(nextFilter);
        setStaffPagination(current => ({ ...current, page: 1 }));
        if (mustReloadDirectly) void loadStaffOrders(true);
    };

    useEffect(() => {
        if (!IS_WP_ADMIN || ADMIN_PAGE !== 'reservations') return;
        const timeout = setTimeout(() => {
            startTransition(() => {
                setStaffPagination(current => ({ ...current, page: 1 }));
                setStaffSearchQuery(staffSearch.trim());
            });
        }, 350);
        return () => clearTimeout(timeout);
    }, [staffSearch]);

    const refreshAvailability = useCallback(async () => {
        const requestSequence = ++availabilityRequestSequenceRef.current;
        try {
            const data = await api.getCatalogAvailability();
            if (requestSequence !== availabilityRequestSequenceRef.current) return;

            const stateById = new Map((data.states || []).map(state => [String(state.id), state]));
            setBooks(current => current.map(book => {
                const state = stateById.get(String(book.id));
                if (!state) {
                    return {
                        ...book,
                        _availability: 'available',
                        _reserved: false,
                        _delivered: false,
                        reservedUntil: undefined,
                    };
                }
                return {
                    ...book,
                    _availability: state._availability,
                    _reserved: state._availability === 'reserved',
                    _delivered: state._availability === 'delivered',
                    reservedUntil: state.reservedUntil,
                };
            }));
        } catch (refreshError) {
            console.error('Aggiornamento disponibilita non riuscito.', refreshError);
        }
    }, []);

    useEffect(() => {
        if (IS_WP_ADMIN && ADMIN_PAGE === 'reservations') void loadStaffOrders(true);
    }, [loadStaffOrders]);

    useEffect(() => {
        void loadData(needsInitialCatalog);
    }, [loadData, needsInitialCatalog]);

    // v8.7.1: Track user input to prevent refresh while typing
    useEffect(() => {
        const handleInput = () => {
            setIsUserEditing(true);
            if (editingTimeoutRef.current) {
                clearTimeout(editingTimeoutRef.current);
            }
            editingTimeoutRef.current = setTimeout(() => {
                setIsUserEditing(false);
            }, 3000); // Reset after 3 seconds of no input
        };

        document.addEventListener('input', handleInput, true);
        document.addEventListener('keydown', handleInput, true);

        return () => {
            document.removeEventListener('input', handleInput, true);
            document.removeEventListener('keydown', handleInput, true);
            if (editingTimeoutRef.current) {
                clearTimeout(editingTimeoutRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (IS_WP_ADMIN && ADMIN_PAGE === 'reservations') return;
        const interval = setInterval(() => {
            if (!settingsModalOpen && !importModalOpen && !resetModalOpen) {
                void refreshAvailability();
            }
        }, 60000);
        return () => clearInterval(interval);
    }, [refreshAvailability, settingsModalOpen, importModalOpen, resetModalOpen]);

    useEffect(() => {
        if (!IS_WP_ADMIN || ADMIN_PAGE !== 'reservations') return;
        const interval = setInterval(() => {
            if (!isUserEditing) void loadStaffOrders();
        }, 300000);
        return () => clearInterval(interval);
    }, [loadStaffOrders, isUserEditing]);

    const handleLogout = () => {
        setIsAuthenticated(false);
        setStaffReservations([]);
    };

    const handleImportClick = () => fileInputRef.current?.click();

    const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setForceImportRequired(0);
        const extension = file.name.split('.').pop()?.toLowerCase();
        if (!extension || !['xlsx', 'xls'].includes(extension)) {
            setImportFeedback('File non valido: seleziona un documento .xlsx o .xls.');
            e.target.value = '';
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            setImportFeedback('File troppo grande: il limite e 10 MB.');
            e.target.value = '';
            return;
        }
        e.target.value = '';
        setIsUploading(true);
        setImportFeedback(`Analisi di ${file.name} in corso...`);
        try {
            const prepared = await prepareCatalogFile(file);
            setPendingImport(prepared);
            const warningText = prepared.warnings.length > 0 ? ` Avvisi: ${prepared.warnings.join('; ')}.` : '';
            setImportFeedback(`${prepared.books.length} righe pronte per l'importazione.${warningText}`);
            setImportModalOpen(true);
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'File Excel non leggibile.';
            setPendingImport(null);
            setImportFeedback(`Importazione non eseguita: ${message}`);
        } finally {
            setIsUploading(false);
        }
    };

    const processFileUpload = async (password: string) => {
        if (!pendingImport) return;
        setIsUploading(true);
        try {
            const forced = forceImportRequired > 0;
            const result = await adminApi!.saveBooks(pendingImport.books, password, forced);
            await loadData();
            setImportFeedback(`Importazione completata${forced ? ' con prenotazioni attive preservate' : ''}: ${result.inserted} inseriti, ${result.updated} aggiornati, ${result.deleted} rimossi.`);
            setShowImport(false);
            setPendingImport(null);
            setForceImportRequired(0);
        } catch (err: unknown) {
            if (err instanceof CatalogActiveReservationsError) {
                const activeReservations = Math.max(1, err.activeReservations);
                setForceImportRequired(activeReservations);
                const message = `${activeReservations} prenotazioni sono attive. Verificare che i relativi volumi mantengano lo stesso ID o inventario nel nuovo file, quindi usare “Importa comunque” per procedere senza eliminare le prenotazioni.`;
                setImportFeedback(`Importazione in attesa di conferma: ${message}`);
                throw new Error(message);
            }
            const message = err instanceof Error ? err.message : 'Errore durante il caricamento';
            setImportFeedback(`Importazione non eseguita: ${message}`);
            throw new Error(message);
        } finally {
            setIsUploading(false);
        }
    };

    const handleExport = async () => {
        if (isUploading) return;
        setIsUploading(true);
        setImportFeedback('Preparazione dell’esportazione aggiornata in corso...');
        try {
            const [XLSX, availabilitySnapshot] = await Promise.all([
                import('xlsx'),
                api.getCatalogAvailability(),
            ]);
            const availabilityById = new Map(
                availabilitySnapshot.states.map(state => [String(state.id), state._availability])
            );
            const exportData = books.map(book => {
                const availability = availabilityById.get(String(book.id)) || 'available';
                const {
                    _availability: _ignoredAvailability,
                    _reserved: _ignoredReserved,
                    _delivered: _ignoredDelivered,
                    reservedUntil: _ignoredReservedUntil,
                    ...catalogFields
                } = book;
                const row = {
                    ...catalogFields,
                    'Stato Attuale': availability === 'delivered'
                        ? 'CONSEGNATO'
                        : availability === 'reserved'
                            ? 'PRENOTATO'
                            : 'DISPONIBILE',
                };
                return Object.fromEntries(Object.entries(row).map(([key, value]) => [key, sanitizeSpreadsheetCell(value)]));
            });

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Libri');
            XLSX.writeFile(wb, 'scarto_librario_export.xlsx');
            setImportFeedback(`Esportazione completata: ${exportData.length.toLocaleString('it-IT')} volumi con stato aggiornato.`);
        } catch (error) {
            console.error('Esportazione catalogo non riuscita.', error);
            setImportFeedback('Esportazione non riuscita: impossibile acquisire lo stato aggiornato dei volumi. Riprova.');
        } finally {
            setIsUploading(false);
        }
    };

    const handleResetClick = () => setResetModalOpen(true);

    const handleResetConfirm = async (password: string) => {
        await adminApi!.reset(password);
        await loadData();
        setCart([]);
        setLastReservationCode(null);
        setLastReservationPdf(null);
    };

    const requestReservationVerification = async (userData: Omit<UserData, 'emailConfirm'>) => {
        if (cart.length === 0) throw new Error('Nessun libro selezionato.');
        return api.requestReservationVerification(cart, userData);
    };

    const confirmReservation = async (
        requestId: string,
        verificationCode: string,
        userData: Omit<UserData, 'emailConfirm'>
    ) => {
        const result = await api.confirmReservation(requestId, verificationCode);
        const confirmedBooks = [...cart];
        const confirmedBookIds = new Set(confirmedBooks.map(book => book.id));
        const reservedUntil = Date.now() + RESERVATION_DURATION_MS;
        availabilityRequestSequenceRef.current += 1;
        setLastReservationBooks(confirmedBooks);
        setLastReservationUserData(userData);
        setLastReservationPdf(result.reservationPdf || null);
        setBooks(current => current.map(book => confirmedBookIds.has(book.id) ? {
            ...book,
            _availability: 'reserved',
            _reserved: true,
            _delivered: false,
            reservedUntil,
        } : book));
        setCart([]);
        setIsCartOpen(false);
        setLastReservationCode(result.code);
        void refreshAvailability();
    };

    const refreshCatalog = async () => {
        await refreshAvailability();
    };

    const handleStaffAction = async (code: string, action: 'complete' | 'cancel' | 'revoke') => {
        if (!isAuthenticated) {
            alert('Sessione scaduta. Effettua nuovamente il login.');
            handleLogout();
            return;
        }
        if (pendingStaffActions.has(code)) return;
        const previousReservations = staffReservations;
        const nextStatus: ReservationStatus = action === 'complete' ? 'completed' : 'cancelled';
        setPendingStaffActions(current => new Set(current).add(code));
        setStaffActionFeedback(`Aggiornamento della prenotazione ${code} in corso...`);
        setStaffReservations(current => current.map(reservation =>
            reservation.code === code
                ? { ...reservation, status: nextStatus, updatedAt: Date.now(), completedAt: action === 'complete' ? Date.now() : reservation.completedAt }
                : reservation
        ));
        try {
            await adminApi!.updateStatus(code, action);
            await loadStaffOrders();
            setStaffActionFeedback(`Prenotazione ${code} aggiornata correttamente.`);
        } catch (e) {
            setStaffReservations(previousReservations);
            setStaffActionFeedback(e instanceof Error ? e.message : 'Errore durante l\'aggiornamento dello stato.');
        } finally {
            setPendingStaffActions(current => {
                const next = new Set(current);
                next.delete(code);
                return next;
            });
        }
    };

    const handleResendEmail = async (code: string) => {
        if (pendingStaffActions.has(code)) return;
        setPendingStaffActions(current => new Set(current).add(code));
        setStaffActionFeedback(`Reinvio del riepilogo della prenotazione ${code} in corso...`);
        try {
            const result = await adminApi!.resendReservationEmail(code);
            setStaffActionFeedback(`${result.message} Prenotazione ${code}.`);
        } catch (error) {
            setStaffActionFeedback(error instanceof Error ? error.message : 'Reinvio email non riuscito.');
        } finally {
            setPendingStaffActions(current => {
                const next = new Set(current);
                next.delete(code);
                return next;
            });
        }
    };

    const handleStaffReservationCreated = (bookIds: string[]) => {
        const reserved = new Set(bookIds);
        const reservedUntil = Date.now() + RESERVATION_DURATION_MS;
        setBooks(current => current.map(book => reserved.has(book.id) ? {
            ...book,
            _availability: 'reserved',
            _reserved: true,
            _delivered: false,
            reservedUntil,
        } : book));
    };

    const toggleCartItem = (book: Book, isDisabled: boolean) => {
        if (isDisabled) return;
        setCart(prev => prev.find(i => i.id === book.id) ? prev.filter(i => i.id !== book.id) : [...prev, book]);
    };

    const bookStates = useMemo(() => {
        const states = new Map<string, BookState>();
        books.forEach(book => {
            if (book._availability === 'delivered' || book._delivered) {
                states.set(book.id, { status: 'gone' });
            } else if (book._availability === 'reserved' || book._reserved) {
                states.set(book.id, { status: 'reserved', expiryDate: book.reservedUntil });
            }
        });
        reservations.forEach(res => {
            if (res.status === 'completed') {
                res.bookIds.forEach(id => states.set(id, { status: 'gone' }));
            } else if (res.status === 'active') {
                res.bookIds.forEach(id => states.set(id, { status: 'reserved', expiryDate: res.createdAt + RESERVATION_DURATION_MS }));
            }
        });
        return states;
    }, [books, reservations, RESERVATION_DURATION_MS]);

    const filteredBooks = useMemo(() => {
        if (!searchTerm) return books;
        const terms = searchTerm.toLowerCase().split(/\s+/).filter(t => t.length > 0);
        return books.filter(book => {
            const bookString = `${book.titolo} ${book.autore} ${book.inventario}`.toLowerCase();
            return terms.every(term => bookString.includes(term));
        });
    }, [books, searchTerm]);

    const staffFilteredReservations = staffReservations;

    const adminFilteredBooks = useMemo(() => {
        if (!staffSearch) return books;
        const terms = staffSearch.toLowerCase().split(/\s+/).filter(Boolean);
        return books.filter(book => {
            const searchable = `${book.titolo} ${book.autore} ${book.inventario} ${book.scatola}`.toLowerCase();
            return terms.every(term => searchable.includes(term));
        });
    }, [books, staffSearch]);

    useEffect(() => {
        const handleResize = () => setSearchPlaceholder(window.innerWidth < 640 ? 'Cerca titolo, autore...' : 'Cerca per titolo, autore o numero inventario...');
        handleResize();
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    if (error) {
        return (
            <div className="flex h-screen items-center justify-center flex-col text-red-600 gap-4 p-4">
                <h1 className="text-2xl font-bold">Errore di Connessione</h1>
                <p className="text-center">{error}</p>
                <button onClick={() => { void loadData(); }} className="px-4 py-2 bg-red-100 rounded hover:bg-red-200">Riprova</button>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="scarto-loading-screen flex h-screen items-center justify-center text-blue-900 font-bold p-6" aria-live="polite" aria-busy="true">
                <div className="scarto-loading-panel">
                    <div className="animate-spin rounded-full h-14 w-14 border-t-4 border-b-4 border-blue-900" aria-hidden="true" />
                    <p>Caricamento dati dal server<span className="scarto-loading-dots" aria-hidden="true"><i>.</i><i>.</i><i>.</i></span></p>
                    <div
                        className="scarto-progress-track"
                        role="progressbar"
                        aria-label="Avanzamento caricamento catalogo"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={loadProgress.percent}
                    >
                        <span style={{ width: `${loadProgress.percent}%` }} />
                    </div>
                    <small>{loadProgress.total > 0
                        ? `${loadProgress.loaded.toLocaleString('it-IT')} di ${loadProgress.total.toLocaleString('it-IT')} volumi (${loadProgress.percent}%)`
                        : 'Connessione e preparazione dei dati in corso'}</small>
                </div>
            </div>
        );
    }

    if (IS_WP_ADMIN) {
        return (
            <div className={`min-h-screen flex flex-col bg-gray-100 font-sans text-gray-800 ${styles.base}`}>
                <StaffHeader
                    styles={styles} fontSize={fontSize} setFontSize={setFontSize}
                    showImport={showImport} setShowImport={setShowImport} staffSearch={staffSearch}
                    setStaffSearch={setStaffSearch} setHeaderHeight={setHeaderHeight} onLogout={handleLogout}
                    isAuthenticated={true} onOpenSettings={() => setSettingsModalOpen(true)}
                />

                {ADMIN_PAGE === 'catalog' && (
                    <DataManagementPanel styles={styles} onImportClick={handleImportClick} onResetClick={handleResetClick} handleExport={handleExport} isUploading={isUploading} importFeedback={importFeedback} />
                )}
                <div className="flex-1 container mx-auto p-4 md:p-6">
                    {staffActionFeedback && ADMIN_PAGE === 'reservations' && (
                        <div className="mb-4 rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-900" role="status" aria-live="polite">
                            {staffActionFeedback}
                        </div>
                    )}
                    {ADMIN_PAGE === 'create-reservation' ? (
                        <StaffReservationCreate books={books} onCreated={handleStaffReservationCreated} />
                    ) : ADMIN_PAGE === 'catalog' ? (
                        <AdminCatalogTable books={adminFilteredBooks} styles={styles} />
                    ) : (
                        <>
                            <div className="mb-4 flex flex-wrap items-center gap-4 rounded border border-gray-200 bg-white p-4 shadow-sm">
                                <fieldset className="m-0 border-0 p-0">
                                    <legend className="mb-2 font-semibold text-gray-800">Stato prenotazioni</legend>
                                    <div className="inline-flex overflow-hidden rounded border border-gray-300" role="group" aria-label="Filtra prenotazioni per stato">
                                        <button
                                            type="button"
                                            aria-pressed={staffStatusFilter === 'all'}
                                            onClick={() => applyStaffStatusFilter('all')}
                                            className={`border-0 px-4 py-2 font-semibold transition-colors ${staffStatusFilter === 'all' ? '!bg-gray-800 !text-white' : '!bg-white !text-gray-700 hover:!bg-gray-100'}`}
                                        >
                                            Tutte le prenotazioni
                                        </button>
                                        <button
                                            type="button"
                                            aria-pressed={staffStatusFilter === 'active'}
                                            onClick={() => applyStaffStatusFilter('active')}
                                            className={`border-0 border-l border-gray-300 px-4 py-2 font-semibold transition-colors ${staffStatusFilter === 'active' ? '!bg-yellow-600 !text-white' : '!bg-white !text-gray-700 hover:!bg-yellow-50'}`}
                                        >
                                            Solo pendenti
                                        </button>
                                    </div>
                                </fieldset>
                                <div className="min-w-60 flex-1">
                                    <p className="m-0 text-sm text-gray-600">Le pendenti sono prenotazioni confermate dall'utente, ma non ancora registrate come consegnate, annullate o scadute.</p>
                                    <p className="mt-1 mb-0 text-sm font-semibold text-gray-800" role="status" aria-live="polite">
                                        {staffOrdersLoading
                                            ? `Applicazione filtro ${staffStatusFilter === 'active' ? 'Solo pendenti' : 'Tutte le prenotazioni'} in corso...`
                                            : `Filtro attivo: ${staffStatusFilter === 'active' ? 'Solo pendenti' : 'Tutte le prenotazioni'} · ${staffPagination.total.toLocaleString('it-IT')} risultati`}
                                    </p>
                                </div>
                            </div>
                            <ReservationsTable reservations={staffFilteredReservations} books={books} handleStaffAction={handleStaffAction} handleResendEmail={handleResendEmail} pendingActions={pendingStaffActions} loading={staffOrdersLoading} styles={styles} headerHeight={headerHeight} library={appSettings} pagination={staffPagination} onPageChange={page => setStaffPagination(current => ({ ...current, page }))} />
                        </>
                    )}
                </div>

                {ADMIN_PAGE === 'catalog' && (
                    <>
                        <input ref={fileInputRef} type="file" accept=".xlsx,.xls" onChange={handleFileSelect} className="hidden" />
                        <PasswordModal
                            isOpen={importModalOpen}
                            onClose={() => { setImportModalOpen(false); setPendingImport(null); setForceImportRequired(0); }}
                            onSubmit={processFileUpload}
                            title={forceImportRequired > 0 ? 'Conferma aggiornamento catalogo' : 'Password di Sicurezza'}
                            description={forceImportRequired > 0
                                ? `${forceImportRequired} prenotazioni sono attive. Le prenotazioni e i dati storici saranno conservati. Prima di procedere verificare che i volumi prenotati mantengano lo stesso ID o inventario nel nuovo file.`
                                : `Il file è valido: ${pendingImport?.books.length || 0} righe pronte. Inserisci la password per confermare l'importazione.`}
                            submitLabel={forceImportRequired > 0 ? 'Importa comunque' : 'Importa dati'}
                            isDanger={forceImportRequired > 0}
                        />
                        <PasswordModal isOpen={resetModalOpen} onClose={() => setResetModalOpen(false)} onSubmit={handleResetConfirm} title="Conferma Reset Totale" description="ATTENZIONE: questa operazione elimina catalogo e prenotazioni. Verifica il backup prima di procedere." submitLabel="Elimina Tutto" isDanger />
                    </>
                )}
            </div>
        );
    }

    const appearanceStyle = {
        '--scarto-primary': appSettings.appearance.primaryColor,
        '--scarto-secondary': appSettings.appearance.secondaryColor,
        '--scarto-header-opacity': String(appSettings.appearance.headerOpacity),
        '--scarto-accent': appSettings.appearance.accentColor,
        '--scarto-background': appSettings.appearance.backgroundColor,
        '--scarto-text': appSettings.appearance.textColor,
        backgroundColor: appSettings.appearance.backgroundColor,
        color: appSettings.appearance.textColor,
        fontFamily: appSettings.appearance.fontFamily
    } as React.CSSProperties;

    return (
        <div className={`scarto-public-app min-h-screen flex flex-col ${styles.base}`} style={appearanceStyle}>
            <PublicHeader
                styles={styles} fontSize={fontSize} setFontSize={setFontSize}
                searchTerm={searchTerm} setSearchTerm={setSearchTerm} searchPlaceholder={searchPlaceholder}
                filteredCount={filteredBooks.length} setHeaderHeight={setHeaderHeight}
                homepageUrl={appSettings.homepageUrl || ''}
                privacyPolicyUrl={appSettings.privacyPolicyUrl || ''}
                appearance={appSettings.appearance}
            />

            <div className="flex-1 container mx-auto p-4 pb-24">
                <BooksTable
                    filteredBooks={filteredBooks} books={books} cart={cart} toggleCartItem={toggleCartItem}
                    bookStates={bookStates} styles={styles} headerHeight={headerHeight}
                />
            </div>

            {cart.length > 0 && (
                <div className="fixed bottom-6 right-6 z-40 animate-bounce-in">
                    <button onClick={() => setIsCartOpen(true)} className="scarto-accent-button !text-white rounded-full px-8 py-5 shadow-lg flex items-center gap-4 transition-transform hover:scale-105">
                        <div className="relative">
                            <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 flex items-center justify-center rounded-full border-2 border-blue-600">{cart.length}</span>
                        </div>
                        <span className="font-bold text-xl">Il mio ordine</span>
                    </button>
                </div>
            )}

            {isCartOpen && (
                <CartModal
                    cart={cart} toggleCartItem={toggleCartItem}
                    requestReservationVerification={requestReservationVerification}
                    confirmReservation={confirmReservation}
                    setIsCartOpen={setIsCartOpen} styles={styles} isAuthenticated={false}
                    maxBooks={appSettings.maxBooksPerReservation} reservationDays={appSettings.reservationDays}
                    privacyPolicyUrl={appSettings.privacyPolicyUrl || ''}
                    refreshCatalog={refreshCatalog}
                />
            )}

            {lastReservationCode && (
                <SuccessModal 
                    lastReservationCode={lastReservationCode} 
                    setLastReservationCode={setLastReservationCode} 
                    styles={styles}
                    reservedBooks={lastReservationBooks}
                    userData={lastReservationUserData}
                    reservationDays={appSettings.reservationDays}
                    reservationPdf={lastReservationPdf}
                    library={appSettings}
                />
            )}
        </div>
    );
};

// ============================================================================
// INIZIALIZZAZIONE REACT
// ============================================================================
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<App />);
}
