import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { CLAIM_LABELS } from '../../api/halal'
import { submissionsApi, type Submission, type SubmissionStatus, type SubmissionType } from '../../api/submissions'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { relativeTime } from '../../lib/time'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { SignInNeeded } from './AddPlace'

type Filter = 'all' | 'review' | 'live'
const FILTERS: [Filter, string][] = [
  ['all', 'All'],
  ['review', 'In review'],
  ['live', 'Live'],
]

const TYPE_LABELS: Record<SubmissionType, string> = {
  new_place: 'New place',
  edit_place: 'Edit',
  halal_report: 'Halal report',
  owner_claim: 'Ownership claim',
  closure: 'Closure report',
  reopen: 'Reopen report',
}

const STATUS: Record<SubmissionStatus, { label: string; tone: string }> = {
  draft: { label: 'Not sent', tone: 'bg-hairline text-kicap' },
  pending: { label: 'Pending review', tone: 'bg-kunyit/35 text-kicap' },
  approved: { label: 'Live', tone: 'bg-pandan/20 text-pandan' },
  changes_requested: { label: 'Changes requested', tone: 'bg-sambal/15 text-sambal-deep' },
  rejected: { label: 'Not approved', tone: 'bg-hairline text-kicap' },
  cancelled: { label: 'Cancelled', tone: 'bg-hairline text-kicap-soft' },
}

/** Everything you've added or fixed, and where each one is in review. */
export function MyPlaces() {
  const { user } = useSession()
  if (!user || user.isGuest) return <SignInNeeded feature="see places you’ve added" />
  return <MyPlacesList />
}

function MyPlacesList() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [filter, setFilter] = useState<Filter>('all')
  const [withdrawing, setWithdrawing] = useState<Submission | null>(null)
  const [busy, setBusy] = useState(false)
  const [withdrawError, setWithdrawError] = useState<string | null>(null)
  const query = useQuery({ queryKey: ['my-submissions'], queryFn: () => submissionsApi.mine().then((response) => response.submissions) })
  const submissions = query.data ?? []
  const count = (status: SubmissionStatus) => submissions.filter((submission) => submission.status === status).length
  const shown = submissions.filter((submission) =>
    filter === 'review' ? submission.status === 'pending' || submission.status === 'changes_requested' : filter === 'live' ? submission.status === 'approved' : true,
  )

  async function withdraw() {
    if (!withdrawing) return
    setBusy(true)
    setWithdrawError(null)
    try {
      await submissionsApi.cancel(withdrawing.id)
      setWithdrawing(null)
      await queryClient.invalidateQueries({ queryKey: ['my-submissions'] })
    } catch (caught) {
      setWithdrawError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 pt-2 pb-10">
      <Link to="/community" className="-ml-1 min-h-11 self-start content-center font-display font-semibold text-sambal-deep">
        ‹ Community
      </Link>
      <div className="-mt-2 flex items-end justify-between gap-3">
        <h1 className="font-display text-3xl font-bold tracking-tight">My places</h1>
        {submissions.length > 0 && (
          <Link to="/community/add" state={{ from: '/community/mine' }} className="press inline-flex min-h-11 items-center rounded-full bg-sambal-deep px-4 font-display font-semibold text-white">
            Add a place
          </Link>
        )}
      </div>

      {query.isPending ? (
        <p role="status" className="text-kicap-soft">
          Loading…
        </p>
      ) : query.isError && submissions.length === 0 ? (
        <div className="flex flex-col items-start gap-2">
          <p role="alert">Couldn’t load your places. {errorMessage(query.error)}</p>
          <Button variant="secondary" className="max-w-xs" onClick={() => void query.refetch()}>
            Try again
          </Button>
        </div>
      ) : submissions.length === 0 ? (
        <div className="flex flex-col items-center gap-2 pt-8 text-center">
          <Mascot mood="thinking" size={96} />
          <h2 className="font-display text-xl font-bold">Nothing here yet</h2>
          <p className="max-w-sm text-kicap-soft">Places you add or fix show up here, with where they are in review.</p>
          <Button className="mt-2 max-w-xs" onClick={() => navigate('/community/add', { state: { from: '/community/mine' } })}>
            Add a place
          </Button>
        </div>
      ) : (
        <>
          <dl className="grid grid-cols-3 gap-2.5">
            <Stat count={count('approved')} label="Live" dot="bg-pandan" />
            <Stat count={count('pending')} label="In review" dot="bg-kunyit" />
            <Stat count={count('changes_requested')} label="Needs changes" dot="bg-sambal" />
          </dl>

          <div role="radiogroup" aria-label="Show" className="flex gap-1 self-start rounded-full border-2 border-hairline bg-surface p-1">
            {FILTERS.map(([value, label]) => (
              <button
                key={value}
                type="button"
                role="radio"
                aria-checked={filter === value}
                onClick={() => setFilter(value)}
                className={`min-h-10 rounded-full px-4 font-semibold ${filter === value ? 'bg-kicap text-white' : 'text-kicap'}`}
              >
                {label}
              </button>
            ))}
          </div>

          {shown.length === 0 ? (
            <p className="py-6 text-center text-kicap-soft">Nothing in this list right now.</p>
          ) : (
            <ul className="flex flex-col gap-3">
              {shown.map((submission) => (
                <SubmissionCard key={submission.id} submission={submission} onWithdraw={() => setWithdrawing(submission)} />
              ))}
            </ul>
          )}
        </>
      )}

      <Sheet open={withdrawing !== null} onClose={() => setWithdrawing(null)} title="Withdraw this submission?">
        <p className="text-kicap-soft">It won’t be reviewed. You can always add it again.</p>
        {withdrawError && (
          <p role="alert" className="text-sambal-deep">
            {withdrawError}
          </p>
        )}
        <Button onClick={() => void withdraw()} busy={busy}>
          Withdraw
        </Button>
        <Button variant="secondary" onClick={() => setWithdrawing(null)}>
          Keep it
        </Button>
      </Sheet>
    </div>
  )
}

function Stat({ count, label, dot }: { count: number; label: string; dot: string }) {
  return (
    <div className="flex flex-col gap-1 rounded-row border-2 border-hairline bg-surface px-3 py-3">
      <dt className="flex items-baseline gap-1.5 text-sm leading-tight text-kicap-soft">
        <span className={`h-2 w-2 shrink-0 -translate-y-px rounded-full ${dot}`} aria-hidden="true" />
        <span>{label}</span>
      </dt>
      <dd className="mt-auto font-display text-2xl font-bold tabular-nums">{count}</dd>
    </div>
  )
}

function SubmissionCard({ submission, onWithdraw }: { submission: Submission; onWithdraw: () => void }) {
  const status = STATUS[submission.status] ?? { label: submission.status, tone: 'bg-hairline text-kicap' }
  const sent = submission.createdAt ? relativeTime(submission.createdAt) : null
  const detail = submission.submissionType === 'halal_report' ? halalLine(submission) : submission.foodCategory
  // Only these two can be withdrawn; the server refuses a pending one.
  const canWithdraw = submission.status === 'draft' || submission.status === 'changes_requested'
  const onTrack = submission.status === 'pending' || submission.status === 'approved'
  const note = submission.reviewNote && (submission.status === 'changes_requested' || submission.status === 'rejected') ? submission.reviewNote : null

  return (
    <li className={`flex flex-col gap-3 rounded-card border-2 border-hairline bg-surface px-5 py-4 ${submission.status === 'cancelled' ? 'opacity-60' : ''}`}>
      <div>
        <p className="font-display text-lg font-bold">{submission.name}</p>
        <p className="text-sm text-kicap-soft">{[TYPE_LABELS[submission.submissionType], detail, sent].filter(Boolean).join(', ')}</p>
      </div>
      <div className="flex items-center gap-3">
        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${status.tone}`}>{status.label}</span>
        {onTrack && (
          // Sent, reviewing, live.
          <span className="ml-auto flex gap-1" role="img" aria-label={submission.status === 'approved' ? 'Live' : 'Reviewing'}>
            {[0, 1, 2].map((at) => (
              <span key={at} className={`h-1.5 w-6 rounded-full ${at < (submission.status === 'approved' ? 3 : 2) ? (submission.status === 'approved' ? 'bg-pandan' : 'bg-kunyit') : 'bg-hairline'}`} />
            ))}
          </span>
        )}
      </div>
      {note && (
        <div className="rounded-row bg-sambal/8 px-4 py-3">
          <p className="text-sm font-semibold">Reviewer’s note</p>
          <p className="text-sm">{note}</p>
        </div>
      )}
      {canWithdraw && (
        <button type="button" onClick={onWithdraw} className="min-h-11 self-start font-semibold text-sambal-deep underline decoration-2 underline-offset-4">
          Withdraw
        </button>
      )}
    </li>
  )
}

function halalLine(submission: Submission): string | null {
  const claim = submission.halalClaim ? (CLAIM_LABELS[submission.halalClaim as keyof typeof CLAIM_LABELS] ?? submission.halalClaim) : null
  const resolved = submission.halalResolvedStatus
  if (resolved && resolved !== submission.halalClaim) return [claim, CLAIM_LABELS[resolved as keyof typeof CLAIM_LABELS] ?? resolved].filter(Boolean).join(' to ')
  return claim
}
