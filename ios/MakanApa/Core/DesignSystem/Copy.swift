import Foundation

/// Centralized copy so tone stays consistent across screens. Plain English only —
/// dish names, the brand and Selera stay as they are.
enum Copy {
    /// Mealtime-aware greeting; supper covers the late-night stretch.
    static func homeGreeting(hour: Int) -> String {
        switch hour {
        case 5..<11: return "Breakfast?"
        case 11..<15: return "Lunch?"
        case 15..<18: return "Tea time?"
        case 18..<22: return "Dinner?"
        default: return "Supper?"
        }
    }
    // Onboarding
    static let onboardingHeroTitle = "Can't decide what to eat?"
    static let onboardingHeroSubtext = "Tap once and we'll pick somewhere good nearby. No endless scrolling, no group-chat debates."
    static let onboardingHeroCTA = "Get started"
    static let onboardingHaveAccount = "I already have an account"
    static let onboardingLocationTitle = "Find good food around you"
    static let onboardingLocationSubtext = "We use your location to pick places near you and show what's popular in your area."
    static let onboardingLocationFootnote = "Your location is never shown to other people."
    static let onboardingLocationConfirmed = "Location is on"
    static let onboardingHalalTitle = "Do you only eat halal?"
    static let onboardingHalalSubtext = "We'll hide places known to be non-halal. Places we haven't verified yet still show, clearly marked. Change it anytime from the map."
    static let onboardingHalalFootnote = "Halal info comes from the community. Always double-check at the restaurant."
    static let onboardingHalalYes = "Yes, hide non-halal"
    static let onboardingHalalNo = "No, show everything"

    // Taste prompt (after a real pick)
    static let tasteTitle = "Want sharper picks?"
    static let tasteSubtext = "Choose up to 4 things you usually go for. We'll lean that way."
    static let tasteFoodHeader = "Food"
    static let tasteStyleHeader = "Style"
    static let tasteSave = "Save"
    static let tasteNotNow = "Not now"
    static let tasteNonePicked = "None picked yet"
    static func tastePickedCount(_ count: Int, of max: Int) -> String { "\(count) of \(max) picked" }
    static func tasteLabel(_ key: String) -> String {
        switch key {
        case "malay": return "Malay"
        case "mamak": return "Mamak"
        case "korean": return "Korean"
        case "cafe": return "Cafe"
        case "dessert": return "Dessert"
        case "cheap_eats": return "Cheap eats"
        case "late_night": return "Late night"
        default: return "Anything"
        }
    }

    static let homeSubtext = "Let's figure out what to eat."
    static let quickPickTitle = "Pick for me"
    static let chooseCravingTitle = "I know what I want"
    static let chooseCravingSubtitle = "Craving, budget, distance"
    static let quickPickHint = "Picks somewhere nearby right away using your usual budget and distance"

    static let recentTitle = "Recent"
    static let recentEmpty = "Places you pick will show up here."
    static let recentToday = "Today"
    static let recentYesterday = "Yesterday"
    static let openInMaps = "Open in Maps"
    static let removeFromRecent = "Remove from Recent"

    static let soloMoodPrompt = "What mood today?"
    static let soloMoodSubtext = "Craving something in particular?"
    static let budgetAnythingSubtext = "No limits"
    static let soloBudgetPrompt = "How much do you want to spend?"
    static let soloBudgetSubtext = "Per person"
    static let soloDistancePrompt = "How far will you go?"
    static let soloDistanceSubtext = "No sweat, we'll find something nice."
    static let soloCTA = "MAKANAPA?"

    static let moodLightSubtext = "Something lighter"
    static let moodQuickSubtext = "Fast & convenient"

    static let moodNasiKandarSubtext = "Kandar power"
    static let moodAyamGepukSubtext = "Smashed, spicy, delicious"
    static let moodNasiPadangSubtext = "Rendang, gulai, the works"
    static let moodMeeGorengSubtext = "Wok hei, always hits"
    static let moodNasiLemakSubtext = "Anytime, anywhere"
    static let moodCharKueyTeowSubtext = "Smoky and satisfying"
    static let moodBananaLeafRiceSubtext = "Drowned in curry, no regrets"
    static let moodDimSumSubtext = "Small plates, big satisfaction"
    static let moodCustomCravingPlaceholder = "Tell me what you're craving..."

    static let thinking = "Thinking so you don't have to..."
    static let thinkingStep1 = "Finding nearby spots..."
    static let thinkingStep2 = "Checking what fits you..."
    static let thinkingStep3 = "Picking the best one..."

    static let rerollHeadline = "Finding another one!"
    static let rerollLine1 = "Same preferences"
    static let rerollLine2 = "Different spot"
    static let rerollLine3 = "Still good, don't worry"

    static let noResultHeadline = "No spots found nearby"
    static let noResultDetail = "Try increasing the distance or changing your preferences."

    static let trustBadgeWhileUsing = "While using"
    static let trustBadgePrivacy = "We respect privacy"
    static let trustBadgeNearby = "Nearby spots"

    /// One label for "this is your pick" — result eyebrow and the onboarding demo badge.
    static let pickedForYou = "Picked for you"
    static let resultGo = "Let's eat"
    static let resultFatigue = "Okay, enough choosing"
    static let anythingLabel = "Anything"

    static let emptyState = "Nothing fits yet 😭 Try increasing your distance."

    static let connectionErrorHeadline = "Oops 😭"
    static let connectionErrorDetail = "No connection.\nCouldn't find food right now."
    static let genericAPIErrorHeadline = "Oops 😭"
    static let genericAPIErrorDetail = "MakanApa hit a snag.\nTry again in a bit."
    static let tryAgain = "Try again"
    static let rateLimitedHeadline = "Slow down a bit 😅"

    static func rateLimitedDetail(retryAfterSeconds: Int?) -> String {
        guard let seconds = retryAfterSeconds, seconds > 0 else {
            return "Too many tries in a row.\nTry again in a minute."
        }
        return "Too many tries in a row.\nTry again in \(seconds)s."
    }
    static let backHome = "Back home"

    static let locationDenied = "Can't find food if we don't know where you are 👀"
    static let locationPrivacyLine = "We only use your location while you're using MakanApa."

    static let tagline = "Less thinking. More eating."
    static let aboutDescription = "MakanApa helps you decide what to eat. Tell it your mood and budget, and it picks food nearby — less thinking, more eating."
    static let privacyPolicyURL = "https://makanapa.hakeemiridza.com/privacy"
    static let termsURL = "https://makanapa.hakeemiridza.com/terms"
    static let communityGuidelinesURL = "https://makanapa.hakeemiridza.com/community-guidelines"
    static let supportURL = "https://makanapa.hakeemiridza.com/support"

    static let loginTagline = "Eat first. Decide later."
    static let signIn = "Sign in"
    static let loginInvalidCredentials = "Email or password doesn't match."

    static let communityHeadlineUniversityFormat = "What's %@ eating? 👀"
    static let communitySubtitleUniversityFormat = "Popular around %@"
    static let communityHeadlineAreaFormat = "What's %@ eating? 👀"
    static let communitySubtitleAreaFormat = "Popular around %@"
    static let communityHeadlinePublic = "What's trending near you"
    static let communitySubtitlePublic = "Trending picks nearby"
    static let communityEmptyHeadline = "Nothing trending yet 👀"
    static let communityEmptyDetail = "Every recommendation your community picks helps build this page."
    static let communityLocationPromptHeadline = "See what's trending around you"
    static let communityLocationPromptDetail = "MakanApa uses your location to find popular picks nearby."
    static let communityLocationEnable = "Continue"
    static let accountRequiredDetail = "Picks, Nearby and saves work without an account. Adding places, posting and halal reports need one, so the community knows who's contributing."
    /// The line every guest sign-in prompt carries — signing up upgrades the guest account in
    /// place, so this is literally true, not marketing.
    static let guestCarryOver = "Your picks, saved places and taste profile all come with you. Nothing lost."
    static let guestSettingsDetail = "Sign in to add places and post. Your picks, saves and taste profile come with you."
    static let nudgePicksTitle = "You've been picking well 🍛"
    static let nudgePicksDetail = "MakanApa is learning what you like. Save it to an account so a new phone doesn't mean starting over."
    static let nudgeSavesTitle = "Your food list is growing ❤️"
    static let nudgeSavesDetail = "Keep your saved places safe — on this phone or the next one."
    static let continueAsGuestFailed = "Couldn't start — check your connection and try again."
    static let communityLocationDeniedHeadline = "Location is off"
    static let communityLocationDeniedDetail = "Turn on location access to see what's trending nearby."
    static let communityLocationOpenSettings = "Open Settings"

    // Kept short deliberately — this title sits inline between "Cancel" and this save button,
    // and a longer pair (e.g. "Choose your community" / "Save community") truncates on
    // standard-width devices. The subtitle below already spells out what this screen is for.
    static let communityChooseCommunityTitle = "Community"
    static let communityChooseCommunitySubtitle = "This helps MakanApa show what's popular around people in your community."
    static let communityChooseCommunityFooter = "You can change your community anytime."
    static let communityChooseCommunitySave = "Save"
    static let communityChangeCommunityCTA = "Change ›"
    static let communityAssignCommunityCTA = "Choose ›"
    static let communityUniversitiesSectionHeader = "UNIVERSITIES"
    static let communityAreasSectionHeader = "AREA"
    static let communityRequestUniversityRow = "Can't find your university? Request it"
    static let communityRequestAreaRow = "Can't find your area? Request it"
    // Same inline-title-truncation reasoning as communityChooseCommunityTitle above — flanked
    // by "Cancel" and "Send request", so it needs to stay short.
    static let communityRequestSheetTitle = "Request"
    static let communityRequestUniversityPlaceholder = "e.g. Universiti Malaya"
    static let communityRequestAreaPlaceholder = "e.g. Shah Alam, Cheras, your neighbourhood"
    static let communityRequestSubmit = "Send request"
    static let communityRequestSentConfirmation = "Thanks, we'll take a look! 👀"

    // Community posts ("What KU is saying")
    static let communityPostsSectionFormat = "WHAT %@ IS SAYING"
    static let communityPostsSectionPublic = "WHAT PEOPLE ARE SAYING"
    static let communityPostsSeeAll = "See all ›"
    static let communityPostsEmptyHeadline = "No one's said anything yet"
    static let communityPostsEmptyDetailFormat = "Be the first to share what %@ is eating 🍛"
    static let communityPostsJoinPrompt = "Join a university or area community to see and share thoughts."
    static let communityPostsComposePlaceholder = "Share a thought… best teh tarik? new spot? 👀"
    static let communityPostsReplyPlaceholder = "Write a reply…"
    static let communityPostsShareCTA = "Share a thought"
    static let communityPostsPost = "Post"
    static let communityPostsReply = "Reply"
    static let communityPostsTagPlace = "Tag a place"
    static let communityPostsTagPlaceSearch = "Search places on MakanApa"
    static let communityPostsViewAllRepliesFormat = "View all %d replies"
    static let communityPostsReportTitle = "Report post"
    static let communityPostsReportDetail = "Reports are anonymous. We review every one, and posts with several reports are hidden automatically."
    static let communityPostsReportNotePlaceholder = "Anything else we should know? (optional)"
    static let communityPostsReportSubmit = "Send report"
    static let communityPostsReportedToast = "Thanks — we'll take a look. You won't see this post again."
    static let communityPostsBlockTitleFormat = "Block %@?"
    static let communityPostsBlockDetail = "You won't see each other's posts or replies. You can unblock in Settings → Blocked users."
    static let communityPostsDeleteTitle = "Delete this post?"
    static let communityPostsGenericError = "Couldn't post that right now. Try again in a bit."
    static let communityPostsGuidelines = "Keep it friendly and about food. No links, ads or personal attacks."
    static let supportEmail = "hakeemiridza@gmail.com"

    static let communityAddPlaceMenuItem = "Add a place"
    static let communityAddPlaceCTA = "Know a good spot? Add it →"
    static let communityMyPlacesMenuItem = "My places"
    static let communitySearchTitle = "Add a place"
    static let communitySearchSubtitle = "Found somewhere good? 👀 Let's check if we know it first."
    static let communitySearchPlaceholder = "Search for a place"
    static let communitySearchExistingLabel = "Already on MakanApa"
    static let communitySearchGoogleLabel = "Found on Google"
    static let communityCantFindHeadline = "Not on Google? No problem."
    static let communityCantFindDetail = "Small places deserve to be discovered too."
    static let communityAddManually = "Add it manually →"
    static let communityDetailsTitle = "Details"
    static let communityLocationStepTitle = "Location"
    static let communityUseCurrentLocation = "Use my current location"
    static let communityChooseOnMap = "Choose on map"
    static let communityLocationReadyDetail = "We'll use where you are right now. This place should be somewhere nearby."
    static let communityLocationImmutable = "Set when this place was added. Location can't be changed."
    static let communityConfirmLocation = "Confirm this location"
    static let communityRecenterOnMe = "Recenter on me"
    static let communityReviewTitle = "Review"
    static let communitySubmitForReview = "Submit for review"
    static let communitySubmissionSuccessHeadline = "Sent for review!"
    static let communitySubmissionSuccessDetail = "We'll check the place before it shows up around MakanApa."
    static let communityMyPlacesTitle = "My places"
    static let communityStatusPending = "Pending review"
    static let communityStatusApproved = "Approved · Now discoverable on MakanApa"
    static let communityStatusChangesRequested = "Changes requested"
    static let communityStatusRejected = "Not approved"
    static let communityStatusCancelled = "Cancelled"
    static let communityUpdateSubmission = "Update submission"
    static let communityCancelSubmission = "Cancel submission"

    static let communityStepFormat = "Step %d of %d · %@"
    static let communityDetailsHeadline = "Tell us about this place"
    static let communitySpendFooter = "Usually around how much per person?"
    static let communitySpendErrorInline = "Enter an estimated spend per person."
    static let communityAddMoreDetailsTitle = "Add more details"
    static let communityAddMoreDetailsSubtitle = "Address, phone & socials"
    static let communityAddMenuTitle = "Add menu"
    static let communityAddMenuSubtitle = "Help people know what's good here."
    static let communityBlankIsFine = "You can leave these blank — the community can help complete them later."
    static let communityAddMenuItemCTA = "Add another item"

    // MARK: - Pick from saved places

    static let savedPickTitle = "Pick from my saved"
    static func savedPickSubtitle(count: Int) -> String { "Shuffle your \(count) saved places" }
    static let savedPickHint = "Shuffles your saved places and picks one for you"
    static let savedPickButton = "Pick one for me"
    static let savedPickNeedMore = "Save one more place and MakanApa can pick between them."
    static let savedPickShuffling = "Shuffling your saved places…"
    static let savedPickDealt = "Here's your pick"
    static let savedPickNoneAvailable = "None of your saved places can be picked right now. They may be closed, or hidden by Halal-only."
    static let savedPickFailed = "Couldn't pick right now. Check your connection and try again."
    static let savedPickRateLimited = "That's a lot of shuffling. Try again in a minute."
    static let savedPickCardBackLabel = "Saved place, face down"
    static let back = "Back"
    static let cancel = "Cancel"

    // MARK: - Pick explanation

    static let whyThisPick = "Why this one?"
    static let whyNotPrompt = "Help me learn — why not this one?"
    static let pickingInProgress = "Nasi's thinking..."
    static let eatHere = "Eat here"
}
