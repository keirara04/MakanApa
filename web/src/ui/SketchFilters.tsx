/** The two wobble filters the `.sketch` outline uses, same values as the marketing site. Mounted once. */
export function SketchFilters() {
  return (
    <svg width="0" height="0" aria-hidden="true" className="absolute">
      <filter id="sketch-a" x="-5%" y="-5%" width="110%" height="110%">
        <feTurbulence type="fractalNoise" baseFrequency="0.018" numOctaves="2" seed="4" result="noise" />
        <feDisplacementMap in="SourceGraphic" in2="noise" scale="5" xChannelSelector="R" yChannelSelector="G" />
      </filter>
      <filter id="sketch-b" x="-5%" y="-5%" width="110%" height="110%">
        <feTurbulence type="fractalNoise" baseFrequency="0.024" numOctaves="2" seed="11" result="noise" />
        <feDisplacementMap in="SourceGraphic" in2="noise" scale="7" xChannelSelector="G" yChannelSelector="R" />
      </filter>
    </svg>
  )
}
