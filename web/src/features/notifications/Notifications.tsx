import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSession } from '../../auth/session'
import { enablePush, preferencesApi, pushState, type NotificationPreferences, type PushState } from '../../lib/push'
import { setStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { SubPage } from '../settings/SubPage'

/**
 * Shown once, after the first accepted pick (the iOS moment): what pushes are for, two optional
 * extras, then the browser's own permission prompt. Never on page load.
 */
export function NotificationPriming({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [mealtime, setMealtime] = useState(false)
  const [news, setNews] = useState(false)
  const [busy, setBusy] = useState(false)
  const [blocked, setBlocked] = useState(false)

  function finish() {
    setStored('primingSeen', true)
    onClose()
  }

  async function turnOn() {
    setBusy(true)
    await preferencesApi.update({ mealtime_nudges: mealtime, release_announcements: news }).catch(() => {})
    // A browser without a push service fails here; the prompt still closes so the meal isn't held up.
    const state = await enablePush().catch(() => 'off' as const)
    setBusy(false)
    if (state === 'blocked') setBlocked(true)
    else finish()
  }

  return (
    <Sheet open={open} onClose={finish} title="Stay in the loop">
      <p className="text-kicap-soft">You’ll get a notification for:</p>
      <ul className="list-inside list-disc">
        <li>Updates on places you add</li>
        <li>Important account notices</li>
      </ul>
      <fieldset className="flex flex-col gap-2">
        <legend className="text-sm font-semibold text-kicap-soft">Optional</legend>
        <Toggle label="Mealtime picks" detail="A pick near you at lunch or dinner, max 1 a day" on={mealtime} onChange={setMealtime} />
        <Toggle label="MakanApa news and new releases" on={news} onChange={setNews} />
      </fieldset>
      {blocked && (
        <p role="alert" className="text-sambal-deep">
          Notifications are blocked for this site. Allow them from the icon next to the address bar, then try again.
        </p>
      )}
      <Button onClick={() => void turnOn()} busy={busy}>
        Turn on notifications
      </Button>
      <Button variant="quiet" onClick={finish}>
        Not now
      </Button>
    </Sheet>
  )
}

const ROWS: { section: string; key: keyof NotificationPreferences; title: string; detail: string }[] = [
  { section: 'Meals', key: 'mealtime_nudges', title: 'Mealtime picks', detail: 'A pick near you at lunch or dinner, max 1 a day' },
  { section: 'Your places', key: 'community_submissions', title: 'Submission updates', detail: 'When a place you added is reviewed' },
  { section: 'Community', key: 'community_replies', title: 'Replies', detail: 'When someone replies to your post' },
  { section: 'Community', key: 'community_reactions', title: 'Reactions', detail: 'When someone reacts to your post' },
  { section: 'From MakanApa', key: 'account_admin', title: 'Account notices', detail: 'Important changes to your account' },
  { section: 'From MakanApa', key: 'release_announcements', title: 'News and new releases', detail: 'New features, now and then' },
]

/** Settings > Notifications: this browser's permission, then what you want to hear about. */
export function NotificationSettings() {
  const { user } = useSession()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['notification-preferences', user?.id], queryFn: preferencesApi.get })
  const [state, setState] = useState<PushState | null>(null)
  const [busy, setBusy] = useState(false)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    void pushState().then(setState)
  }, [])

  function change(key: keyof NotificationPreferences, value: boolean) {
    // Optimistic, like iOS: the switch moves at once and the save happens in the background.
    queryClient.setQueryData(['notification-preferences', user?.id], { preferences: { ...data!.preferences, [key]: value } })
    void preferencesApi.update({ [key]: value }).catch(() => queryClient.invalidateQueries({ queryKey: ['notification-preferences'] }))
  }

  let lastSection = ''
  return (
    <SubPage title="Notifications">
      <section className="rounded-row bg-surface px-4 py-4" aria-live="polite">
        {state === 'on' && <p className="font-semibold text-pandan">Notifications are on for this browser.</p>}
        {state === 'blocked' && <p>Notifications are blocked for this site. Allow them from the icon next to the address bar.</p>}
        {state === 'unsupported' && <p>This browser can’t show notifications. On Android, use Chrome; on iPhone, use the MakanApa app.</p>}
        {state === 'off' && (
          <>
            <p>Notifications are off for this browser.</p>
            <Button
              className="mt-3 md:max-w-xs"
              busy={busy}
              onClick={() => {
                setBusy(true)
                setFailed(false)
                void enablePush()
                  .then(setState)
                  .catch(() => setFailed(true))
                  .finally(() => setBusy(false))
              }}
            >
              Turn on notifications
            </Button>
            {failed && <p role="alert" className="mt-2 text-sambal-deep">Couldn’t turn on notifications in this browser. Try again, or check the site’s permissions.</p>}
          </>
        )}
      </section>

      {data &&
        ROWS.map((row) => {
          const heading = row.section !== lastSection ? row.section : null
          lastSection = row.section
          return (
            <div key={row.key}>
              {heading && <h2 className="mb-1 mt-2 font-display text-base font-semibold text-kicap-soft">{heading}</h2>}
              <Toggle label={row.title} detail={row.detail} on={data.preferences[row.key]} onChange={(value) => change(row.key, value)} />
            </div>
          )
        })}
    </SubPage>
  )
}

function Toggle({ label, detail, on, onChange }: { label: string; detail?: string; on: boolean; onChange: (value: boolean) => void }) {
  return (
    <div className="flex items-center gap-4 border-b-2 border-hairline py-3">
      <div className="flex-1">
        <p className="font-display text-lg font-semibold">{label}</p>
        {detail && <p className="text-sm text-kicap-soft">{detail}</p>}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={on}
        aria-label={label}
        onClick={() => onChange(!on)}
        className={`relative h-8 w-14 shrink-0 rounded-full transition-colors ${on ? 'bg-pandan' : 'bg-hairline'}`}
      >
        <span className={`absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-[left] ${on ? 'left-7' : 'left-1'}`} />
      </button>
    </div>
  )
}

