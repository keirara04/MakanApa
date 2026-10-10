import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react'
import { Link, useLocation as useRouterLocation, useNavigate, useSearchParams } from 'react-router'
import { ApiError } from '../../api/client'
import {
  changedFields,
  priceLevelFromSpend,
  spendFromPriceLevel,
  submissionsApi,
  type DetailsForm,
  type ExistingPlace,
  type GooglePlace,
  type LocationSource,
  type SubmissionDetails,
} from '../../api/submissions'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { resizeForUpload } from '../../lib/photos'
import { getPosition, useLocation, type Coordinates } from '../../lib/location'
import { Button } from '../../ui/Button'
import { Field } from '../../ui/Field'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { useContributionGate } from '../auth/ContributionGate'
import { MAPS_KEY } from '../nearby/NearbyMap'
import { LocationPreview, PinPicker } from './PinPicker'

/** What an entry point can hand over: a known place to edit (skips Find), and where Done goes. */
export interface AddPlaceState {
  existing?: ExistingPlace
  menu?: boolean
  from?: string
}

type Step = 'find' | 'details' | 'location' | 'review'
const STEP_TITLES: Record<Step, string> = { find: 'Find', details: 'Details', location: 'Location', review: 'Review' }
const CATEGORIES = ['Mamak', 'Nasi campur', 'Kopitiam', 'Cafe', 'Western', 'Chinese', 'Indian', 'Warung', 'Dessert', 'Street food']
const MAX_PHOTOS = 5
const EMPTY: DetailsForm = { name: '', address: '', foodCategory: '', spend: '', phone: '', instagramHandle: '', tiktokHandle: '', websiteUrl: '', menuItems: [] }

interface Selection {
  type: 'new_place' | 'edit_place'
  source: 'google' | 'manual'
  googlePlaceId: string | null
  restaurantId: number | null
}

interface Photo {
  key: string
  url: string
  uploading: boolean
}

/** For guests: what the page is for and a way to sign in (iOS AccountRequired). */
export function SignInNeeded({ feature }: { feature: string }) {
  return (
    <div className="flex flex-col items-center gap-3 pt-10 text-center">
      <Mascot mood="wave" size={96} />
      <h1 className="font-display text-2xl font-bold">Sign in to {feature}</h1>
      <p className="max-w-sm text-kicap-soft">Picks, Nearby and saves work without an account. Adding places needs one, so the community knows who’s contributing.</p>
      <Link to="/sign-in" className="press mt-2 inline-flex min-h-14 items-center rounded-full bg-sambal px-8 font-display text-xl font-bold text-white shadow-lift">
        Sign in or create an account
      </Link>
    </div>
  )
}

/** Add a place or suggest an edit: Find, Details, Location (new places only), Review. */
export function AddPlace() {
  const { user } = useSession()
  if (!user || user.isGuest) return <SignInNeeded feature="add or edit places" />
  return <Wizard />
}

function Wizard() {
  const navigate = useNavigate()
  const routerLocation = useRouterLocation()
  const [params, setParams] = useSearchParams()
  const [handover] = useState<AddPlaceState>(() => (routerLocation.state as AddPlaceState | null) ?? {})
  const { gate, run } = useContributionGate('add or edit places')
  const location = useLocation()

  const [query, setQuery] = useState(() => params.get('q') ?? '')
  const [selection, setSelection] = useState<Selection | null>(null)
  const [form, setForm] = useState<DetailsForm>(EMPTY)
  const [original, setOriginal] = useState<DetailsForm>(EMPTY)
  const [notes, setNotes] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof DetailsForm | 'notes', string>>>({})
  const [coords, setCoords] = useState<Coordinates | null>(null)
  const [locationSource, setLocationSource] = useState<LocationSource>('current_location')
  const [draftId, setDraftId] = useState<number | null>(null)
  const [draftError, setDraftError] = useState<string | null>(null)
  const [photos, setPhotos] = useState<Photo[]>([])
  const [photoError, setPhotoError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [discarding, setDiscarding] = useState(false)

  const isEdit = selection?.type === 'edit_place'
  const steps: Step[] = [...(handover.existing ? [] : ['find' as const]), 'details', ...(isEdit ? [] : ['location' as const]), 'review']
  const requested = (params.get('step') as Step | null) ?? steps[0]
  // A step needs what came before it; a reload or an old history entry starts over.
  const step: Step = !selection ? steps[0] : requested === 'review' && !coords ? 'details' : steps.includes(requested) ? requested : steps[0]
  const index = steps.indexOf(step)

  // Landed on a later step with nothing chosen (reload, or Back from Sent for review): the URL
  // follows what's shown, so Back doesn't walk through steps that can't be shown.
  useEffect(() => {
    if (!selection && !handover.existing && params.has('step')) {
      const next = new URLSearchParams(params)
      next.delete('step')
      setParams(next, { replace: true })
    }
  }, [selection, handover.existing, params, setParams])

  // Each step keeps the handover (router state), so a reload keeps the place and where Done goes.
  // A new step replaces the whole section (and the button that was focused): move focus to its
  // heading. Not on first arrival, where Find focuses its search box.
  const shownStep = useRef(step)
  useEffect(() => {
    if (shownStep.current === step) return
    shownStep.current = step
    document.getElementById(`${step}-title`)?.focus()
  }, [step])

  const go = (next: Step) => setParams({ step: next }, { state: routerLocation.state })
  const leave = () => navigate(handover.from ?? '/community')

  const pick = useCallback((next: Selection, values: DetailsForm, at: Coordinates | null, source: LocationSource, replace = false) => {
    resetDraft()
    setSelection(next)
    setForm(values)
    setOriginal(values)
    setNotes('')
    setFieldErrors({})
    setCoords(at)
    setLocationSource(source)
    setParams({ step: 'details' }, { replace, state: handover })
    // resetDraft only touches refs and setters.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [setParams, handover])

  const editExisting = useCallback(
    (place: ExistingPlace, replace = false) =>
      pick(
        { type: 'edit_place', source: 'manual', googlePlaceId: null, restaurantId: place.id },
        { ...EMPTY, name: place.name, foodCategory: place.foodCategory ?? '', spend: spendFromPriceLevel(place.priceLevel), address: place.address ?? '' },
        // An edit never moves the place, but the submission still carries its coordinates.
        { latitude: place.latitude, longitude: place.longitude },
        'current_location',
        replace,
      ),
    [pick],
  )

  // Handed a place: straight into its details (replacing, so Back leaves rather than loops).
  const handedOver = useRef(false)
  useEffect(() => {
    if (handover.existing && !handedOver.current) {
      handedOver.current = true
      editExisting(handover.existing, true)
    }
  }, [handover.existing, editExisting])

  // ---- Draft: created on reaching Review so photos have an id to attach to; kept in step after edits.

  const draft = useRef<{ id: number; details: string; location: string } | null>(null)
  const syncing = useRef<Promise<number | null> | null>(null)
  const submitted = useRef(false)
  // The wizard was left (a draft created after this is withdrawn at once).
  const left = useRef(false)
  // Re-syncs at Review after the page comes back from the page cache.
  const resync = useRef(() => {})

  function resetDraft() {
    if (draft.current) void submissionsApi.cancel(draft.current.id).catch(() => {})
    draft.current = null
    setDraftId(null)
    setDraftError(null)
    setPhotos([])
    setPhotoError(null)
    setSubmitError(null)
  }

  // Leaving without submitting withdraws the draft, so it doesn't sit there for the pruner.
  useEffect(() => {
    // A plain request while the page is alive; keepalive only on pagehide. In headless Chromium
    // that cross-origin keepalive never reaches the API, so a reload can still leave a draft
    // behind: PruneAbandonedSubmissionDrafts clears those.
    const abandon = (keepalive: boolean) => {
      if (draft.current && !submitted.current) {
        void submissionsApi.cancel(draft.current.id, keepalive).catch(() => {})
        draft.current = null
        // Back from the page cache, the screen must not show photos on the withdrawn draft.
        setDraftId(null)
        setPhotos([])
      }
    }
    const onPageHide = () => abandon(true)
    const onPageShow = (event: PageTransitionEvent) => event.persisted && resync.current()
    window.addEventListener('pagehide', onPageHide)
    window.addEventListener('pageshow', onPageShow)
    left.current = false
    return () => {
      left.current = true
      window.removeEventListener('pagehide', onPageHide)
      window.removeEventListener('pageshow', onPageShow)
      abandon(false)
    }
  }, [])

  const changed = changedFields(original, form)
  const trimmed = (value: string) => value.trim() || null
  const details: SubmissionDetails = {
    name: form.name.trim(),
    address: trimmed(form.address),
    foodCategory: trimmed(form.foodCategory),
    priceLevel: priceLevelFromSpend(form.spend),
    phone: trimmed(form.phone),
    instagramHandle: trimmed(form.instagramHandle),
    tiktokHandle: trimmed(form.tiktokHandle),
    websiteUrl: trimmed(form.websiteUrl),
    menuItems: form.menuItems.length ? form.menuItems : null,
    notes: trimmed(notes),
    changedFields: changed,
  }

  async function doSync(): Promise<number | null> {
    if (!selection || !coords) return null
    const detailsKey = JSON.stringify(details)
    const locationKey = `${coords.latitude},${coords.longitude},${locationSource}`
    let current = draft.current
    // The update endpoint can't move a place: a new pin means a new draft (and its photos go).
    if (current && current.location !== locationKey) {
      resetDraft()
      current = null
    }
    setDraftError(null)
    try {
      if (!current) {
        const { submission } = await submissionsApi.create({
          ...details,
          submissionType: selection.type,
          sourceType: selection.source,
          googlePlaceId: selection.googlePlaceId,
          restaurantId: selection.restaurantId,
          latitude: coords.latitude,
          longitude: coords.longitude,
          locationSource,
        })
        current = { id: submission.id, details: detailsKey, location: locationKey }
        if (left.current) {
          void submissionsApi.cancel(submission.id).catch(() => {})
          return null
        }
      } else if (current.details !== detailsKey) {
        try {
          await submissionsApi.update(current.id, details)
          current = { ...current, details: detailsKey }
        } catch (caught) {
          // Withdrawn in My places, or pruned: no longer editable. Start a new draft instead.
          if (!(caught instanceof ApiError && caught.status === 422 && !caught.fields)) throw caught
          draft.current = null
          setDraftId(null)
          setPhotos([])
          return doSync()
        }
      }
      draft.current = current
      setDraftId(current.id)
      return current.id
    } catch (caught) {
      const fields = caught instanceof ApiError && caught.status === 422 ? caught.fields : undefined
      const mapped = fields ? fieldsToForm(fields) : {}
      if (Object.keys(mapped).length) {
        setFieldErrors(mapped)
        go('details')
      } else setDraftError(caught instanceof ApiError && caught.status !== 422 ? errorMessage(caught) : 'Couldn’t get this ready. Try again in a bit.')
      return null
    }
  }

  // One sync at a time: StrictMode's double effect and a quick Submit share the same request.
  function syncDraft(): Promise<number | null> {
    syncing.current ??= doSync().finally(() => (syncing.current = null))
    return syncing.current
  }

  useEffect(() => {
    if (step === 'review') void syncDraft()
    // Runs on arriving at Review; Submit syncs again for anything changed since.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step])
  useEffect(() => {
    resync.current = () => step === 'review' && void syncDraft()
  })

  // Photos on screen, including ones picked a moment ago and still uploading.
  const photoCount = useRef(0)
  useEffect(() => {
    photoCount.current = photos.length
  }, [photos.length])

  async function addPhotos(files: FileList | null) {
    const id = draft.current?.id
    if (!id || !files?.length) return
    setPhotoError(null)
    for (const file of Array.from(files)) {
      if (photoCount.current >= MAX_PHOTOS) break
      photoCount.current++
      const key = crypto.randomUUID()
      const url = URL.createObjectURL(file)
      setPhotos((current) => [...current, { key, url, uploading: true }])
      try {
        await submissionsApi.uploadPhoto(id, await resizeForUpload(file))
        setPhotos((current) => current.map((photo) => (photo.key === key ? { ...photo, uploading: false } : photo)))
      } catch (caught) {
        URL.revokeObjectURL(url)
        photoCount.current--
        setPhotos((current) => current.filter((photo) => photo.key !== key))
        setPhotoError(
          caught instanceof ApiError
            ? caught.status === 429
              ? 'Too many photos at once. Wait a minute, then add the rest.'
              : errorMessage(caught)
            : 'Couldn’t read that photo. Try a different one.',
        )
      }
    }
  }

  async function submit() {
    setSubmitting(true)
    setSubmitError(null)
    try {
      const id = await syncDraft()
      if (!id) return
      await submissionsApi.submit(id).catch((caught: unknown) => {
        if (!(caught instanceof ApiError && caught.status === 422 && /already been submitted/i.test(caught.message))) throw caught
      })
      submitted.current = true
      navigate('/community/add/done', { replace: true, state: { name: details.name, edit: isEdit, from: handover.from } })
    } catch (caught) {
      setSubmitError(errorMessage(caught))
    } finally {
      setSubmitting(false)
    }
  }

  const uploading = photos.some((photo) => photo.uploading)
  const editHasSomething = !isEdit || changed.length > 0 || details.notes !== null || photos.length > 0
  const dirty = draftId !== null || photos.length > 0 || (isEdit ? changed.length > 0 || notes.trim() !== '' : selection !== null && (selection.source === 'google' || form.name.trim() !== ''))

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 pt-2 pb-48 md:pb-32">
      <header className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          {index > 0 ? (
            <button type="button" onClick={() => navigate(-1)} className="-ml-1 min-h-11 font-display font-semibold text-sambal-deep">
              ‹ Back
            </button>
          ) : (
            <span />
          )}
          <button type="button" onClick={() => (dirty ? setDiscarding(true) : leave())} className="min-h-11 font-display font-semibold text-sambal-deep">
            Cancel
          </button>
        </div>
        <div role="group" aria-label={`Step ${index + 1} of ${steps.length}, ${STEP_TITLES[step]}`}>
          <p className="text-sm text-kicap-soft" aria-hidden="true">
            {isEdit ? 'Suggest an edit' : 'Add a place'} · Step {index + 1} of {steps.length}
          </p>
          <div className="mt-2 flex gap-1.5" aria-hidden="true">
            {steps.map((name, at) => (
              <span key={name} className={`h-1.5 flex-1 rounded-full ${at <= index ? 'bg-sambal' : 'bg-hairline'}`} />
            ))}
          </div>
        </div>
      </header>

      {step === 'find' && (
        <FindStep
          query={query}
          onQuery={setQuery}
          coords={location.state.status === 'ready' ? location.state.coords : null}
          locationOff={location.state.status !== 'ready'}
          onTurnOnLocation={() => void location.request()}
          onExisting={(place) => editExisting(place)}
          onGoogle={(place: GooglePlace) =>
            pick(
              { type: 'new_place', source: 'google', googlePlaceId: place.googlePlaceId, restaurantId: null },
              { ...EMPTY, name: place.name, foodCategory: place.foodCategory ?? '', spend: spendFromPriceLevel(place.priceLevel), address: place.address ?? '' },
              { latitude: place.latitude, longitude: place.longitude },
              'google',
            )
          }
          onManual={(typed) => pick({ type: 'new_place', source: 'manual', googlePlaceId: null, restaurantId: null }, { ...EMPTY, name: typed }, null, 'current_location')}
        />
      )}

      {step === 'details' && selection && (
        <DetailsStep
          isEdit={isEdit}
          googleSpend={selection.source === 'google' && form.spend !== '' && form.spend === original.spend}
          form={form}
          notes={notes}
          errors={fieldErrors}
          openMenu={handover.menu ?? false}
          onChange={(next) => {
            setForm(next)
            setFieldErrors({})
          }}
          onNotes={setNotes}
          onContinue={() => {
            const errors = validate(form)
            setFieldErrors(errors)
            if (Object.keys(errors).length) return
            run(() => go(isEdit ? 'review' : 'location'))
          }}
        />
      )}

      {step === 'location' && selection && (
        <LocationStep
          name={form.name.trim()}
          fromGoogle={selection.source === 'google'}
          coords={coords}
          source={locationSource}
          clearsPhotos={photos.length > 0}
          onPin={(at, source) => {
            setCoords(at)
            setLocationSource(source)
          }}
          onContinue={() => go('review')}
        />
      )}

      {step === 'review' && selection && coords && (
        <section className="flex flex-col gap-5" aria-labelledby="review-title">
          <StepHeading id="review-title" title="Looks good?" subtitle="Check everything before it goes to review." />
          <ReviewCard title="Details" onEdit={() => go('details')}>
            <ReviewRow label="Name" value={details.name} />
            <ReviewRow label="Category" value={details.foodCategory} />
            <ReviewRow label="Spend" value={form.spend.trim() ? `≈ RM${form.spend.trim()} / person` : null} />
            <ReviewRow label="Address" value={details.address} />
            <ReviewRow label="Contact" value={[details.phone, details.instagramHandle, details.tiktokHandle, details.websiteUrl].filter(Boolean).join(', ') || null} />
            <ReviewRow label="Menu" value={form.menuItems.length ? `${form.menuItems.length} item${form.menuItems.length === 1 ? '' : 's'}` : null} />
            {isEdit && <ReviewRow label="Changes" value={changed.length ? changed.map((field) => FIELD_LABELS[field]).join(', ') : 'Nothing changed yet'} />}
          </ReviewCard>
          <ReviewCard title="Location" onEdit={isEdit ? undefined : () => go('location')}>
            <LocationPreview coords={coords} name={details.name} height={140} />
            <p className="text-sm text-kicap-soft">{isEdit ? 'Unchanged' : SOURCE_LABELS[locationSource]}</p>
          </ReviewCard>
          <ReviewCard title="Photos" subtitle={`Optional, up to ${MAX_PHOTOS}`}>
            {draftId ? (
              <div className="flex flex-wrap gap-2.5">
                {photos.map((photo) => (
                  <div key={photo.key} className="relative h-20 w-20 overflow-hidden rounded-row bg-hairline">
                    <img src={photo.url} alt="" className="h-full w-full object-cover" />
                    {photo.uploading && <span className="absolute inset-0 grid place-items-center bg-kicap/40 text-xs font-semibold text-white">Uploading</span>}
                  </div>
                ))}
                {photos.length < MAX_PHOTOS && (
                  <label className="press grid h-20 w-20 cursor-pointer place-items-center rounded-row border-2 border-dashed border-sambal/50 bg-sambal/5 text-center text-sm font-semibold text-sambal-deep focus-within:outline-3 focus-within:outline-sambal">
                    Add
                    <input type="file" accept="image/*" multiple className="sr-only" aria-label="Add photos" onChange={(event) => void addPhotos(event.target.files).finally(() => (event.target.value = ''))} />
                  </label>
                )}
              </div>
            ) : draftError ? (
              <div className="flex flex-col items-start gap-2">
                <p role="alert" className="text-sambal-deep">
                  {draftError}
                </p>
                <Button variant="quiet" onClick={() => void syncDraft()}>
                  Try again
                </Button>
              </div>
            ) : (
              <p role="status" className="text-sm text-kicap-soft">
                Getting things ready…
              </p>
            )}
            {photoError && (
              <p role="alert" className="text-sm text-sambal-deep">
                {photoError}
              </p>
            )}
          </ReviewCard>
          {submitError && (
            <p role="alert" className="text-sambal-deep">
              {submitError}
            </p>
          )}
          <ActionBar hint={!editHasSomething ? 'Change something, or add a note or photo, to send this edit.' : uploading ? 'Waiting for photos to finish uploading…' : undefined}>
            <Button onClick={() => void submit()} busy={submitting} disabled={!draftId || uploading || !editHasSomething}>
              Submit for review
            </Button>
          </ActionBar>
        </section>
      )}

      <Sheet open={discarding} onClose={() => setDiscarding(false)} title={isEdit ? 'Discard this edit?' : 'Discard this place?'}>
        <p className="text-kicap-soft">What you’ve filled in so far won’t be saved.</p>
        <Button onClick={leave}>Discard</Button>
        <Button variant="secondary" onClick={() => setDiscarding(false)}>
          Keep editing
        </Button>
      </Sheet>
      {gate}
    </div>
  )
}

const FIELD_LABELS: Record<string, string> = {
  name: 'Name',
  address: 'Address',
  food_category: 'Category',
  price_level: 'Spend',
  phone: 'Phone',
  instagram_handle: 'Instagram',
  tiktok_handle: 'TikTok',
  website_url: 'Website',
  menu_items: 'Menu',
}

const SOURCE_LABELS: Record<LocationSource, string> = { google: 'Location from Google', map_pin: 'Pinned on the map', current_location: 'Your current location' }

/** `URL.canParse` without needing Safari 17. */
function isUrl(value: string): boolean {
  try {
    new URL(value)
    return true
  } catch {
    return false
  }
}

/** Checked before Continue, so the server's 422 is a backstop, not the first word. */
function validate(form: DetailsForm): Partial<Record<keyof DetailsForm, string>> {
  const errors: Partial<Record<keyof DetailsForm, string>> = {}
  if (!form.name.trim()) errors.name = 'Add a name so people can find it.'
  if (form.spend.trim() && !(Number(form.spend) > 0)) errors.spend = 'Enter an estimated spend per person, like 15.'
  if (form.websiteUrl.trim() && !isUrl(form.websiteUrl.trim())) errors.websiteUrl = 'Enter the full address, starting with https://'
  return errors
}

/** The server's validation errors, on the form field each belongs to. */
function fieldsToForm(fields: Record<string, string[]>): Partial<Record<keyof DetailsForm | 'notes', string>> {
  const map: Record<string, keyof DetailsForm | 'notes'> = {
    name: 'name',
    address: 'address',
    foodCategory: 'foodCategory',
    priceLevel: 'spend',
    phone: 'phone',
    instagramHandle: 'instagramHandle',
    tiktokHandle: 'tiktokHandle',
    websiteUrl: 'websiteUrl',
    notes: 'notes',
  }
  const out: Partial<Record<keyof DetailsForm | 'notes', string>> = {}
  for (const [key, messages] of Object.entries(fields)) {
    const field = map[key] ?? (key.startsWith('menuItems') ? 'menuItems' : undefined)
    if (field && messages[0]) out[field] = messages[0]
  }
  return out
}

function StepHeading({ id, title, subtitle }: { id: string; title: string; subtitle: string }) {
  return (
    <div>
      {/* Focused on each step change (see Wizard), so keyboards and screen readers follow. */}
      <h1 id={id} tabIndex={-1} className="font-display text-3xl font-bold tracking-tight outline-none">
        {title}
      </h1>
      <p className="mt-1 text-kicap-soft">{subtitle}</p>
    </div>
  )
}

/** The one pinned action area, above the tab bar on phones. */
function ActionBar({ hint, children }: { hint?: string; children: ReactNode }) {
  return (
    <div className="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-10 border-t-2 border-hairline bg-cream/95 px-5 py-3 backdrop-blur md:bottom-0 md:left-60 lg:left-64">
      <div className="mx-auto flex max-w-2xl flex-col gap-2">
        {hint && <p className="text-center text-sm text-kicap-soft">{hint}</p>}
        {children}
      </div>
    </div>
  )
}

// ---- Find

function FindStep({
  query,
  onQuery,
  coords,
  locationOff,
  onTurnOnLocation,
  onExisting,
  onGoogle,
  onManual,
}: {
  query: string
  onQuery: (query: string) => void
  coords: Coordinates | null
  locationOff: boolean
  onTurnOnLocation: () => void
  onExisting: (place: ExistingPlace) => void
  onGoogle: (place: GooglePlace) => void
  onManual: (typed: string) => void
}) {
  const [found, setFound] = useState<{ existing: ExistingPlace[]; google: GooglePlace[] } | null>(null)
  const [searching, setSearching] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [attempt, setAttempt] = useState(0)
  const term = query.trim()
  const lat = coords?.latitude
  const lng = coords?.longitude
  const results = term.length >= 2 ? found : null

  // Search as you type, after a short pause; each keystroke cancels the last request.
  useEffect(() => {
    if (term.length < 2) return
    const controller = new AbortController()
    const timer = setTimeout(() => {
      setSearching(true)
      setError(null)
      submissionsApi
        .search(term, lat !== undefined && lng !== undefined ? { latitude: lat, longitude: lng } : null, controller.signal)
        .then(setFound)
        .catch((caught) => !controller.signal.aborted && setError(caught instanceof ApiError && caught.status === 429 ? errorMessage(caught) : 'Couldn’t search right now. Check your connection and try again.'))
        .finally(() => !controller.signal.aborted && setSearching(false))
    }, 450)
    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [term, lat, lng, attempt])

  return (
    <section className="flex flex-col gap-5" aria-labelledby="find-title">
      <StepHeading id="find-title" title="Find the place" subtitle="Found somewhere good? Let’s check if we know it first." />
      <label className="flex min-h-13 items-center gap-3 rounded-row border-2 border-hairline bg-surface px-4 focus-within:border-kicap">
        <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <span className="sr-only">Search for a place</span>
        <input
          type="search"
          value={query}
          onChange={(event) => onQuery(event.target.value)}
          placeholder="Search for a place"
          autoComplete="off"
          autoFocus
          className="min-h-12 flex-1 bg-transparent text-base outline-none placeholder:text-kicap-soft"
        />
        {searching && (
          <span role="status" className="text-sm text-kicap-soft">
            Searching…
          </span>
        )}
      </label>

      {/* Without a location the server can't ask Google, so "no matches" would mislead. */}
      {locationOff && (
        <button type="button" onClick={onTurnOnLocation} className="-mt-2 min-h-11 self-start font-semibold text-sambal-deep underline decoration-2 underline-offset-4">
          Turn on location to also search Google
        </button>
      )}

      {error && (
        <div className="flex flex-col items-start gap-1">
          <p role="alert" className="text-sambal-deep">
            {error}
          </p>
          <Button variant="quiet" onClick={() => setAttempt((count) => count + 1)}>
            Try again
          </Button>
        </div>
      )}

      {results && results.existing.length > 0 && (
        <ResultGroup title="Already on MakanApa">
          {results.existing.map((place) => (
            <ResultRow key={place.id} name={place.name} category={place.foodCategory} distanceKm={place.distanceKm} address={place.address} badge="Suggest an edit" tone="kunyit" onClick={() => onExisting(place)} />
          ))}
        </ResultGroup>
      )}
      {results && results.google.length > 0 && (
        <ResultGroup title="Found on Google">
          {results.google.map((place) => (
            <ResultRow key={place.googlePlaceId} name={place.name} category={place.foodCategory} distanceKm={place.distanceKm} address={place.address} badge="Add" tone="sambal" onClick={() => onGoogle(place)} />
          ))}
        </ResultGroup>
      )}
      {results && !searching && results.existing.length === 0 && results.google.length === 0 && <p className="text-kicap-soft">No matches for “{term}”.</p>}

      <button
        type="button"
        onClick={() => onManual(term)}
        className="sketch press flex flex-col items-start gap-1 px-5 py-4 text-left"
        style={{ ['--sketch-radius' as string]: '20px', ['--sketch-fill' as string]: 'color-mix(in srgb, var(--color-kunyit) 22%, var(--color-surface))' }}
      >
        <span className="font-display text-lg font-bold">Not on Google? No problem.</span>
        <span className="text-kicap-soft">Small places deserve to be discovered too.</span>
        <span className="mt-1 font-semibold text-sambal-deep underline decoration-2 underline-offset-4">Add it yourself</span>
      </button>
    </section>
  )
}

function ResultGroup({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-2">
      <h2 className="text-sm font-semibold text-kicap-soft">{title}</h2>
      {children}
    </div>
  )
}

function ResultRow({
  name,
  category,
  distanceKm,
  address,
  badge,
  tone,
  onClick,
}: {
  name: string
  category: string | null
  distanceKm: number | null
  address: string | null
  badge: string
  tone: 'kunyit' | 'sambal'
  onClick: () => void
}) {
  const meta = [category, distanceKm !== null ? (distanceKm < 1 ? `${Math.round(distanceKm * 1000)} m` : `${distanceKm.toFixed(1)} km`) : null].filter(Boolean).join(', ')
  return (
    <button type="button" onClick={onClick} className="press flex items-center gap-3 rounded-row border border-hairline bg-surface px-4 py-3 text-left">
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{name}</span>
        {meta && <span className="block text-sm text-kicap-soft">{meta}</span>}
        {address && <span className="block truncate text-sm text-kicap-soft">{address}</span>}
      </span>
      <span className={`shrink-0 rounded-full px-3 py-1 text-sm font-semibold ${tone === 'kunyit' ? 'bg-kunyit/35 text-kicap' : 'bg-sambal/12 text-sambal-deep'}`}>{badge}</span>
    </button>
  )
}

// ---- Details

function DetailsStep({
  isEdit,
  googleSpend,
  form,
  notes,
  errors,
  openMenu,
  onChange,
  onNotes,
  onContinue,
}: {
  isEdit: boolean
  googleSpend: boolean
  form: DetailsForm
  notes: string
  errors: Partial<Record<keyof DetailsForm | 'notes', string>>
  openMenu: boolean
  onChange: (form: DetailsForm) => void
  onNotes: (notes: string) => void
  onContinue: () => void
}) {
  const set = (key: keyof DetailsForm) => (event: React.ChangeEvent<HTMLInputElement>) => onChange({ ...form, [key]: event.target.value })
  const extras = [form.address, form.phone, form.instagramHandle, form.tiktokHandle, form.websiteUrl].filter((value) => value.trim()).length
  const extrasHaveError = Boolean(errors.address || errors.phone || errors.instagramHandle || errors.tiktokHandle || errors.websiteUrl)

  return (
    <section className="flex flex-col gap-6" aria-labelledby="details-title">
      <StepHeading
        id="details-title"
        title={isEdit ? 'What should change?' : 'Tell us about this place'}
        subtitle={isEdit ? 'Fix anything that’s wrong or missing. Only what you change gets reviewed.' : 'Just the basics. Everything else is optional.'}
      />
      <Field label="Place name" value={form.name} onChange={set('name')} placeholder="e.g. Restoran Nasi Kandar Pelita" autoCapitalize="words" required error={errors.name} />
      <div className="flex flex-col gap-2">
        <Field label="Category" value={form.foodCategory} onChange={set('foodCategory')} placeholder="e.g. Mamak" autoCapitalize="words" error={errors.foodCategory} />
        <div className="flex flex-wrap gap-2" role="group" aria-label="Category suggestions">
          {CATEGORIES.map((category) => {
            const selected = form.foodCategory.trim().toLowerCase() === category.toLowerCase()
            return (
              <button
                key={category}
                type="button"
                aria-pressed={selected}
                onClick={() => onChange({ ...form, foodCategory: selected ? '' : category })}
                className={`press min-h-10 rounded-full border-2 px-3.5 text-sm font-semibold ${selected ? 'border-sambal-deep bg-sambal-deep text-white' : 'border-hairline bg-surface'}`}
              >
                {category}
              </button>
            )
          })}
        </div>
      </div>
      <Field
        label="Spend per person (RM)"
        value={form.spend}
        onChange={set('spend')}
        inputMode="decimal"
        placeholder="15"
        error={errors.spend}
        hint={googleSpend ? 'Estimated from Google. Change it if you know better.' : 'Usually around how much per person?'}
      />

      <Disclosure title="Add more details" subtitle={extras ? `${extras} added` : 'Address, phone and socials'} open={extrasHaveError}>
        <Field label="Address" value={form.address} onChange={set('address')} placeholder="Street, area" error={errors.address} />
        <Field label="Phone" value={form.phone} onChange={set('phone')} type="tel" placeholder="012-345 6789" error={errors.phone} />
        <Field label="Instagram" value={form.instagramHandle} onChange={set('instagramHandle')} placeholder="@handle" autoCapitalize="none" autoCorrect="off" error={errors.instagramHandle} />
        <Field label="TikTok" value={form.tiktokHandle} onChange={set('tiktokHandle')} placeholder="@handle" autoCapitalize="none" autoCorrect="off" error={errors.tiktokHandle} />
        <Field label="Website" value={form.websiteUrl} onChange={set('websiteUrl')} type="url" placeholder="https://" error={errors.websiteUrl} />
        <p className="text-sm text-kicap-soft">You can leave these blank. The community can help complete them later.</p>
      </Disclosure>

      <Disclosure title="Add menu" subtitle={form.menuItems.length ? `${form.menuItems.length} added` : 'Help people know what’s good here.'} open={openMenu || Boolean(errors.menuItems)}>
        {form.menuItems.length > 0 && (
          <ul className="divide-y divide-hairline">
            {form.menuItems.map((item, at) => (
              <li key={`${item.name}-${at}`} className="flex min-h-12 items-center gap-3">
                <span className="flex-1 font-semibold">{item.name}</span>
                {item.price !== null && <span className="tabular-nums text-kicap-soft">RM{item.price.toFixed(2)}</span>}
                <button
                  type="button"
                  aria-label={`Remove ${item.name}`}
                  onClick={() => onChange({ ...form, menuItems: form.menuItems.filter((_, index) => index !== at) })}
                  className="grid h-11 w-11 place-items-center rounded-full text-kicap-soft"
                >
                  <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" />
                  </svg>
                </button>
              </li>
            ))}
          </ul>
        )}
        <AddMenuItem onAdd={(item) => onChange({ ...form, menuItems: [...form.menuItems, item] })} />
        {errors.menuItems && <p className="text-sm text-sambal-deep">{errors.menuItems}</p>}
      </Disclosure>

      <label className="flex flex-col gap-1.5">
        <span className="font-display text-base font-semibold">Notes for the reviewer</span>
        <textarea
          value={notes}
          onChange={(event) => onNotes(event.target.value)}
          rows={3}
          maxLength={500}
          placeholder="Anything else worth knowing? (optional)"
          className="rounded-row border-2 border-hairline bg-surface px-4 py-3 placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
        />
        {errors.notes && <span className="text-sm text-sambal-deep">{errors.notes}</span>}
      </label>

      <ActionBar hint={form.name.trim() ? undefined : 'Add a name to continue'}>
        <Button onClick={onContinue}>Continue</Button>
      </ActionBar>
    </section>
  )
}

function Disclosure({ title, subtitle, open, children }: { title: string; subtitle: string; open: boolean; children: ReactNode }) {
  // The open prop only ever opens it: if React removed the attribute when the error clears, the
  // panel would snap shut on the first keystroke of the fix.
  const ref = useRef<HTMLDetailsElement>(null)
  const [initial] = useState(open)
  useEffect(() => {
    if (open && ref.current) ref.current.open = true
  }, [open])
  return (
    <details ref={ref} open={initial || undefined} className="group rounded-card border-2 border-hairline bg-surface/70 px-5 py-1">
      <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
        <span className="flex-1">
          <span className="block font-semibold">{title}</span>
          <span className="text-sm text-kicap-soft">{subtitle}</span>
        </span>
        <svg viewBox="0 0 24 24" className="h-5 w-5 text-kicap-soft transition-transform group-open:rotate-180" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true">
          <path d="m6 9 6 6 6-6" />
        </svg>
      </summary>
      <div className="flex flex-col gap-4 pt-2 pb-5">{children}</div>
    </details>
  )
}

function AddMenuItem({ onAdd }: { onAdd: (item: { name: string; price: number | null }) => void }) {
  const [name, setName] = useState('')
  const [price, setPrice] = useState('')
  const nameInput = useRef<HTMLInputElement>(null)
  const value = Number(price)
  const ok = name.trim() !== '' && (!price.trim() || (Number.isFinite(value) && value >= 0 && value < 10000))

  function add() {
    if (!ok) return
    onAdd({ name: name.trim(), price: price.trim() ? Math.round(value * 100) / 100 : null })
    setName('')
    setPrice('')
    nameInput.current?.focus()
  }

  return (
    <div className="flex items-end gap-2">
      <label className="flex min-w-0 flex-1 flex-col gap-1.5">
        <span className="text-sm font-semibold">Dish</span>
        <input
          ref={nameInput}
          value={name}
          onChange={(event) => setName(event.target.value)}
          onKeyDown={(event) => event.key === 'Enter' && add()}
          maxLength={120}
          placeholder="Nasi lemak ayam"
          className="min-h-12 rounded-row border-2 border-hairline bg-surface px-3 placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
        />
      </label>
      <label className="flex w-24 flex-col gap-1.5">
        <span className="text-sm font-semibold">Price (RM)</span>
        <input
          value={price}
          onChange={(event) => setPrice(event.target.value)}
          onKeyDown={(event) => event.key === 'Enter' && add()}
          inputMode="decimal"
          placeholder="0.00"
          className="min-h-12 rounded-row border-2 border-hairline bg-surface px-3 placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
        />
      </label>
      <button type="button" onClick={add} disabled={!ok} aria-label="Add dish" className="press grid h-12 w-12 shrink-0 place-items-center rounded-full bg-sambal text-white disabled:bg-hairline disabled:text-kicap-soft">
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
      </button>
    </div>
  )
}

// ---- Location (new places only)

function LocationStep({
  name,
  fromGoogle,
  coords,
  source,
  clearsPhotos,
  onPin,
  onContinue,
}: {
  name: string
  fromGoogle: boolean
  coords: Coordinates | null
  source: LocationSource
  clearsPhotos: boolean
  onPin: (coords: Coordinates, source: LocationSource) => void
  onContinue: () => void
}) {
  const [locating, setLocating] = useState(false)
  const [problem, setProblem] = useState<string | null>(null)
  const [picking, setPicking] = useState(false)

  async function pinCurrent() {
    setProblem(null)
    setLocating(true)
    try {
      onPin(await getPosition(), 'current_location')
    } catch (error) {
      const denied = error instanceof GeolocationPositionError && error.code === error.PERMISSION_DENIED
      const fallback = MAPS_KEY ? ', or choose the spot on the map' : ''
      setProblem(denied ? `Location is off for MakanApa. Allow it in your browser’s site settings${fallback}.` : `Couldn’t get your location. Try again${fallback}.`)
    } finally {
      setLocating(false)
    }
  }

  return (
    <section className="flex flex-col gap-5" aria-labelledby="location-title">
      <StepHeading
        id="location-title"
        title={coords ? 'Is this the right spot?' : 'Where is it?'}
        subtitle={!coords ? 'Pin it so people can actually find it.' : fromGoogle && source === 'google' ? 'This is where Google says the place is.' : 'This place should be somewhere around this pin.'}
      />
      {coords ? (
        <>
          <LocationPreview coords={coords} name={name} />
          <p className="text-sm text-kicap-soft">{SOURCE_LABELS[source]}</p>
          {clearsPhotos && <p className="text-sm text-kicap-soft">Moving the pin clears the photos you added.</p>}
        </>
      ) : (
        <div className="flex flex-col gap-3">
          <LocationOption title="Use my current location" detail="Best if you’re at the place right now" onClick={() => void pinCurrent()} />
          {MAPS_KEY && <LocationOption title="Choose on map" detail="Drag the map to drop a pin" onClick={() => setPicking(true)} />}
        </div>
      )}
      {locating && (
        <p role="status" className="text-kicap-soft">
          Getting your location…
        </p>
      )}
      {problem && (
        <p role="alert" className="text-sambal-deep">
          {problem}
        </p>
      )}

      {coords && (
        <ActionBar>
          <Button onClick={onContinue}>Looks right</Button>
          {MAPS_KEY ? (
            <Button variant="quiet" onClick={() => setPicking(true)}>
              {source === 'google' ? 'Adjust pin' : 'Change location'}
            </Button>
          ) : (
            source !== 'current_location' && (
              <Button variant="quiet" onClick={() => void pinCurrent()}>
                Use my current location instead
              </Button>
            )
          )}
        </ActionBar>
      )}

      {picking && (
        <PinPicker
          start={coords}
          onClose={() => setPicking(false)}
          onConfirm={(at) => {
            onPin(at, 'map_pin')
            setPicking(false)
          }}
        />
      )}
    </section>
  )
}

function LocationOption({ title, detail, onClick }: { title: string; detail: string; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="press flex min-h-16 items-center gap-4 rounded-row border-2 border-hairline bg-surface px-4 py-3 text-left">
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sambal/10 text-sambal-deep" aria-hidden="true">
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" />
          <circle cx="12" cy="9.5" r="2.5" />
        </svg>
      </span>
      <span className="flex-1">
        <span className="block font-semibold">{title}</span>
        <span className="text-sm text-kicap-soft">{detail}</span>
      </span>
    </button>
  )
}

// ---- Review

function ReviewCard({ title, subtitle, onEdit, children }: { title: string; subtitle?: string; onEdit?: () => void; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-3 rounded-card border-2 border-hairline bg-surface px-5 py-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-lg font-bold">{title}</h2>
          {subtitle && <p className="text-sm text-kicap-soft">{subtitle}</p>}
        </div>
        {onEdit && (
          <button type="button" onClick={onEdit} aria-label={`Edit ${title.toLowerCase()}`} className="min-h-11 px-2 font-semibold text-sambal-deep">
            Edit
          </button>
        )}
      </div>
      {children}
    </div>
  )
}

function ReviewRow({ label, value }: { label: string; value: string | null }) {
  if (!value) return null
  return (
    <p className="flex gap-3">
      <span className="w-20 shrink-0 text-sm text-kicap-soft">{label}</span>
      <span className="min-w-0 flex-1 break-words">{value}</span>
    </p>
  )
}

// ---- Done

export function AddPlaceDone() {
  const navigate = useNavigate()
  const { state } = useRouterLocation() as { state: { name?: string; edit?: boolean; from?: string } | null }
  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-3 pt-10 text-center">
      <Mascot mood="excited" size={120} />
      <h1 className="font-display text-3xl font-bold tracking-tight">{state?.edit ? 'Edit sent for review!' : 'Sent for review!'}</h1>
      {state?.name && <p className="font-display text-lg font-semibold">{state.name}</p>}
      <p className="text-kicap-soft">We’ll check the place before it shows up around MakanApa.</p>
      <p className="text-sm text-kicap-soft">You’ll get a notification once it’s reviewed. Track it anytime in My places.</p>
      <div className="mt-4 flex w-full flex-col gap-2">
        <Button onClick={() => navigate(state?.from ?? '/community')}>Done</Button>
        <Button variant="secondary" onClick={() => navigate('/community/mine')}>
          See My places
        </Button>
        {!state?.edit && (
          <Button variant="quiet" onClick={() => navigate('/community/add', { replace: true })}>
            Add another place
          </Button>
        )}
      </div>
    </div>
  )
}
