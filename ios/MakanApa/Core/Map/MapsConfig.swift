import Foundation

enum MapsConfig {
    // Dedicated Maps SDK for iOS key, separate from api/.env's GOOGLE_PLACES_API_KEY —
    // restricted in Google Cloud Console to this app's bundle ID (com.keirara.makanapa) and
    // to the Maps SDK for iOS API only. Keeping it separate from the server key means
    // restricting one doesn't block the other (a single key can only carry one application
    // restriction type — see the Nearby photo/reviews 403 this split fixes).
    static let apiKey = "AIzaSyBXH9oWJrdcvfd0lKL52OPPTFitoYMmKDQ"
}
