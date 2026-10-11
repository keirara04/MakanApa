import { useState, type ReactNode } from 'react'
import { install, installPlatform, useCanInstall, type InstallPlatform } from '../../lib/install'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'

const PLATFORMS: [InstallPlatform, string][] = [
  ['iphone', 'iPhone'],
  ['android', 'Android'],
  ['computer', 'Computer'],
]

/**
 * "Add MakanApa to your home screen": the steps for this device first (iPhone, Android or a
 * computer), each with a small drawing of where to tap. Where the browser can install directly
 * (Chrome, Edge), one button does it.
 */
export function InstallGuide({ open, onClose }: { open: boolean; onClose: () => void }) {
  const canInstall = useCanInstall()
  const [platform, setPlatform] = useState<InstallPlatform>(() => installPlatform())

  return (
    <Sheet open={open} onClose={onClose} title="Add MakanApa to your home screen">
      <p className="-mt-2 text-kicap-soft">It opens full screen like an app, starts faster and can send mealtime notifications. No app store, nothing to download.</p>

      {canInstall && (
        <div className="flex flex-col gap-2 rounded-card bg-cream p-4">
          <Button onClick={() => void install().then((installed) => installed && onClose())}>Install MakanApa</Button>
          <p className="text-center text-sm text-kicap-soft">Your browser can do it in one tap. Or follow the steps below.</p>
        </div>
      )}

      <div className="flex gap-1 rounded-full bg-cream p-1" role="radiogroup" aria-label="Your device">
        {PLATFORMS.map(([value, label]) => (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={platform === value}
            onClick={() => setPlatform(value)}
            className={`press min-h-10 flex-1 rounded-full font-display text-sm font-semibold ${platform === value ? 'bg-surface text-kicap shadow-sm' : 'text-kicap-soft'}`}
          >
            {label}
          </button>
        ))}
      </div>

      <ol className="flex flex-col gap-5">
        {STEPS[platform].map((step, index) => (
          <li key={step.title} className="flex flex-col gap-2.5">
            <div className="flex items-start gap-3">
              <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sambal font-display text-sm font-bold text-white">{index + 1}</span>
              <div>
                <p className="font-display font-semibold">{step.title}</p>
                <p className="text-sm text-kicap-soft">{step.detail}</p>
              </div>
            </div>
            <div aria-hidden="true" className="ml-10">
              {step.drawing}
            </div>
          </li>
        ))}
      </ol>

      <p className="rounded-row bg-kunyit/20 px-4 py-3 text-sm">{NOTES[platform]}</p>

      <Button variant="secondary" onClick={onClose}>
        Done
      </Button>
    </Sheet>
  )
}

const NOTES: Record<InstallPlatform, string> = {
  iphone: 'Using Chrome on iPhone? Tap Share next to the address bar, then Add to Home Screen. On iPhone, mealtime notifications only work once MakanApa is on your home screen.',
  android: 'Samsung Internet: tap the menu at the bottom, then Add page to, then Home screen.',
  computer: 'Safari on a Mac: choose File in the menu bar, then Add to Dock.',
}

/* The drawings: simplified browser parts, with what to tap circled in sambal. */

const RING = 'ring-[3px] ring-sambal ring-offset-2 ring-offset-cream'

function Frame({ children }: { children: ReactNode }) {
  return <div className="w-full max-w-64 overflow-hidden rounded-[16px] border border-hairline bg-cream p-2.5">{children}</div>
}

function Glyph({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={`h-5 w-5 ${className}`} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      {children}
    </svg>
  )
}

const ShareGlyph = () => (
  <Glyph>
    <path d="M8 9H6v11h12V9h-2M12 3v12M8.5 6.5 12 3l3.5 3.5" />
  </Glyph>
)

const AddGlyph = () => (
  <Glyph className="h-4 w-4">
    <rect x="4" y="4" width="16" height="16" rx="4" />
    <path d="M12 8.5v7M8.5 12h7" />
  </Glyph>
)

const Bar = ({ width }: { width: string }) => <span className={`block h-2 rounded-full bg-kicap/10 ${width}`} />

/** Safari's bottom toolbar, Share circled. */
function SafariToolbar() {
  return (
    <Frame>
      <div className="mb-2 flex h-8 items-center justify-center rounded-lg bg-surface text-xs text-kicap-soft">app.trymakanapa.com</div>
      <div className="flex items-center justify-around rounded-lg bg-surface py-1.5 text-kicap-soft">
        <Glyph>
          <path d="m14 6-6 6 6 6" />
        </Glyph>
        <Glyph>
          <path d="m10 6 6 6-6 6" />
        </Glyph>
        <span className={`rounded-md p-1 text-sambal-deep ${RING}`}>
          <ShareGlyph />
        </span>
        <Glyph>
          <path d="M5 4h14v16l-7-4-7 4z" />
        </Glyph>
        <Glyph>
          <rect x="4" y="4" width="7" height="7" rx="1.5" />
          <rect x="13" y="4" width="7" height="7" rx="1.5" />
          <rect x="4" y="13" width="7" height="7" rx="1.5" />
          <rect x="13" y="13" width="7" height="7" rx="1.5" />
        </Glyph>
      </div>
    </Frame>
  )
}

/** A menu or share sheet with one row circled. */
function Menu({ rows, pick, icon }: { rows: string[]; pick: string; icon: ReactNode }) {
  return (
    <Frame>
      <div className="flex flex-col gap-1.5">
        {rows.map((row) => (
          <span
            key={row}
            className={`flex items-center justify-between rounded-lg bg-surface px-3 py-2 text-xs font-semibold ${row === pick ? `text-kicap ${RING}` : 'text-kicap-soft'}`}
          >
            {row}
            {row === pick && icon}
          </span>
        ))}
      </div>
    </Frame>
  )
}

/** A confirm dialog with its main button circled. */
function Confirm({ title, action }: { title: string; action: string }) {
  return (
    <Frame>
      <div className="flex flex-col gap-2 rounded-lg bg-surface p-3">
        <div className="flex items-center gap-2">
          <img src="/icon-192.png" alt="" width={28} height={28} className="h-7 w-7 rounded-lg" />
          <span className="text-xs font-semibold">{title}</span>
        </div>
        <Bar width="w-4/5" />
        <div className="flex justify-end gap-2 pt-1 text-xs font-semibold">
          <span className="px-2 py-1 text-kicap-soft">Cancel</span>
          <span className={`rounded-md px-2.5 py-1 text-sambal-deep ${RING}`}>{action}</span>
        </div>
      </div>
    </Frame>
  )
}

/** The finished result: MakanApa's icon among the others. */
function HomeScreen({ dock = false }: { dock?: boolean }) {
  const others = ['bg-pandan/40', 'bg-kunyit/50', 'bg-kicap/15']
  return (
    <Frame>
      <div className={`flex items-end justify-center gap-3 ${dock ? 'rounded-xl bg-surface/80 px-3 py-2' : 'py-1'}`}>
        {others.slice(0, 2).map((tone) => (
          <span key={tone} className={`h-10 w-10 rounded-[11px] ${tone}`} />
        ))}
        <span className="flex flex-col items-center gap-1">
          <img src="/icon-192.png" alt="" width={40} height={40} className={`h-10 w-10 rounded-[11px] ${RING}`} />
          <span className="text-[10px] font-semibold">MakanApa</span>
        </span>
        <span className={`h-10 w-10 rounded-[11px] ${others[2]}`} />
      </div>
    </Frame>
  )
}

/** Chrome's address bar on Android, the three-dot menu circled. */
function ChromeAndroidBar() {
  return (
    <Frame>
      <div className="flex items-center gap-2 rounded-lg bg-surface px-2 py-1.5 text-kicap-soft">
        <span className="flex h-7 flex-1 items-center rounded-full bg-cream px-3 text-xs">app.trymakanapa.com</span>
        <span className={`rounded-md px-1 py-1 text-sambal-deep ${RING}`}>
          <Glyph>
            <circle cx="12" cy="5" r="1.4" fill="currentColor" />
            <circle cx="12" cy="12" r="1.4" fill="currentColor" />
            <circle cx="12" cy="19" r="1.4" fill="currentColor" />
          </Glyph>
        </span>
      </div>
    </Frame>
  )
}

/** A desktop address bar with the install icon circled at its right end. */
function DesktopBar() {
  return (
    <Frame>
      <div className="flex items-center gap-2 rounded-lg bg-surface px-2 py-1.5 text-kicap-soft">
        <span className="flex h-7 flex-1 items-center justify-between rounded-full bg-cream pl-3 pr-1 text-xs">
          app.trymakanapa.com
          <span className={`rounded-full p-0.5 text-sambal-deep ${RING}`}>
            <Glyph className="h-4 w-4">
              <rect x="3" y="4" width="18" height="12" rx="2" />
              <path d="M8 20h8M12 7v6M9.5 10.5 12 13l2.5-2.5" />
            </Glyph>
          </span>
        </span>
      </div>
    </Frame>
  )
}

const STEPS: Record<InstallPlatform, { title: string; detail: string; drawing: ReactNode }[]> = {
  iphone: [
    { title: 'Tap Share', detail: 'The square with an arrow, in Safari’s toolbar.', drawing: <SafariToolbar /> },
    {
      title: 'Tap Add to Home Screen',
      detail: 'Scroll down the list if you don’t see it.',
      drawing: <Menu rows={['Copy', 'Add to Reading List', 'Add to Home Screen']} pick="Add to Home Screen" icon={<AddGlyph />} />,
    },
    { title: 'Tap Add', detail: 'MakanApa is now on your home screen. Open it from there.', drawing: <HomeScreen /> },
  ],
  android: [
    { title: 'Tap the menu', detail: 'The three dots at the top right of Chrome.', drawing: <ChromeAndroidBar /> },
    {
      title: 'Tap Add to Home screen',
      detail: 'Some phones say Install app instead.',
      drawing: <Menu rows={['New tab', 'Bookmarks', 'Add to Home screen']} pick="Add to Home screen" icon={<AddGlyph />} />,
    },
    { title: 'Tap Install', detail: 'MakanApa appears on your home screen and in your app list.', drawing: <Confirm title="Install MakanApa?" action="Install" /> },
  ],
  computer: [
    { title: 'Click the install icon', detail: 'At the right end of the address bar, in Chrome or Edge.', drawing: <DesktopBar /> },
    { title: 'Click Install', detail: 'MakanApa opens in its own window.', drawing: <Confirm title="Install MakanApa?" action="Install" /> },
    { title: 'Open it like any app', detail: 'From your dock, Start menu or app list.', drawing: <HomeScreen dock /> },
  ],
}
