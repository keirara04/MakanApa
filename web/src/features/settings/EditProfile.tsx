import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { accountApi, AVATARS, avatarSrc, type Community } from '../../api/account'
import { authApi } from '../../api/auth'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { Button } from '../../ui/Button'
import { Field } from '../../ui/Field'
import { Sheet } from '../../ui/Sheet'
import { useContributionGate } from '../auth/ContributionGate'
import { Chevron } from './Settings'
import { SubPage } from './SubPage'

export function EditProfile() {
  const { user, setUser } = useSession()
  const navigate = useNavigate()
  const [name, setName] = useState(user?.name ?? '')
  const [avatar, setAvatar] = useState(user?.avatarKey ?? 'default')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [choosing, setChoosing] = useState(false)

  if (!user) return null
  const community = user.university ?? user.area ?? 'Public'

  async function save() {
    if (!name.trim()) {
      setError('Add a name so people know who’s posting.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      const { user: updated } = await authApi.updateProfile({ name: name.trim(), avatarKey: avatar })
      setUser(updated)
      navigate('/settings')
    } catch {
      setError('Couldn’t save your profile. Try again.')
      setBusy(false)
    }
  }

  return (
    <SubPage title="Edit profile">
      <div className="flex flex-col items-center gap-2 text-center">
        <img src={avatarSrc(avatar)} alt="" width={96} height={96} className="h-24 w-24 rounded-full border-[3px] border-white bg-kunyit/25 object-cover shadow-lift" />
        <p className={`font-display text-2xl font-semibold ${name.trim() ? '' : 'text-kicap-soft'}`}>{name.trim() || 'Your name'}</p>
        <p className="text-sm text-kicap-soft">{community}</p>
      </div>

      <fieldset>
        <legend className="font-display text-lg font-semibold">Choose your character</legend>
        <div className="mt-3 grid grid-cols-4 gap-3 sm:grid-cols-7">
          {AVATARS.map(([key, label]) => (
            <label key={key} className="flex cursor-pointer flex-col items-center gap-1">
              <input type="radio" name="avatar" value={key} checked={avatar === key} onChange={() => setAvatar(key)} className="peer sr-only" />
              <img
                src={avatarSrc(key)}
                alt=""
                width={56}
                height={56}
                className="h-14 w-14 rounded-full bg-kunyit/25 object-cover ring-offset-2 ring-offset-cream peer-checked:ring-[3px] peer-checked:ring-sambal peer-focus-visible:ring-[3px] peer-focus-visible:ring-kicap"
              />
              <span className="text-xs font-semibold">{label}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <Field label="Name" value={name} onChange={(event) => setName(event.target.value)} maxLength={100} autoComplete="name" />

      <button type="button" onClick={() => setChoosing(true)} className="flex min-h-16 items-center gap-3 border-y-2 border-hairline py-3 text-left">
        <span className="flex-1">
          <span className="block font-display text-lg font-semibold">Community</span>
          <span className="text-sm text-kicap-soft">{community}</span>
        </span>
        <Chevron />
      </button>

      {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      <Button onClick={() => void save()} busy={busy} className="md:max-w-xs">
        Save
      </Button>

      <CommunityPicker open={choosing} onClose={() => setChoosing(false)} />
    </SubPage>
  )
}

type Choice = { type: 'public' } | { type: 'university' | 'area'; shortName: string }

/** Public, a university, or an area. Saves on its own, separate from the profile. */
export function CommunityPicker({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, setUser } = useSession()
  const initial: Choice = user?.university ? { type: 'university', shortName: user.university } : user?.area ? { type: 'area', shortName: user.area } : { type: 'public' }
  const [choice, setChoice] = useState<Choice>(initial)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [requesting, setRequesting] = useState<'university' | 'area' | null>(null)
  const universities = useQuery({ queryKey: ['universities'], queryFn: accountApi.universities, enabled: open, staleTime: 3_600_000 })
  const areas = useQuery({ queryKey: ['areas'], queryFn: accountApi.areas, enabled: open, staleTime: 3_600_000 })

  async function save() {
    setBusy(true)
    setError(null)
    try {
      const body = choice.type === 'university' ? { university: choice.shortName } : choice.type === 'area' ? { area: choice.shortName } : {}
      const { user: updated } = await accountApi.setCommunity(body)
      setUser(updated)
      onClose()
    } catch {
      setError('Couldn’t save your community. Try again.')
    } finally {
      setBusy(false)
    }
  }

  const option = (selected: boolean, onSelect: () => void, title: string, detail?: string) => (
    <label className="flex min-h-13 cursor-pointer items-center gap-3 py-2">
      <input type="radio" name="community" checked={selected} onChange={onSelect} className="h-5 w-5 accent-[var(--color-sambal)]" />
      <span>
        <span className="block font-semibold">{title}</span>
        {detail && <span className="text-xs text-kicap-soft">{detail}</span>}
      </span>
    </label>
  )

  const list = (type: 'university' | 'area', query: typeof universities | typeof areas, items: Community[] | undefined, label: string) => (
    <fieldset>
      <legend className="text-sm font-semibold text-kicap-soft">{label}</legend>
      {query.isLoading && <p className="py-2 text-sm">Loading…</p>}
      {query.isError && (
        <p className="py-2 text-sm">
          Couldn’t load the list.{' '}
          <button type="button" className="underline" onClick={() => void query.refetch()}>
            Retry
          </button>
        </p>
      )}
      <div className="divide-y divide-hairline">
        {items?.map((item) =>
          option(choice.type === type && choice.shortName === item.shortName, () => setChoice({ type, shortName: item.shortName }), item.shortName, item.name),
        )}
      </div>
      <button type="button" onClick={() => setRequesting(type)} className="min-h-11 text-sm font-semibold text-sambal-deep underline">
        Can’t find your {type}? Request it
      </button>
    </fieldset>
  )

  return (
    <>
      <Sheet open={open && !requesting} onClose={onClose} title="Community">
        <p className="text-kicap-soft">This helps MakanApa show what’s popular around people in your community.</p>
        <div className="max-h-[50dvh] overflow-y-auto pr-1">
          {option(choice.type === 'public', () => setChoice({ type: 'public' }), 'Public')}
          {list('university', universities, universities.data?.universities, 'Universities')}
          <div className="mt-3">{list('area', areas, areas.data?.areas, 'Areas')}</div>
        </div>
        <p className="text-sm text-kicap-soft">You can change your community anytime.</p>
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
        <Button onClick={() => void save()} busy={busy} disabled={universities.isLoading || areas.isLoading}>
          Save
        </Button>
        <Button variant="quiet" onClick={onClose}>
          Cancel
        </Button>
      </Sheet>
      <RequestCommunity type={requesting} onClose={() => setRequesting(null)} />
    </>
  )
}

function RequestCommunity({ type, onClose }: { type: 'university' | 'area' | null; onClose: () => void }) {
  const { gate, run } = useContributionGate('request a new community')
  const [name, setName] = useState('')
  const [state, setState] = useState<'idle' | 'sending' | 'sent'>('idle')
  const [error, setError] = useState<string | null>(null)

  function send() {
    if (!type) return
    run(() => {
      setState('sending')
      setError(null)
      accountApi
        .requestCommunity(type, name.trim())
        .then(() => setState('sent'))
        .catch((caught) => {
          setError(errorMessage(caught))
          setState('idle')
        })
    })
  }

  return (
    <>
      <Sheet
        open={type !== null}
        onClose={() => {
          setName('')
          setState('idle')
          onClose()
        }}
        title="Request"
      >
        {state === 'sent' ? (
          <p role="status">Thanks, we’ll take a look!</p>
        ) : (
          <Field
            label={type === 'university' ? 'University name' : 'Area name'}
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={150}
            placeholder={type === 'university' ? 'e.g. Universiti Malaya' : 'e.g. Shah Alam, Cheras, your neighbourhood'}
          />
        )}
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
        {state !== 'sent' && (
          <Button onClick={send} disabled={!name.trim()} busy={state === 'sending'}>
            Send request
          </Button>
        )}
        <Button variant="quiet" onClick={onClose}>
          {state === 'sent' ? 'Done' : 'Cancel'}
        </Button>
      </Sheet>
      {gate}
    </>
  )
}
