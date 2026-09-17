import SwiftUI

extension Color {
    static let sambalRed = Color("SambalRed")
    static let nasiCream = Color("NasiCream")
    static let kicap = Color("Kicap")
    static let pandan = Color("Pandan")
    static let kunyit = Color("Kunyit")

    /// Subtle stroke for glass surfaces (e.g. `FloatingTabBar`) — derived from `kicap` rather than
    /// hardcoded white so it still reads correctly if dark mode is added later.
    static let hairline = kicap.opacity(0.08)
}
