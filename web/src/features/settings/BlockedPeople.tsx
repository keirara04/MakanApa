import { useQuery, useQueryClient } from '@tanstack/react-query'
import { accountApi, avatarSrc } from '../../api/account'
import { Button } from '../../ui/Button'
import { SubPage } from './SubPage'

/** People whose posts and replies you've hidden (and who can't see yours). */
export function BlockedPeople() {
  const queryClient = useQueryClient()
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: ['blocks'], queryFn: accountApi.blocks })

  return (
    <SubPage title="Blocked people">
      {isLoading && <p role="status">Loading…</p>}
      {isError && (
        <div className="flex flex-col items-start gap-2">
          <p>No connection. Couldn’t load the list.</p>
          <Button variant="secondary" className="max-w-xs" onClick={() => void refetch()}>
            Try again
          </Button>
        </div>
      )}
      {data && data.users.length === 0 && <p className="text-kicap-soft">You haven’t blocked anyone.</p>}
      {data && data.users.length > 0 && (
        <ul className="divide-y-2 divide-hairline border-y-2 border-hairline">
          {data.users.map((person) => (
            <li key={person.id} className="flex min-h-16 items-center gap-3 py-2">
              <img src={avatarSrc(person.avatarKey)} alt="" width={32} height={32} className="h-8 w-8 rounded-full bg-kunyit/25" />
              <span className="flex-1 font-semibold">{person.name}</span>
              <button
                type="button"
                onClick={() =>
                  void accountApi
                    .unblock(person.id)
                    .then(() => queryClient.setQueryData(['blocks'], { users: data.users.filter((item) => item.id !== person.id) }))
                    .catch(() => {})
                }
                className="press min-h-11 rounded-full border-2 border-sambal/40 px-4 font-semibold text-sambal-deep"
              >
                Unblock
              </button>
            </li>
          ))}
        </ul>
      )}
    </SubPage>
  )
}
