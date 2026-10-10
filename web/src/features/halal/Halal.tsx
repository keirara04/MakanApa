import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { ApiError } from '../../api/client'
import type { CertificationAuthority, HalalInfo, HalalReport, MyHalalReport } from '../../api/decide'
import { AUTHORITY_LABELS, CLAIM_LABELS, halalApi, halalDate, photoLimits, type HalalClaim, type HistoryEntry } from '../../api/halal'
import { nearbyApi } from '../../api/nearby'
import { submissionsApi } from '../../api/submissions'
import { errorMessage } from '../../lib/errors'
import { resizeForUpload } from '../../lib/photos'
import { Button } from '../../ui/Button'
import { Field } from '../../ui/Field'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { useContributionGate } from '../auth/ContributionGate'

const CLAIMS: HalalClaim[] = ['certified', 'muslim_friendly', 'non_halal']
const CLAIM_HINTS: Record<HalalClaim, string> = {
  certified: 'Only a valid halal certificate counts. We’ll verify it.',
  muslim_friendly: 'No halal certificate, but you know something useful (e.g. Muslim-owned, no pork or alcohol on the menu). Tell us how you know.',
  non_halal: 'Pork, lard or alcohol served. A photo of the menu helps.',
}

/**
 * Halal on a place: the status as the server words it, where it came from, your own vouch and
 * where it is in review, approved evidence, and ways to vouch, see the history or claim the
 * place. A vouch never changes the status by itself: the team approves it first.
 */
export function HalalSection({ restaurantId, name, halal: given }: { restaurantId: number; name: string; halal: HalalInfo }) {
  const queryClient = useQueryClient()
  const [fresh, setFresh] = useState<{ id: number; halal: HalalInfo } | null>(null)
  const halal = fresh?.id === restaurantId ? fresh.halal : given
  const [sheet, setSheet] = useState<'vouch' | 'claim' | 'history' | null>(null)
  const vouchGate = useContributionGate('vouch for halal info')
  const claimGate = useContributionGate('claim this place')
  const mine = halal.myReport ?? null
  const demand = !halal.verification && halal.recentPickers >= 2 ? `${halal.recentPickers} people picked this place in the last 30 days. Know if it’s halal? Help them out.` : null

  // After a vouch, show "waiting for review" from the server rather than guessing.
  async function refresh() {
    const details = await nearbyApi.details(restaurantId).catch(() => null)
    if (!details) return
    setFresh({ id: restaurantId, halal: details.halal })
    queryClient.setQueryData(['place', restaurantId], details)
  }

  return (
    <section aria-labelledby="halal-title" className="flex flex-col gap-3 rounded-card border-2 border-hairline bg-surface px-5 py-4 text-left">
      <h2 id="halal-title" className="font-display text-lg font-bold">
        Halal
      </h2>
      <div>
        <p className="font-semibold">{halal.display.longLabel}</p>
        {halal.display.verificationLabel && <p className="text-sm text-kicap-soft">{halal.display.verificationLabel}</p>}
        {halal.status === 'certified' && halal.verification?.expiresAt && <p className="text-sm text-kicap-soft">Certificate valid until {halalDate(halal.verification.expiresAt)}</p>}
      </div>

      {mine ? (
        <MyVouch report={mine} onContinue={() => vouchGate.run(() => setSheet('vouch'))} />
      ) : (
        <>
          {demand && <p className="text-sm">{demand}</p>}
          <button
            type="button"
            onClick={() => vouchGate.run(() => setSheet('vouch'))}
            className="press flex min-h-14 items-center gap-3 rounded-row border-2 border-kicap bg-cream px-4 py-3 text-left"
          >
            <span className="flex-1">
              <span className="block font-semibold">{halal.status === 'unknown' ? 'Know if this place is halal?' : 'Is this still accurate?'}</span>
              <span className="text-sm text-kicap-soft">Vouch for it. Our team reviews every vouch.</span>
            </span>
            <Chevron />
          </button>
        </>
      )}

      {(halal.reports ?? []).map((report) => (
        <ReportCard key={report.id} report={report} />
      ))}

      <p className="text-sm text-kicap-soft">Community-sourced: halal info may be incomplete. Always double-check at the restaurant.</p>
      <div className="flex flex-wrap items-center justify-between gap-2">
        {halal.historyCount > 1 ? (
          <button type="button" onClick={() => setSheet('history')} className="min-h-11 font-semibold text-sambal-deep underline decoration-2 underline-offset-4">
            History ({halal.historyCount})
          </button>
        ) : (
          <span />
        )}
        <button type="button" onClick={() => claimGate.run(() => setSheet('claim'))} className="min-h-11 text-kicap-soft underline decoration-2 underline-offset-4">
          Own this place?
        </button>
      </div>

      {/* Stays open after sending, while the refresh turns the prompt into "waiting for review". */}
      {sheet === 'vouch' && (
        <VouchSheet restaurantId={restaurantId} name={name} existing={mine && (mine.status === 'draft' || mine.status === 'changes_requested') ? mine : null} onClose={() => setSheet(null)} onSent={() => void refresh()} />
      )}
      {sheet === 'claim' && <OwnerClaimSheet restaurantId={restaurantId} name={name} onClose={() => setSheet(null)} />}
      {sheet === 'history' && <HistorySheet restaurantId={restaurantId} name={name} onClose={() => setSheet(null)} />}
      {vouchGate.gate}
      {claimGate.gate}
    </section>
  )
}

function Chevron() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true">
      <path d="m9 6 6 6-6 6" />
    </svg>
  )
}

const MY_VOUCH: Record<MyHalalReport['status'], { tone: string; title: (claim: string) => string }> = {
  pending: { tone: 'bg-kunyit/25', title: (claim) => `You vouched ${claim}. Waiting for review.` },
  changes_requested: { tone: 'bg-sambal/10', title: () => 'Our team needs a bit more evidence' },
  approved: { tone: 'bg-pandan/15', title: () => 'Your vouch was approved. Thank you!' },
  rejected: { tone: 'bg-sambal/10', title: () => 'Your vouch couldn’t be verified' },
  draft: { tone: 'bg-kunyit/25', title: () => 'Your vouch isn’t sent yet' },
}

function MyVouch({ report, onContinue }: { report: MyHalalReport; onContinue: () => void }) {
  const look = MY_VOUCH[report.status] ?? MY_VOUCH.draft
  return (
    <div className={`flex flex-col items-start gap-1 rounded-row px-4 py-3 ${look.tone}`}>
      <p className="font-semibold">{look.title(report.claim ? CLAIM_LABELS[report.claim].toLowerCase() : 'its halal status')}</p>
      {report.reviewNote && <p className="text-sm">{report.reviewNote}</p>}
      {(report.status === 'draft' || report.status === 'changes_requested') && (
        <button type="button" onClick={onContinue} className="min-h-11 font-semibold text-sambal-deep underline decoration-2 underline-offset-4">
          {report.status === 'draft' ? 'Finish your vouch' : 'Add evidence'}
        </button>
      )}
    </div>
  )
}

function ReportCard({ report }: { report: HalalReport }) {
  return (
    <article className={`flex flex-col gap-1.5 rounded-row border border-hairline px-4 py-3 ${report.isCurrent ? 'bg-cream' : 'bg-cream/50 text-kicap/85'}`}>
      <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        {report.isCurrent && <span className="rounded-full bg-pandan/15 px-2 py-0.5 text-xs font-semibold text-pandan">Current evidence</span>}
        <span className="font-semibold">{report.userName}</span>
        {report.userTrusted && <span className="text-xs font-semibold text-pandan">Trusted contributor</span>}
        {report.approvedAt && <span className="text-kicap-soft">{halalDate(report.approvedAt)}</span>}
      </p>
      {/* The claim and what the team concluded are both kept: show both when they differ. */}
      {report.claim && report.resolvedStatus && report.claim !== report.resolvedStatus && (
        <p className="text-sm text-kicap-soft">
          Reported {CLAIM_LABELS[report.claim].toLowerCase()}, reviewed as {CLAIM_LABELS[report.resolvedStatus].toLowerCase()}
        </p>
      )}
      {report.comment && <p className="italic">“{report.comment}”</p>}
      {report.photos.length > 0 && (
        <ul className="flex gap-2 overflow-x-auto">
          {report.photos.map((photo) => (
            <li key={photo.id} className="shrink-0">
              <a href={photo.url} target="_blank" rel="noreferrer">
                <img src={photo.url} alt="Evidence photo" className="h-16 w-16 rounded-xl object-cover" loading="lazy" />
              </a>
            </li>
          ))}
        </ul>
      )}
    </article>
  )
}

// ---- Photos picked in a sheet

function Thumb({ file }: { file: File }) {
  // ponytail: object URLs aren't revoked (StrictMode would revoke the memoised one); a few photos per sheet.
  const url = useMemo(() => URL.createObjectURL(file), [file])
  return <img src={url} alt="" className="h-full w-full object-cover" />
}

function PhotoPicker({ label, files, limit, onChange }: { label: string; files: File[]; limit: number; onChange: (files: File[]) => void }) {
  return (
    <div className="flex flex-col gap-2">
      <span className="font-display text-base font-semibold">{label}</span>
      <div className="flex flex-wrap gap-2">
        {files.map((file, at) => (
          <div key={`${file.name}-${at}`} className="relative h-20 w-20 overflow-hidden rounded-row bg-hairline">
            <Thumb file={file} />
            <button
              type="button"
              aria-label={`Remove photo ${at + 1}`}
              onClick={() => onChange(files.filter((_, index) => index !== at))}
              className="absolute top-0 right-0 grid h-8 w-8 place-items-center rounded-bl-xl bg-kicap/70 text-white"
            >
              <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>
        ))}
        {files.length < limit && (
          <label className="press grid h-20 w-20 cursor-pointer place-items-center rounded-row border-2 border-dashed border-sambal/50 bg-sambal/5 text-sm font-semibold text-sambal-deep focus-within:outline-3 focus-within:outline-sambal">
            Add
            <input
              type="file"
              accept="image/*"
              multiple={limit - files.length > 1}
              className="sr-only"
              aria-label={label}
              onChange={(event) => {
                onChange([...files, ...Array.from(event.target.files ?? [])].slice(0, limit))
                event.target.value = ''
              }}
            />
          </label>
        )}
      </div>
    </div>
  )
}

/** Uploads what isn't on the server yet, so a retry after a failed step never sends a photo twice. */
async function uploadAll(submissionId: number, photos: { file: File; type: string }[], sent: Set<File>, onProgress: (step: string) => void) {
  const todo = photos.filter((photo) => !sent.has(photo.file))
  for (const [index, photo] of todo.entries()) {
    onProgress(`Uploading photo ${index + 1} of ${todo.length}…`)
    let jpeg: Blob
    try {
      jpeg = await resizeForUpload(photo.file)
    } catch {
      throw new Error('Couldn’t read one of your photos. Remove it and pick it again.')
    }
    await submissionsApi.uploadPhoto(submissionId, jpeg, photo.type)
    sent.add(photo.file)
  }
}

function failure(caught: unknown, rateLimited: string): string {
  if (caught instanceof ApiError) return caught.status === 429 ? rateLimited : errorMessage(caught)
  return caught instanceof Error ? caught.message : 'Something went wrong. Try again in a bit.'
}

// ---- Vouch

function VouchSheet({ restaurantId, name, existing, onClose, onSent }: { restaurantId: number; name: string; existing: MyHalalReport | null; onClose: () => void; onSent: () => void }) {
  const [claim, setClaim] = useState<HalalClaim>(existing?.claim && existing.claim !== 'unknown' ? existing.claim : 'certified')
  const [comment, setComment] = useState(existing?.comment ?? '')
  const [authority, setAuthority] = useState<CertificationAuthority>(existing?.certificationAuthority ?? 'jakim')
  const [certificateNumber, setCertificateNumber] = useState(existing?.certificateNumber ?? '')
  const [knowsExpiry, setKnowsExpiry] = useState(Boolean(existing?.certificateExpiresAt))
  const [expiresAt, setExpiresAt] = useState(() => existing?.certificateExpiresAt ?? isoDate(new Date(Date.now() + 365 * 86_400_000)))
  const [certFiles, setCertFiles] = useState<File[]>([])
  const [otherFiles, setOtherFiles] = useState<File[]>([])
  const [sending, setSending] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [sent] = useState(() => new Set<File>())
  const [today] = useState(() => isoDate(new Date()))

  const limits = photoLimits(claim, existing)
  const attached = existing?.photoCount ?? 0
  const selected = (claim === 'certified' ? certFiles.length : 0) + otherFiles.length
  const tooMany = selected > limits.slots
  const needsCert = claim === 'certified' && certFiles.length === 0 && (existing?.certPhotoCount ?? 0) === 0
  const hasEvidence = comment.trim() !== '' || selected > 0 || attached > 0
  const canSend = !needsCert && hasEvidence && !tooMany && sending === null

  async function send() {
    setError(null)
    try {
      setSending('Saving…')
      const certified = claim === 'certified'
      const { submission } = await halalApi.report(restaurantId, {
        claim,
        comment: comment.trim() || null,
        certificationAuthority: certified ? authority : null,
        certificateNumber: certified && certificateNumber.trim() ? certificateNumber.trim() : null,
        certificateExpiresAt: certified && knowsExpiry ? expiresAt : null,
      })
      const photos = [...(certified ? certFiles.map((file) => ({ file, type: 'halal_cert' })) : []), ...otherFiles.map((file) => ({ file, type: 'storefront' }))]
      await uploadAll(submission.id, photos, sent, setSending)
      setSending('Sending…')
      await submissionsApi.submit(submission.id)
      setDone(true)
      onSent()
    } catch (caught) {
      setError(failure(caught, 'You’ve sent a lot of reports. Wait a bit, or for some to be reviewed.'))
    } finally {
      setSending(null)
    }
  }

  return (
    <Sheet open onClose={onClose} title={done ? 'Thanks for vouching!' : 'Vouch for this place'}>
      {done ? (
        <Done onClose={onClose}>Our team reviews every vouch before it shows to others. You’ll get a notification once it’s checked.</Done>
      ) : (
        <>
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 font-display text-base font-semibold">{name} is…</legend>
            {CLAIMS.map((option) => (
              <label key={option} className="flex min-h-12 cursor-pointer items-center gap-3 rounded-row border-2 border-hairline bg-cream px-4 has-checked:border-kicap">
                <input type="radio" name="claim" value={option} checked={claim === option} onChange={() => setClaim(option)} className="h-5 w-5 accent-sambal" />
                <span className="font-semibold">{CLAIM_LABELS[option]}</span>
              </label>
            ))}
            <p className="text-sm text-kicap-soft">{CLAIM_HINTS[claim]}</p>
          </fieldset>

          {claim === 'certified' && (
            <div className="flex flex-col gap-4 rounded-row bg-cream px-4 py-4">
              {limits.cert > 0 ? (
                <PhotoPicker label={(existing?.certPhotoCount ?? 0) > 0 ? 'Another certificate photo' : 'Certificate photo (required)'} files={certFiles} limit={limits.cert} onChange={setCertFiles} />
              ) : (
                (existing?.certPhotoCount ?? 0) > 0 && <p className="text-sm text-kicap-soft">Certificate photo already attached.</p>
              )}
              <label className="flex flex-col gap-1.5">
                <span className="font-display text-base font-semibold">Issued by</span>
                <select value={authority} onChange={(event) => setAuthority(event.target.value as CertificationAuthority)} className="min-h-13 rounded-row border-2 border-hairline bg-surface px-3">
                  {Object.entries(AUTHORITY_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <Field label="Certificate number (if readable)" value={certificateNumber} onChange={(event) => setCertificateNumber(event.target.value.toUpperCase())} maxLength={60} />
              <label className="flex min-h-11 items-center gap-3">
                <input type="checkbox" checked={knowsExpiry} onChange={(event) => setKnowsExpiry(event.target.checked)} className="h-5 w-5 accent-sambal" />
                I can see the expiry date
              </label>
              {knowsExpiry && <Field label="Expires" type="date" value={expiresAt} min={today} onChange={(event) => setExpiresAt(event.target.value)} />}
              <p className="text-sm text-kicap-soft">Our team checks the certificate against the authority’s directory before anything is shown as Halal.</p>
            </div>
          )}

          <label className="flex flex-col gap-1.5">
            <span className="font-display text-base font-semibold">How do you know?</span>
            <textarea
              value={comment}
              onChange={(event) => setComment(event.target.value)}
              rows={3}
              maxLength={1000}
              placeholder={claim === 'muslim_friendly' ? 'e.g. Owner is Muslim, no alcohol on the menu' : 'e.g. Cert displayed beside the cashier'}
              className="rounded-row border-2 border-hairline bg-surface px-4 py-3 placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
            />
          </label>

          {limits.other > 0 && <PhotoPicker label="Storefront or menu photos" files={otherFiles} limit={limits.other} onChange={setOtherFiles} />}
          <div className="text-sm text-kicap-soft">
            {attached > 0 && <p>{attached === 1 ? '1 photo' : `${attached} photos`} from before are already attached, so no need to add them again.</p>}
            {tooMany && <p className="text-sambal-deep">That’s more photos than fit. Only {limits.slots} more can be added to this vouch.</p>}
            <p>Your name and comment are shown with your vouch once approved.</p>
          </div>

          {error && (
            <p role="alert" className="text-sambal-deep">
              {error}
            </p>
          )}
          {sending && <p role="status" className="text-kicap-soft">{sending}</p>}
          <Button onClick={() => void send()} disabled={!canSend} busy={sending !== null}>
            Send vouch for review
          </Button>
          {!sending && (needsCert || !hasEvidence) && <p className="-mt-2 text-center text-sm text-kicap-soft">{needsCert ? 'Add a photo of the certificate to send this.' : 'Say how you know, or add a photo.'}</p>}
          <Button variant="quiet" onClick={onClose}>
            Cancel
          </Button>
        </>
      )}
    </Sheet>
  )
}

function isoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function Done({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  return (
    <>
      <Mascot mood="excited" size={96} className="mx-auto" />
      <p className="text-center text-kicap-soft">{children}</p>
      <Button onClick={onClose}>Close</Button>
    </>
  )
}

// ---- Owner claim

function OwnerClaimSheet({ restaurantId, name, onClose }: { restaurantId: number; name: string; onClose: () => void }) {
  const [phone, setPhone] = useState('')
  const [notes, setNotes] = useState('')
  const [proof, setProof] = useState<File[]>([])
  const [sending, setSending] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [sent] = useState(() => new Set<File>())

  async function send() {
    setError(null)
    try {
      setSending('Saving…')
      const { submission } = await halalApi.ownerClaim(restaurantId, { contactPhone: phone.trim(), notes: notes.trim() || null })
      await uploadAll(
        submission.id,
        proof.map((file) => ({ file, type: 'other' })),
        sent,
        setSending,
      )
      setSending('Sending…')
      await submissionsApi.submit(submission.id)
      setDone(true)
    } catch (caught) {
      setError(failure(caught, 'Too many tries in a row. Try again in a minute.'))
    } finally {
      setSending(null)
    }
  }

  return (
    <Sheet open onClose={onClose} title={done ? 'Claim sent' : `Claim ${name}`}>
      {done ? (
        <Done onClose={onClose}>Thanks! Our team will verify your ownership and get back to you.</Done>
      ) : (
        <>
          <p className="text-kicap-soft">Verified owners’ halal evidence is marked as coming from the owner. It’s still reviewed.</p>
          <Field label="Contact phone" type="tel" value={phone} onChange={(event) => setPhone(event.target.value)} maxLength={30} autoComplete="tel" />
          <label className="flex flex-col gap-1.5">
            <span className="font-display text-base font-semibold">Anything we should know? (optional)</span>
            <textarea value={notes} onChange={(event) => setNotes(event.target.value)} rows={2} maxLength={500} className="rounded-row border-2 border-hairline bg-surface px-4 py-3 focus:border-kicap focus:outline-none" />
          </label>
          <PhotoPicker label="Proof photo (required)" files={proof} limit={2} onChange={setProof} />
          <p className="-mt-2 text-sm text-kicap-soft">Business licence (SSM or premise licence) or a photo of you at the signboard. Proof photos stay private. Only our team sees them.</p>
          {error && (
            <p role="alert" className="text-sambal-deep">
              {error}
            </p>
          )}
          {sending && <p role="status" className="text-kicap-soft">{sending}</p>}
          <Button onClick={() => void send()} disabled={!phone.trim() || proof.length === 0} busy={sending !== null}>
            Send claim
          </Button>
          <Button variant="quiet" onClick={onClose}>
            Cancel
          </Button>
        </>
      )}
    </Sheet>
  )
}

// ---- History

const METHOD_LABELS: Record<string, string> = {
  automatic: 'Flagged automatically from its listing',
  registry_verified: 'Checked against the official registry',
  administrator_override: 'Set by the MakanApa team',
}

function HistorySheet({ restaurantId, name, onClose }: { restaurantId: number; name: string; onClose: () => void }) {
  const [entries, setEntries] = useState<HistoryEntry[]>([])
  const [nextPage, setNextPage] = useState<number | null>(1)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    halalApi
      .history(restaurantId, page)
      .then((response) => {
        if (cancelled) return
        setEntries((current) => [...current, ...response.entries.filter((entry) => !current.some((seen) => seen.id === entry.id))])
        setNextPage(response.nextPage)
      })
      .catch((caught) => !cancelled && setError(errorMessage(caught)))
      .finally(() => !cancelled && setLoading(false))
    return () => {
      cancelled = true
    }
  }, [restaurantId, page])

  return (
    <Sheet open onClose={onClose} title="Halal history">
      <p className="-mt-2 text-kicap-soft">Why MakanApa says what it says about {name}, and what it said before.</p>
      <ol className="flex flex-col divide-y-2 divide-hairline">
        {entries.map((entry) => (
          <li key={entry.id} className="flex flex-col gap-0.5 py-3">
            <p className="flex flex-wrap items-center gap-2">
              <span className="font-semibold">{CLAIM_LABELS[entry.status]}</span>
              <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${entry.state === 'active' ? 'bg-pandan/15 text-pandan' : 'bg-hairline text-kicap-soft'}`}>
                {entry.state === 'active' ? 'Current' : entry.state.charAt(0).toUpperCase() + entry.state.slice(1)}
              </span>
            </p>
            <p className="text-sm text-kicap-soft">
              {[METHOD_LABELS[entry.method] ?? (entry.evidenceSource === 'restaurant_owner' ? 'Owner evidence, reviewed' : 'Community evidence, reviewed'), entry.authority ? AUTHORITY_LABELS[entry.authority] : null]
                .filter(Boolean)
                .join(', ')}
            </p>
            {entry.summary && <p className="text-sm">{entry.summary}</p>}
            {entry.effectiveFrom && <p className="text-xs text-kicap-soft">{halalDate(entry.effectiveFrom)}</p>}
          </li>
        ))}
      </ol>
      {loading && <p role="status" className="text-kicap-soft">Loading…</p>}
      {error && (
        <p role="alert" className="text-sambal-deep">
          {error}
        </p>
      )}
      {!loading && nextPage !== null && !error && (
        <Button
          variant="secondary"
          onClick={() => {
            setLoading(true)
            setPage(nextPage)
          }}
        >
          Show older
        </Button>
      )}
      <Button variant="quiet" onClick={onClose}>
        Close
      </Button>
    </Sheet>
  )
}
