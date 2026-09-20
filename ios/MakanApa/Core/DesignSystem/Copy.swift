import Foundation

/// Centralized Malaysian-voice copy so tone stays consistent across screens.
enum Copy {
    static let homeGreeting = "Hungry?"
    static let homeSubtext = "Okay, what we doing today?"
    static let gengComingSoon = "Soon lah 👀 Geng mode is still cooking."

    static let soloMoodPrompt = "What mood today?"
    static let soloMoodSubtext = "Craving something in particular?"
    static let soloAnythingLah = "🎲 Anything lah"
    static let anythingLahSubtext = "Surprise me!"
    static let budgetAnythingSubtext = "No limits"
    static let soloBudgetPrompt = "How much you wanna spend?"
    static let soloBudgetSubtext = "per person ya!"
    static let soloDistancePrompt = "How far willing to jalan?"
    static let soloDistanceSubtext = "No sweat, we'll find something nice."
    static let soloCTA = "MAKANAPA?"

    static let moodLightSubtext = "Something lighter"
    static let moodQuickSubtext = "Fast & convenient"

    static let moodNasiKandarSubtext = "Kandar power"
    static let moodAyamGepukSubtext = "Smashed, spicy, sedap"
    static let moodNasiPadangSubtext = "Rendang, gulai, the works"
    static let moodMeeGorengSubtext = "Wok hei, always hits"
    static let moodNasiLemakSubtext = "Anytime, anywhere"
    static let moodCharKueyTeowSubtext = "Smoky and shiok"
    static let moodBananaLeafRiceSubtext = "Banjir gravy, no regrets"
    static let moodDimSumSubtext = "Small plates, big satisfaction"
    static let moodCustomCravingPlaceholder = "Cakap je nak makan apa..."

    static let thinking = "Thinking so you don't have to..."
    static let thinkingStep1 = "Finding nearby spots..."
    static let thinkingStep2 = "Checking what fits you..."
    static let thinkingStep3 = "Picking the best one..."

    static let rerollHeadline = "Cari lagi!"
    static let rerollLine1 = "Same preferences"
    static let rerollLine2 = "Different spot"
    static let rerollLine3 = "Still sedap, don't worry"

    static let noResultHeadline = "No spots found nearby"
    static let noResultDetail = "Try increasing the distance or changing your preferences."

    static let trustBadgeWhileUsing = "While using"
    static let trustBadgePrivacy = "We respect privacy"
    static let trustBadgeNearby = "Nearby spots"

    static let resultIntro = "MakanApa says..."
    static let resultThatsIt = "Settled."
    static let jomMakan = "JOM MAKAN"
    static let pickAgain = "Cari lain lah"
    static let anythingLabel = "Anything"

    static let emptyState = "Wah, demanding ah 😭 Try increasing your distance."

    static let connectionErrorHeadline = "Aiyo 😭"
    static let connectionErrorDetail = "Connection gone.\nCouldn't find makan right now."
    static let genericAPIErrorHeadline = "Aiyo 😭"
    static let genericAPIErrorDetail = "MakanApa blur kejap.\nTry again in a bit."
    static let tryAgain = "Try again"
    static let backHome = "Back home"

    static let locationDenied = "Can't find makan if we don't know where you are 👀"
    static let locationPrivacyLine = "We only use your location while you're using MakanApa."

    static let tagline = "Less thinking. More makan."
    static let aboutDescription = "MakanApa helps you decide what to eat. Tell it your mood and budget, and it picks food nearby — less thinking, more makan."
    static let privacyPolicyURL = "https://dear-papaya-894.notion.site/MakanApa-Privacy-Policy-3dd5ee337d988041840fdc5560b64bf8"

    static let privateBetaEyebrow = "PRIVATE BETA"
    static let loginTagline = "Makan dulu. Decide later."
    static let signIn = "Sign in"
    static let loginInvalidCredentials = "Email or password doesn't match."
    static let joinBetaPrompt = "Don't have beta access?"
    static let joinBetaCTA = "Join beta →"
    static let joinBetaSheetTitle = "Private beta"
    static let joinBetaSheetBody = "MakanApa is currently being tested with a small group of university students. We're starting around UKM first and opening access gradually as we improve recommendations."
    static let joinBetaGotIt = "Got it"

    static let communityHeadlineUniversityFormat = "%@ tengah makan apa? 👀"
    static let communitySubtitleUniversityFormat = "Popular around %@"
    static let communityHeadlinePublic = "What's trending near you"
    static let communitySubtitlePublic = "Trending picks nearby"
    static let communityEmptyHeadline = "Nothing trending yet 👀"
    static let communityEmptyDetail = "Every recommendation your community picks helps build this page."
    static let communityLocationPromptHeadline = "See what's trending around you"
    static let communityLocationPromptDetail = "MakanApa uses your location to find popular picks nearby."
    static let communityLocationEnable = "Enable Location"
    static let communityLocationDeniedHeadline = "Location is off"
    static let communityLocationDeniedDetail = "Turn on location access to see what's trending nearby."
    static let communityLocationOpenSettings = "Open Settings"
}
