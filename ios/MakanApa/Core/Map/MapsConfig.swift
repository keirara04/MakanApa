import Foundation

enum MapsConfig {
    // Same key as api/.env's GOOGLE_PLACES_API_KEY (Maps SDK for iOS + Places API both enabled
    // on it). Restrict it in Google Cloud Console to this app's bundle ID — an unrestricted key
    // embedded in a shipped binary is extractable and usable by anyone.
    static let apiKey = "AIzaSyBGeW8YStPbZXEr6c4MWNcN8yiUmZnCt8I"
}
