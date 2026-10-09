import SwiftUI

extension Color {
    static let sambalRed = Color("SambalRed")
    static let nasiCream = Color("NasiCream")
    static let kicap = Color("Kicap")
    static let pandan = Color("Pandan")
    static let kunyit = Color("Kunyit")

    // Opaque neutral ramp — replaces ad-hoc `kicap.opacity(x)` tints so surfaces match.
    /// Secondary text and quiet icons. Passes AA on nasiCream.
    static let kicapSecondary = Color("KicapSecondary")
    /// Card and grouped-list fill, a step lighter than the nasiCream page.
    static let surface = Color("Surface")
    /// Borders, dividers, pressed-row highlight.
    static let hairline = Color("Hairline")
}
