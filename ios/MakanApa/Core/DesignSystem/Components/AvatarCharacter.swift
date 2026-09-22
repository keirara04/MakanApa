import Foundation

/// Preset profile-avatar characters a user can pick in Settings → Profile. `key` mirrors the
/// backend's `avatar_key` column and `config/avatars.php` exactly — the backend only ever sees
/// this string, never a filename, so real character art can replace the `Avatar_*` placeholder
/// assets later without any API or model change.
enum AvatarCharacter: String, CaseIterable, Identifiable {
    case defaultCharacter = "default"
    case nasi
    case roti
    case milo
    case laksa
    case tehTarik = "teh_tarik"
    case mascot

    var id: String { rawValue }
    var key: String { rawValue }

    var displayName: String {
        switch self {
        case .defaultCharacter: "Classic"
        case .nasi: "Nasi"
        case .roti: "Roti"
        case .milo: "Milo"
        case .laksa: "Laksa"
        case .tehTarik: "Teh Tarik"
        case .mascot: "MakanApa"
        }
    }

    /// `.mascot` points at the official mascot art already shipped for other screens
    /// (`MascotDefault`) rather than an `Avatar_*` asset — everything else follows the
    /// `Avatar_<key>` convention.
    var imageName: String {
        switch self {
        case .mascot: "MascotDefault"
        default: "Avatar_\(rawValue)"
        }
    }

    init(key: String?) {
        self = AvatarCharacter(rawValue: key ?? "") ?? .defaultCharacter
    }
}
